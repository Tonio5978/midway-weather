<?php

declare(strict_types=1);

namespace App\Weather\Services;

use App\Models\CollectionStatus;
use App\Models\ForecastDay;
use App\Weather\Support\WeatherCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Read-only seven-local-day projection of Weather's collected forecast. */
final class DailyForecast
{
    public function project(CarbonImmutable $now): ?array
    {
        return DB::transaction(function () use ($now): ?array {
            $timezone = (string) config('weather.timezone', 'America/New_York');
            $start = $now->setTimezone($timezone)->startOfDay();
            $end = $start->addDays(7);
            $status = CollectionStatus::query()->where('source', CollectionStatus::SOURCE_FORECAST)->first();
            $failed = $status !== null && ($status->consecutive_failures > 0
                || ($status->last_failure_at !== null && ($status->last_success_at === null || $status->last_failure_at->gt($status->last_success_at))));
            $days = [];
            foreach (ForecastDay::query()->where('date', '>=', $start->toDateString())->where('date', '<', $end->toDateString())->orderBy('date')->limit(7)->get() as $row) {
                if ($row->fetched_at === null) {
                    continue;
                }
                $retrieved = CarbonImmutable::instance($row->fetched_at)->utc();
                if ($retrieved->gt($now)) {
                    continue;
                }
                $high = $this->number($row->getRawOriginal('temperature_high'), -150, 150);
                $low = $this->number($row->getRawOriginal('temperature_low'), -150, 150);
                if ($high !== null && $low !== null && $low > $high) {
                    continue;
                }
                $probability = $this->number($row->getRawOriginal('precipitation_probability'), 0, 100);
                $code = $row->weather_code;
                $condition = WeatherCode::label($code) === 'Unknown' ? null : WeatherCode::describe($code);
                if ($high === null && $low === null && $probability === null && $condition === null) {
                    continue;
                }
                $deadline = $retrieved->addHours(max(0, (int) config('weather.staleness.forecast_stale_hours', 3)));
                $days[] = [
                    'date' => $row->date->toDateString(),
                    'temperature_high_f' => $high,
                    'temperature_low_f' => $low,
                    'precipitation_probability_percent' => $probability,
                    'condition' => $condition,
                    'retrieved_at' => $retrieved->toIso8601String(),
                    'freshness' => ['state' => $now->lte($deadline) ? 'fresh' : 'stale', 'fresh_until' => $deadline->toIso8601String()],
                    'last_known_good' => $failed,
                ];
            }
            if ($days === []) {
                return null;
            }

            return [
                'timezone' => $timezone,
                'period' => ['starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String()],
                'availability' => count($days) < 7 || $failed || $status?->last_success_at === null
                    || collect($days)->contains(fn (array $day): bool => $day['freshness']['state'] !== 'fresh') ? 'degraded' : 'available',
                'days' => $days,
            ];
        });
    }

    private function number(mixed $value, float $minimum, float $maximum): ?float
    {
        return is_numeric($value) && is_finite((float) $value) && $value >= $minimum && $value <= $maximum ? (float) $value : null;
    }
}
