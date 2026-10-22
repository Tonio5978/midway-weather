<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\CollectionStatus;
use App\Models\ForecastDay;
use App\Models\Observation;
use App\Weather\Services\DemoDataGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

/**
 * Demo mode makes the dashboard fully viewable before real credentials exist.
 * The generator must produce a coherent, self-consistent dataset entirely
 * offline — it may not call any third-party API.
 */
final class DemoDataGeneratorTest extends TestCase
{
    use MakesWeatherData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['weather.timezone' => 'America/New_York']);
    }

    private function generator(): DemoDataGenerator
    {
        return app(DemoDataGenerator::class);
    }

    public function test_it_generates_observations_a_forecast_and_health_offline(): void
    {
        Http::fake();

        $now = CarbonImmutable::parse('2026-07-21T17:00:00Z');
        $result = $this->generator()->generate($now, 6);

        // 6 hours at 5-minute cadence, inclusive of the endpoint => 73 points.
        $this->assertSame(73, $result['observations']);
        $this->assertSame(7, $result['forecast_days']);
        $this->assertDatabaseCount('observations', 73);
        $this->assertDatabaseCount('forecast_days', 7);

        // It records BOTH sources as healthy so the status panel looks live.
        $ambient = CollectionStatus::query()->where('source', CollectionStatus::SOURCE_AMBIENT)->first();
        $forecast = CollectionStatus::query()->where('source', CollectionStatus::SOURCE_FORECAST)->first();
        $this->assertNotNull($ambient?->last_success_at);
        $this->assertNotNull($forecast?->last_success_at);

        // The all-important guarantee: no outbound weather API calls.
        Http::assertNothingSent();
    }

    public function test_generated_observations_carry_the_synthetic_demo_mac(): void
    {
        $this->generator()->generate(CarbonImmutable::parse('2026-07-21T17:00:00Z'), 1);

        $latest = Observation::query()->latestFirst()->first();
        $this->assertSame(DemoDataGenerator::DEMO_MAC, $latest->device_mac);
    }

    public function test_the_generated_forecast_begins_today(): void
    {
        $now = CarbonImmutable::parse('2026-07-21T17:00:00Z');
        $this->generator()->generate($now, 1);

        $today = CarbonImmutable::now('America/New_York')->toDateString();
        $this->assertDatabaseHas('forecast_days', ['date' => $today]);

        // 7 consecutive days, none in the past.
        $this->assertSame(7, ForecastDay::query()->count());
        $this->assertSame(0, ForecastDay::query()->whereDate('date', '<', $today)->count());
    }

    public function test_regenerating_is_idempotent_for_overlapping_windows(): void
    {
        $now = CarbonImmutable::parse('2026-07-21T17:00:00Z');

        $this->generator()->generate($now, 1);
        $countAfterFirst = Observation::query()->count();

        // Same reference time and window => same minute-keyed rows, updated in place.
        $this->generator()->generate($now, 1);

        $this->assertSame($countAfterFirst, Observation::query()->count());
        $this->assertDatabaseCount('forecast_days', 7);
    }
}
