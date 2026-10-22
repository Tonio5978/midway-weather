<?php

declare(strict_types=1);

namespace App\Weather\Services;

use App\Models\CollectionStatus;
use App\Models\ForecastDay;
use App\Models\Observation;
use App\Weather\Support\AtmosphereModifier;
use App\Weather\Support\Number;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Read-only projection of existing Weather data; never invokes collection or presentation caches. */
class CanonicalSummary
{
    private const CODES = [0, 1, 2, 3, 45, 48, 51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 71, 73, 75, 77, 80, 81, 82, 85, 86, 95, 96, 99];

    public function project(CarbonImmutable $now): ?array
    {
        // SQLite read snapshot keeps data and collection-status evidence consistent.
        return DB::transaction(fn () => $this->read($now));
    }

    private function read(CarbonImmutable $now): ?array
    {
        $timezone = (string) config('weather.timezone');
        $start = $now->setTimezone($timezone)->startOfDay();
        $end = $start->addDay();
        $period = ['starts_at' => $start->utc()->toIso8601String(), 'ends_at' => $end->utc()->toIso8601String()];
        $latest = null;
        $observed = null;
        $impaired = false;
        $since = $now->subDays(max(1, (int) config('weather.retention_days', 10)));

        // The existing indexed timestamp/retention bounds cap the scan. No cached
        // daily aggregate is trusted: it has no covered-through timestamp.
        foreach (Observation::query()->where('observed_at', '>=', $since)->latestFirst()->cursor() as $row) {
            $time = $this->observationTime($row, $now);
            $temperature = $this->temperature($row->getRawOriginal('temperature'));
            if ($time === null || $temperature === null) {
                if ($latest === null) {
                    $impaired = true;
                }

                continue;
            }
            if ($latest === null) {
                $latest = ['row' => $row, 'time' => $time, 'temperature' => $temperature];
            }
            if ($time->lt($start)) {
                break;
            }
            if ($observed === null) {
                $observed = [
                    'period' => $period,
                    'as_of_at' => $time->toIso8601String(),
                    'temperature_high_f' => $temperature,
                    'temperature_low_f' => $temperature,
                    'freshness' => $this->freshness($time, $now),
                ];
            } else {
                $observed['temperature_high_f'] = max($observed['temperature_high_f'], $temperature);
                $observed['temperature_low_f'] = min($observed['temperature_low_f'], $temperature);
            }
        }
        if ($latest === null) {
            return null;
        }

        $station = $this->refreshState(CollectionStatus::SOURCE_AMBIENT, $now);
        $forecastState = $this->refreshState(CollectionStatus::SOURCE_FORECAST, $now);
        $row = ForecastDay::query()->whereDate('date', $start->toDateString())->first();
        $forecast = $this->forecast($row, $period, $forecastState['failed'], $now);
        $observation = [
            'temperature_f' => $latest['temperature'],
            'observed_at' => $latest['time']->toIso8601String(),
            // Neither updated_at nor the global successful poll identifies retrieval of this row.
            'retrieved_at' => null,
            'freshness' => $this->freshness($latest['time'], $now),
            'last_known_good' => $station['failed'],
            'solar_radiation_w_m2' => $this->nonnegative($latest['row']->getRawOriginal('solar_radiation')),
        ];
        $rain = $this->nonnegative($latest['row']->getRawOriginal('rain_hourly'));
        $code = $forecast === null ? null : $this->knownCode($row?->getRawOriginal('weather_code'));
        $atmosphere = $this->classification($code, $rain);
        $interpretation = null;
        if ($atmosphere !== null) {
            $fresh = CarbonImmutable::parse($observation['freshness']['fresh_until']);
            $stale = CarbonImmutable::parse($observation['freshness']['stale_after']);
            if ($forecast !== null) {
                $forecastDeadline = CarbonImmutable::parse($forecast['freshness']['fresh_until']);
                $fresh = $fresh->min($forecastDeadline);
                $stale = $stale->min($forecastDeadline);
            }
            $interpretation = [
                'based_on' => $forecast === null ? ['observation'] : ['observation', 'forecast_today'],
                'freshness' => $this->deadlines($fresh, $stale, $now),
                'atmosphere' => $atmosphere,
                // Display phrase rotation writes state and uses response-anchored trends.
                // Advisory prose is optional; do not publish those unproven claims.
                'summary' => null,
            ];
        }

        return [
            'availability' => $impaired || ! $station['healthy'] || ! $forecastState['healthy'] || $forecast === null ? 'degraded' : 'available',
            'timezone' => $timezone,
            'observation' => $observation,
            'observed_today' => $observed,
            'forecast_today' => $forecast,
            'interpretation' => $interpretation,
        ];
    }

    private function observationTime(Observation $row, CarbonImmutable $now): ?CarbonImmutable
    {
        try {
            $raw = $row->raw;
            $milliseconds = $raw['dateutc'] ?? null;
            if (is_numeric($milliseconds) && is_finite((float) $milliseconds)) {
                $source = CarbonImmutable::createFromTimestampMs((int) $milliseconds, 'UTC');
            } else {
                $text = $raw['date'] ?? null;
                if (! is_string($text) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/iD', $text)) {
                    return null;
                }
                $parsed = new \DateTimeImmutable($text);
                $errors = \DateTimeImmutable::getLastErrors();
                if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) {
                    return null;
                }
                $source = CarbonImmutable::instance($parsed)->utc();
            }
            $stored = CarbonImmutable::instance($row->observed_at)->utc();

            // Reject legacy synthetic-now fallback, mismatched provenance, and future data.
            return $source->lte($now) && $source->startOfMinute()->equalTo($stored) ? $stored : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function refreshState(string $source, CarbonImmutable $now): array
    {
        $status = CollectionStatus::query()->where('source', $source)->first();
        if ($status === null) {
            return ['healthy' => false, 'failed' => false];
        }
        try {
            $success = $status->last_success_at;
            $failure = $status->last_failure_at;
            $failed = $status->consecutive_failures > 0 || ($failure !== null && ($success === null || $failure->gt($success)));

            return [
                'healthy' => ! $failed && $success !== null && $success->lte($now) && ($failure === null || $failure->lte($now)),
                'failed' => $failed && $failure !== null && $failure->lte($now),
            ];
        } catch (\Throwable) {
            return ['healthy' => false, 'failed' => false];
        }
    }

    private function forecast(?ForecastDay $row, array $period, bool $retained, CarbonImmutable $now): ?array
    {
        if ($row === null) {
            return null;
        }
        try {
            if ($row->fetched_at === null) {
                return null;
            }
            $retrieved = CarbonImmutable::instance($row->fetched_at)->utc();
        } catch (\Throwable) {
            return null;
        }
        if ($retrieved->gt($now)) {
            return null;
        }
        $high = $this->temperature($row->getRawOriginal('temperature_high'));
        $low = $this->temperature($row->getRawOriginal('temperature_low'));
        $condition = $this->classification($this->knownCode($row->getRawOriginal('weather_code')), null);
        if (($high === null && $low === null && $condition === null) || ($high !== null && $low !== null && $low > $high)) {
            return null;
        }
        $deadline = $retrieved->addHours(max(0, (int) config('weather.staleness.forecast_stale_hours', 3)));

        return [
            'period' => $period,
            'retrieved_at' => $retrieved->toIso8601String(),
            'freshness' => ['state' => $now->lte($deadline) ? 'fresh' : 'stale', 'fresh_until' => $deadline->toIso8601String()],
            'last_known_good' => $retained,
            'temperature_high_f' => $high,
            'temperature_low_f' => $low,
            'condition' => $condition,
        ];
    }

    private function freshness(CarbonImmutable $source, CarbonImmutable $now): array
    {
        $fresh = max(0, (int) config('weather.staleness.fresh_minutes', 10));
        $stale = max($fresh, (int) config('weather.staleness.stale_minutes', 20));

        return $this->deadlines($source->addMinutes($fresh), $source->addMinutes($stale), $now);
    }

    private function deadlines(CarbonImmutable $fresh, CarbonImmutable $stale, CarbonImmutable $now): array
    {
        return [
            'state' => $now->lte($fresh) ? 'fresh' : ($now->lte($stale) ? 'delayed' : 'stale'),
            'fresh_until' => $fresh->toIso8601String(),
            'stale_after' => $stale->toIso8601String(),
        ];
    }

    private function temperature(mixed $value): ?float
    {
        $number = Number::float($value);

        return $number !== null && $number >= -459.67 ? $number : null;
    }

    private function nonnegative(mixed $value): ?float
    {
        $number = Number::float($value);

        return $number !== null && $number >= 0 ? $number : null;
    }

    private function knownCode(mixed $value): ?int
    {
        $number = Number::float($value);

        return $number !== null && $number === (float) (int) $number && in_array((int) $number, self::CODES, true) ? (int) $number : null;
    }

    private function classification(?int $code, ?float $rain): ?string
    {
        if ($code === null && ($rain === null || $rain <= 0)) {
            return null;
        }
        $kind = AtmosphereModifier::fromCurrent($code, $rain)['kind'];

        return $kind === 'severe-storm' ? 'thunderstorm_with_hail' : str_replace('-', '_', $kind);
    }
}
