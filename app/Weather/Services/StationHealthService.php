<?php

declare(strict_types=1);

namespace App\Weather\Services;

use App\Models\CollectionStatus;
use App\Models\Observation;
use Carbon\CarbonImmutable;

/**
 * Derives the freshness/health state of the station and forecast feeds from
 * stored data. Thresholds come from config/weather.php so they can be tuned
 * without code changes.
 */
class StationHealthService
{
    public const FRESH = 'fresh';

    public const DELAYED = 'delayed';

    public const STALE = 'stale';

    public const NO_DATA = 'no_data';

    /**
     * Classify how current the latest observation is.
     */
    public function observationFreshness(?Observation $latest, ?CarbonImmutable $now = null): string
    {
        if ($latest === null) {
            return self::NO_DATA;
        }

        $now ??= CarbonImmutable::now('UTC');
        $ageMinutes = $latest->observed_at->diffInMinutes($now);

        $fresh = (int) config('weather.staleness.fresh_minutes', 10);
        $stale = (int) config('weather.staleness.stale_minutes', 20);

        return match (true) {
            $ageMinutes <= $fresh => self::FRESH,
            $ageMinutes <= $stale => self::DELAYED,
            default => self::STALE,
        };
    }

    /**
     * Age of the latest observation in whole minutes, or null when no data.
     */
    public function observationAgeMinutes(?Observation $latest, ?CarbonImmutable $now = null): ?int
    {
        if ($latest === null) {
            return null;
        }

        $now ??= CarbonImmutable::now('UTC');

        return (int) floor($latest->observed_at->diffInMinutes($now));
    }

    /**
     * Whether the forecast is older than the configured stale window.
     */
    public function forecastIsStale(?CarbonImmutable $fetchedAt, ?CarbonImmutable $now = null): bool
    {
        if ($fetchedAt === null) {
            return true;
        }

        $now ??= CarbonImmutable::now('UTC');
        $staleHours = (int) config('weather.staleness.forecast_stale_hours', 3);

        return $fetchedAt->diffInHours($now) > $staleHours;
    }

    /**
     * Build a display-safe health summary for a collection source.
     *
     * @return array{
     *     last_success_at: string|null,
     *     last_failure_at: string|null,
     *     consecutive_failures: int,
     *     last_error: string|null,
     *     healthy: bool
     * }
     */
    public function statusSummary(string $source): array
    {
        $status = CollectionStatus::query()->where('source', $source)->first();

        if ($status === null) {
            return [
                'last_success_at' => null,
                'last_failure_at' => null,
                'consecutive_failures' => 0,
                'last_error' => null,
                'healthy' => false,
            ];
        }

        return [
            'last_success_at' => $status->last_success_at?->toIso8601String(),
            'last_failure_at' => $status->last_failure_at?->toIso8601String(),
            'consecutive_failures' => $status->consecutive_failures,
            'last_error' => $status->last_error,
            // Healthy when the most recent poll succeeded.
            'healthy' => $status->consecutive_failures === 0 && $status->last_success_at !== null,
        ];
    }
}
