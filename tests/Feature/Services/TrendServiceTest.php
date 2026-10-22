<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Weather\Data\Trend;
use App\Weather\Services\TrendService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

/**
 * Trends are computed from persisted history so they survive reloads. The key
 * behaviors: a configurable noise threshold reports "Steady", and a missing
 * comparison point yields an "unknown" trend rather than a misleading zero.
 */
final class TrendServiceTest extends TestCase
{
    use MakesWeatherData;
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze the clock; the service compares "now" against ~1h/3h ago.
        $this->now = CarbonImmutable::parse('2026-07-21T17:00:00Z');
        $this->travelTo($this->now);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function service(): TrendService
    {
        return app(TrendService::class);
    }

    public function test_temperature_rise_beyond_threshold_reads_as_warmer(): void
    {
        // Reading ~1h ago (within the 20 min tolerance window).
        $this->storeObservation($this->now->subMinutes(60), ['temperature' => 70.0]);
        $current = $this->storeObservation($this->now, ['temperature' => 76.0]);

        $trend = $this->service()->temperature($current);

        $this->assertSame(Trend::RISING, $trend->direction);
        $this->assertSame('Warmer', $trend->label);
        $this->assertSame(6.0, $trend->delta);
    }

    public function test_temperature_fall_reads_as_cooler(): void
    {
        $this->storeObservation($this->now->subMinutes(60), ['temperature' => 80.0]);
        $current = $this->storeObservation($this->now, ['temperature' => 74.0]);

        $trend = $this->service()->temperature($current);

        $this->assertSame(Trend::FALLING, $trend->direction);
        $this->assertSame('Cooler', $trend->label);
        $this->assertSame(-6.0, $trend->delta);
    }

    public function test_change_below_threshold_reads_as_steady(): void
    {
        // 0.3°F change is below the 0.5°F threshold -> Steady.
        $this->storeObservation($this->now->subMinutes(60), ['temperature' => 75.0]);
        $current = $this->storeObservation($this->now, ['temperature' => 75.3]);

        $trend = $this->service()->temperature($current);

        $this->assertSame(Trend::STEADY, $trend->direction);
        $this->assertSame('Steady', $trend->label);
    }

    public function test_missing_comparison_point_yields_unknown(): void
    {
        // Only the current reading exists; nothing ~1h ago within tolerance.
        $current = $this->storeObservation($this->now, ['temperature' => 75.0]);

        $trend = $this->service()->temperature($current);

        $this->assertSame(Trend::UNKNOWN, $trend->direction);
        $this->assertNull($trend->delta);
    }

    public function test_pressure_trend_uses_rising_falling_language(): void
    {
        // Pressure window is 3 hours.
        $this->storeObservation($this->now->subMinutes(180), ['pressure_relative' => 30.10]);
        $current = $this->storeObservation($this->now, ['pressure_relative' => 29.95]);

        $trend = $this->service()->pressure($current);

        $this->assertSame(Trend::FALLING, $trend->direction);
        $this->assertSame('Falling', $trend->label);
    }

    public function test_wind_trend_uses_increasing_easing_language(): void
    {
        $this->storeObservation($this->now->subMinutes(60), ['wind_speed_avg_10m' => 4.0]);
        $current = $this->storeObservation($this->now, ['wind_speed_avg_10m' => 12.0]);

        $trend = $this->service()->wind($current);

        $this->assertSame(Trend::RISING, $trend->direction);
        $this->assertSame('Increasing', $trend->label);
    }

    public function test_temperature_since_midnight_compares_against_first_reading_of_day(): void
    {
        $localMidnight = CarbonImmutable::now('America/New_York')->startOfDay()->utc();

        // First reading after local midnight is the baseline.
        $this->storeObservation($localMidnight->addMinutes(5), ['temperature' => 68.0]);
        $current = $this->storeObservation($this->now, ['temperature' => 82.0]);

        $trend = $this->service()->temperatureSinceMidnight($current);

        $this->assertSame(Trend::RISING, $trend->direction);
        $this->assertSame('since midnight', $trend->comparison);
        $this->assertSame(14.0, $trend->delta);
    }
}
