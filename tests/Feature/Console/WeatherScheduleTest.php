<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class WeatherScheduleTest extends TestCase
{
    public function test_weather_commands_have_the_production_schedule(): void
    {
        $events = collect($this->app->make(Schedule::class)->events());

        $maintenance = $this->eventFor($events, 'optimize:clear');
        $this->assertSame('0 0 1 * *', $maintenance->expression);
        $this->assertSame(config('weather.timezone'), $maintenance->timezone);
        $this->assertTrue($maintenance->withoutOverlapping);
        $this->assertSame(60, $maintenance->expiresAt);
        $this->assertSame(storage_path('logs/scheduler-maintenance.log'), $maintenance->output);

        $collection = $this->eventFor($events, 'weather:collect');
        $this->assertSame('* * * * *', $collection->expression);
        $this->assertTrue($collection->withoutOverlapping);
        $this->assertSame(10, $collection->expiresAt);
        $this->assertTrue($collection->runInBackground);

        $forecast = $this->eventFor($events, 'weather:forecast');
        $this->assertSame('0 * * * *', $forecast->expression);
        $this->assertTrue($forecast->withoutOverlapping);
        $this->assertSame(30, $forecast->expiresAt);
        $this->assertTrue($forecast->runInBackground);

        $cleanup = $this->eventFor($events, 'weather:cleanup');
        $this->assertSame('0 3 * * *', $cleanup->expression);
        $this->assertSame(config('weather.timezone'), $cleanup->timezone);
        $this->assertTrue($cleanup->withoutOverlapping);
    }

    /** @param  Collection<int, Event>  $events */
    private function eventFor(Collection $events, string $command): Event
    {
        $event = $events->first(
            static fn (Event $event): bool => str_contains($event->command, $command),
        );

        $this->assertNotNull($event, sprintf('The [%s] command is not scheduled.', $command));

        return $event;
    }
}
