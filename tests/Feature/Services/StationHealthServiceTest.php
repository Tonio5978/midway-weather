<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\CollectionStatus;
use App\Weather\Services\StationHealthService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

/**
 * Freshness classification drives the on-screen status badge and the
 * stale-dimming behavior. The boundaries (10 min fresh, 20 min stale) are
 * asserted directly since they gate whether the wall display flags a problem.
 */
final class StationHealthServiceTest extends TestCase
{
    use MakesWeatherData;
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-07-21T17:00:00Z');

        config([
            'weather.staleness.fresh_minutes' => 10,
            'weather.staleness.stale_minutes' => 20,
            'weather.staleness.forecast_stale_hours' => 3,
        ]);
    }

    private function service(): StationHealthService
    {
        return app(StationHealthService::class);
    }

    public function test_no_observation_is_no_data(): void
    {
        $this->assertSame(StationHealthService::NO_DATA, $this->service()->observationFreshness(null, $this->now));
        $this->assertNull($this->service()->observationAgeMinutes(null, $this->now));
    }

    public function test_recent_observation_is_fresh(): void
    {
        $obs = $this->storeObservation($this->now->subMinutes(5));
        $this->assertSame(StationHealthService::FRESH, $this->service()->observationFreshness($obs, $this->now));
    }

    public function test_at_the_fresh_boundary_is_still_fresh(): void
    {
        // Exactly 10 minutes old -> fresh (<= fresh_minutes).
        $obs = $this->storeObservation($this->now->subMinutes(10));
        $this->assertSame(StationHealthService::FRESH, $this->service()->observationFreshness($obs, $this->now));
    }

    public function test_between_fresh_and_stale_is_delayed(): void
    {
        $obs = $this->storeObservation($this->now->subMinutes(15));
        $this->assertSame(StationHealthService::DELAYED, $this->service()->observationFreshness($obs, $this->now));
    }

    public function test_beyond_the_stale_boundary_is_stale(): void
    {
        // 21 minutes old -> stale (> stale_minutes).
        $obs = $this->storeObservation($this->now->subMinutes(21));
        $this->assertSame(StationHealthService::STALE, $this->service()->observationFreshness($obs, $this->now));
    }

    public function test_age_in_minutes_is_reported(): void
    {
        $obs = $this->storeObservation($this->now->subMinutes(7));
        $this->assertSame(7, $this->service()->observationAgeMinutes($obs, $this->now));
    }

    public function test_forecast_is_stale_when_missing_or_old(): void
    {
        $this->assertTrue($this->service()->forecastIsStale(null, $this->now));
        $this->assertTrue($this->service()->forecastIsStale($this->now->subHours(4), $this->now));
        $this->assertFalse($this->service()->forecastIsStale($this->now->subHours(2), $this->now));
    }

    public function test_status_summary_defaults_to_unhealthy_when_absent(): void
    {
        $summary = $this->service()->statusSummary('ambient');

        $this->assertFalse($summary['healthy']);
        $this->assertNull($summary['last_success_at']);
        $this->assertSame(0, $summary['consecutive_failures']);
    }

    public function test_status_summary_reports_healthy_after_a_success(): void
    {
        CollectionStatus::recordSuccess('ambient');

        $summary = $this->service()->statusSummary('ambient');

        $this->assertTrue($summary['healthy']);
        $this->assertNotNull($summary['last_success_at']);
        $this->assertSame(0, $summary['consecutive_failures']);
    }

    public function test_status_summary_reports_unhealthy_after_a_failure(): void
    {
        CollectionStatus::recordFailure('ambient', 'boom');

        $summary = $this->service()->statusSummary('ambient');

        $this->assertFalse($summary['healthy']);
        $this->assertSame(1, $summary['consecutive_failures']);
        $this->assertSame('boom', $summary['last_error']);
    }
}
