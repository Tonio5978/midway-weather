<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Weather\Services\DailySummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

/**
 * Daily summaries let the display compare "today vs. recent days" cheaply and
 * preserve history after raw observations are pruned. Aggregation must pick the
 * right reducer per field — notably rain uses the running daily max, not a sum
 * of the cumulative field (which would double-count).
 */
final class DailySummaryServiceTest extends TestCase
{
    use MakesWeatherData;
    use RefreshDatabase;

    private DailySummaryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['weather.timezone' => 'America/New_York']);
        $this->service = app(DailySummaryService::class);
    }

    public function test_it_aggregates_a_days_observations_into_one_summary(): void
    {
        // Local date 2026-07-20 (America/New_York). Pick UTC instants that fall
        // squarely inside that local day (EDT = UTC-4).
        $localDate = CarbonImmutable::parse('2026-07-20', 'America/New_York');
        $morning = CarbonImmutable::parse('2026-07-20T14:00:00Z'); // 10:00 EDT
        $afternoon = CarbonImmutable::parse('2026-07-20T21:00:00Z'); // 17:00 EDT

        $this->storeObservation($morning, [
            'temperature' => 72.0,
            'wind_gust' => 12.0,
            'rain_daily' => 0.20,
            'uv_index' => 3.0,
            'pressure_relative' => 30.10,
        ]);
        $this->storeObservation($afternoon, [
            'temperature' => 94.0,
            'wind_gust' => 21.0,
            'rain_daily' => 0.50, // running daily accumulator, higher later
            'uv_index' => 8.0,
            'pressure_relative' => 29.90,
        ]);

        $summary = $this->service->rebuildForDate($localDate);

        $this->assertNotNull($summary);
        $this->assertSame(94.0, $summary->temperature_high);
        $this->assertSame(72.0, $summary->temperature_low);
        $this->assertSame(21.0, $summary->max_wind_gust);
        // Max of the running accumulator, NOT the sum (0.70 would be wrong).
        $this->assertSame(0.50, $summary->total_rain);
        $this->assertSame(8.0, $summary->max_uv);
        $this->assertSame(29.90, $summary->min_pressure);
        $this->assertSame(30.10, $summary->max_pressure);
        $this->assertSame(2, $summary->observation_count);
    }

    public function test_a_day_without_observations_yields_no_summary(): void
    {
        $summary = $this->service->rebuildForDate(CarbonImmutable::parse('2026-07-20', 'America/New_York'));

        $this->assertNull($summary);
        $this->assertDatabaseCount('daily_summaries', 0);
    }

    public function test_rebuilding_the_same_date_overwrites_rather_than_duplicates(): void
    {
        $localDate = CarbonImmutable::parse('2026-07-20', 'America/New_York');
        $instant = CarbonImmutable::parse('2026-07-20T17:00:00Z');

        $this->storeObservation($instant, ['temperature' => 80.0]);
        $first = $this->service->rebuildForDate($localDate);

        // A hotter reading arrives for the same local day.
        $this->storeObservation($instant->addMinutes(30), ['temperature' => 96.0]);
        $second = $this->service->rebuildForDate($localDate);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('daily_summaries', 1);
        $this->assertSame(96.0, $second->fresh()->temperature_high);
    }

    public function test_rebuild_recent_summarizes_only_days_that_have_data(): void
    {
        $today = CarbonImmutable::now('America/New_York')->startOfDay();

        // Data today and two days ago; nothing yesterday.
        $this->storeObservation($today->addHours(15)->utc(), ['temperature' => 88.0]);
        $this->storeObservation($today->subDays(2)->addHours(15)->utc(), ['temperature' => 84.0]);

        $summaries = $this->service->rebuildRecent(5);

        // Only the two days with observations produce summaries.
        $this->assertCount(2, $summaries);
        $this->assertDatabaseCount('daily_summaries', 2);
    }
}
