<?php

declare(strict_types=1);

namespace App\Weather\Services;

use App\Models\Observation;
use App\Weather\Data\Trend;
use App\Weather\Providers\PrimaryWeatherStore;
use Carbon\CarbonImmutable;

/**
 * Calculates weather trends from locally stored observations.
 *
 * Trends are derived from persisted history (not from JavaScript memory) so
 * they survive page reloads and reflect the actual station record. Each trend
 * has a configurable minimum delta so ordinary sensor noise is reported as
 * "Steady" rather than a misleading rise/fall. Thresholds and comparison
 * windows live in config/weather.php.
 */
class TrendService
{
    public function __construct(private readonly ObservationRepository $observations) {}

    /**
     * Temperature change over the configured window (default 1 hour).
     * Labels lean into weather language: Warmer / Cooler / Steady.
     */
    public function temperature(Observation $current): Trend
    {
        return $this->compare(
            current: $current->temperature,
            metric: 'temperature',
            windowMinutes: (int) config('weather.trends.windows.temperature_minutes', 60),
            threshold: (float) config('weather.trends.temperature_delta_f', 0.5),
            valueOf: fn (Observation $o) => $o->temperature,
            labels: ['up' => 'Warmer', 'down' => 'Cooler', 'steady' => 'Steady'],
            comparison: 'past hour',
        );
    }

    /**
     * Temperature change since local midnight (start of the current day).
     */
    public function temperatureSinceMidnight(Observation $current): Trend
    {
        if ($current->temperature === null) {
            return Trend::unknown('since midnight');
        }

        $timezone = (string) config('weather.timezone', 'America/New_York');
        $midnightUtc = CarbonImmutable::now($timezone)->startOfDay()->utc();

        $baseline = app(PrimaryWeatherStore::class)->observations()
            ->where('observed_at', '>=', $midnightUtc)
            ->whereNotNull('temperature')
            ->orderBy('observed_at')
            ->first();

        if ($baseline === null || $baseline->temperature === null) {
            return Trend::unknown('since midnight');
        }

        $delta = $current->temperature - $baseline->temperature;
        $threshold = (float) config('weather.trends.temperature_delta_f', 0.5);

        return $this->buildTrend(
            $delta,
            $threshold,
            ['up' => 'Warmer', 'down' => 'Cooler', 'steady' => 'Steady'],
            'since midnight',
        );
    }

    /**
     * Pressure change over the configured window (default 3 hours).
     * Rising / Falling / Steady — the classic barometric trend.
     */
    public function pressure(Observation $current): Trend
    {
        return $this->compare(
            current: $current->pressure_relative,
            metric: 'pressure',
            windowMinutes: (int) config('weather.trends.windows.pressure_minutes', 180),
            threshold: (float) config('weather.trends.pressure_delta_inhg', 0.02),
            valueOf: fn (Observation $o) => $o->pressure_relative,
            labels: ['up' => 'Rising', 'down' => 'Falling', 'steady' => 'Steady'],
            comparison: 'past 3 hours',
        );
    }

    /**
     * Humidity change over the configured window (default 1 hour).
     */
    public function humidity(Observation $current): Trend
    {
        return $this->compare(
            current: $current->humidity === null ? null : (float) $current->humidity,
            metric: 'humidity',
            windowMinutes: (int) config('weather.trends.windows.humidity_minutes', 60),
            threshold: (float) config('weather.trends.humidity_delta_pct', 2.0),
            valueOf: fn (Observation $o) => $o->humidity === null ? null : (float) $o->humidity,
            labels: ['up' => 'Increasing', 'down' => 'Decreasing', 'steady' => 'Steady'],
            comparison: 'past hour',
        );
    }

    /**
     * Wind trend using the 10-minute average (falling back to instantaneous)
     * compared with the reading ~1 hour ago.
     */
    public function wind(Observation $current): Trend
    {
        $currentValue = $current->wind_speed_avg_10m ?? $current->wind_speed;

        return $this->compare(
            current: $currentValue,
            metric: 'wind',
            windowMinutes: (int) config('weather.trends.windows.wind_minutes', 60),
            threshold: (float) config('weather.trends.wind_delta_mph', 2.0),
            valueOf: fn (Observation $o) => $o->wind_speed_avg_10m ?? $o->wind_speed,
            labels: ['up' => 'Increasing', 'down' => 'Easing', 'steady' => 'Steady'],
            comparison: 'past hour',
        );
    }

    /**
     * Generic comparison against the observation nearest a past window.
     *
     * @param  callable(Observation): (float|null)  $valueOf
     * @param  array{up: string, down: string, steady: string}  $labels
     */
    private function compare(
        ?float $current,
        string $metric,
        int $windowMinutes,
        float $threshold,
        callable $valueOf,
        array $labels,
        string $comparison,
    ): Trend {
        if ($current === null) {
            return Trend::unknown($comparison);
        }

        $tolerance = (int) config('weather.trends.tolerance_minutes', 20);
        $target = CarbonImmutable::now('UTC')->subMinutes($windowMinutes);

        $past = $this->observations->nearest($target, $tolerance);

        if ($past === null) {
            return Trend::unknown($comparison);
        }

        $pastValue = $valueOf($past);

        if ($pastValue === null) {
            return Trend::unknown($comparison);
        }

        return $this->buildTrend($current - $pastValue, $threshold, $labels, $comparison);
    }

    /**
     * Turn a signed delta into a Trend, applying the noise threshold.
     *
     * @param  array{up: string, down: string, steady: string}  $labels
     */
    private function buildTrend(float $delta, float $threshold, array $labels, string $comparison): Trend
    {
        if (abs($delta) < $threshold) {
            return new Trend(Trend::STEADY, $labels['steady'], $delta, $comparison);
        }

        if ($delta > 0) {
            return new Trend(Trend::RISING, $labels['up'], $delta, $comparison);
        }

        return new Trend(Trend::FALLING, $labels['down'], $delta, $comparison);
    }
}
