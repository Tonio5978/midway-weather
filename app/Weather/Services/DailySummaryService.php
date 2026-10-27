<?php

declare(strict_types=1);

namespace App\Weather\Services;

use App\Models\DailySummary;
use App\Models\Observation;
use App\Weather\Providers\PrimaryWeatherStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Computes and persists per-day aggregates from observations.
 *
 * Summaries are persisted (rather than recomputed on every request) so the
 * display can cheaply compare "rain today vs. recent days" and so history
 * survives after detailed observations are pruned by retention cleanup.
 * Recomputation is idempotent: re-running a date overwrites its row.
 */
class DailySummaryService
{
    /**
     * Rebuild the summary for a single local date from its observations.
     * Returns null when the day has no observations.
     */
    public function rebuildForDate(CarbonImmutable $localDate, ?int $stationId = null): ?DailySummary
    {
        $timezone = (string) config('weather.timezone', 'America/New_York');

        $startUtc = $localDate->setTimezone($timezone)->startOfDay()->utc();
        $endUtc = $localDate->setTimezone($timezone)->endOfDay()->utc();

        $observations = app(PrimaryWeatherStore::class)->observations($stationId)
            ->whereBetween('observed_at', [$startUtc, $endUtc])
            ->get();

        if ($observations->isEmpty()) {
            return null;
        }

        $attributes = $this->aggregate($observations);
        $attributes['date'] = $localDate->toDateString();

        $store = app(PrimaryWeatherStore::class);
        $id = $stationId ?? $store->primaryId();
        if ($id && $id !== $store->settings()?->legacy_station_id) {
            $attributes['station_id'] = $id;
        }

        return $store->summaries($stationId)->updateOrCreate(
            ['date' => $localDate->toDateString()],
            $attributes,
        );
    }

    /**
     * Rebuild summaries for the last N local days (inclusive of today).
     *
     * @return Collection<int, DailySummary>
     */
    public function rebuildRecent(int $days, ?int $stationId = null): Collection
    {
        $timezone = (string) config('weather.timezone', 'America/New_York');
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $summaries = new Collection;

        for ($i = 0; $i < $days; $i++) {
            $date = $today->subDays($i);
            $summary = $this->rebuildForDate($date, $stationId);

            if ($summary !== null) {
                $summaries->push($summary);
            }
        }

        return $summaries;
    }

    /**
     * Aggregate a set of observations into summary column values.
     *
     * Rain total uses the max of `rain_daily` (a running daily accumulator on
     * the station) rather than summing per-reading values, which would
     * double-count the cumulative field.
     *
     * @param  Collection<int, Observation>  $observations
     * @return array<string, mixed>
     */
    private function aggregate(Collection $observations): array
    {
        return [
            'temperature_high' => $this->max($observations, 'temperature'),
            'temperature_low' => $this->min($observations, 'temperature'),
            'max_wind_gust' => $this->max($observations, 'wind_gust'),
            'total_rain' => $this->max($observations, 'rain_daily'),
            'max_uv' => $this->max($observations, 'uv_index'),
            'max_solar_radiation' => $this->max($observations, 'solar_radiation'),
            'min_pressure' => $this->min($observations, 'pressure_relative'),
            'max_pressure' => $this->max($observations, 'pressure_relative'),
            'observation_count' => $observations->count(),
        ];
    }

    /**
     * @param  Collection<int, Observation>  $observations
     */
    private function max(Collection $observations, string $attribute): ?float
    {
        $values = $observations->pluck($attribute)->filter(fn ($v) => $v !== null);

        return $values->isEmpty() ? null : (float) $values->max();
    }

    /**
     * @param  Collection<int, Observation>  $observations
     */
    private function min(Collection $observations, string $attribute): ?float
    {
        $values = $observations->pluck($attribute)->filter(fn ($v) => $v !== null);

        return $values->isEmpty() ? null : (float) $values->min();
    }
}
