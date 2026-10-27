<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Weather\Services\WeatherCollectionService;
use Illuminate\Console\Command;

/**
 * Polls the latest Ambient observation and persists it. Designed to run every
 * minute from the scheduler. In demo mode it delegates to `weather:demo`
 * so the dashboard stays populated without real credentials.
 */
class CollectWeatherCommand extends Command
{
    protected $signature = 'weather:collect';

    protected $description = 'Fetch the latest Ambient Weather observation and store it locally';

    public function handle(WeatherCollectionService $service): int
    {
        if (config('weather.demo_mode')) {
            $this->warn('Demo mode is enabled — generating fixture data instead of calling Ambient.');
            $this->call('weather:demo', ['--hours' => 3]);

            return self::SUCCESS;
        }

        $result = $service->collect();

        if (! $result['success']) {
            $this->error('Collection failed: '.$result['error']);

            return self::FAILURE;
        }

        if ($result['skipped'] ?? false) {
            $this->info('Collection deferred by provider settings or cooldown.');

            return self::SUCCESS;
        }
        $observation = $result['observation'];
        $this->info(sprintf(
            'Stored observation for %s (%s). Temp: %s°F',
            $observation->observed_at->toIso8601String(),
            $result['created'] ? 'new' : 'updated',
            $observation->temperature ?? 'n/a',
        ));

        return self::SUCCESS;
    }
}
