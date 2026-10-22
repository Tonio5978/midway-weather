<?php

declare(strict_types=1);

namespace App\Weather\Services;

use App\Models\Observation;
use App\Weather\Data\ObservationData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Reads and writes station observations.
 *
 * Persistence is idempotent: observations are keyed by their (minute-rounded)
 * `observed_at`, which is unique, so re-polling the same reading updates the
 * existing row rather than creating a duplicate.
 */
class ObservationRepository
{
    /**
     * Persist a normalized observation, de-duplicating on `observed_at`.
     *
     * Returns the persisted model and whether it was newly created.
     *
     * @return array{observation: Observation, created: bool}
     */
    public function store(ObservationData $data): array
    {
        $attributes = $data->toAttributes();

        // Round to the minute to match Ambient's server-side rounding and make
        // dedup robust against sub-minute jitter in repeated polls.
        $observedAt = $data->observedAt->startOfMinute();
        $attributes['observed_at'] = $observedAt;

        $existing = Observation::query()
            ->where('observed_at', $observedAt)
            ->first();

        if ($existing !== null) {
            $existing->fill($attributes)->save();

            return ['observation' => $existing, 'created' => false];
        }

        $observation = Observation::create($attributes);

        return ['observation' => $observation, 'created' => true];
    }

    /**
     * The most recent observation, or null when none has been collected.
     */
    public function latest(): ?Observation
    {
        return Observation::query()->latestFirst()->first();
    }

    /**
     * The observation nearest to a target time, within a tolerance window.
     * Used by trend calculations to find "the reading ~1 hour ago".
     */
    public function nearest(CarbonImmutable $target, int $toleranceMinutes): ?Observation
    {
        $from = $target->subMinutes($toleranceMinutes);
        $to = $target->addMinutes($toleranceMinutes);

        return Observation::query()
            ->whereBetween('observed_at', [$from, $to])
            // Order by absolute distance from the target timestamp.
            ->orderByRaw('ABS(strftime(\'%s\', observed_at) - ?)', [$target->getTimestamp()])
            ->first();
    }

    /**
     * Recent observations within the given number of hours, oldest first.
     * Optionally capped to a maximum number of evenly-taken points for
     * sparklines.
     *
     * @return Collection<int, Observation>
     */
    public function recent(int $hours, ?int $maxPoints = null): Collection
    {
        $since = CarbonImmutable::now('UTC')->subHours($hours);

        $observations = Observation::query()
            ->where('observed_at', '>=', $since)
            ->orderBy('observed_at')
            ->get();

        if ($maxPoints === null || $observations->count() <= $maxPoints) {
            return $observations;
        }

        return $this->downsample($observations, $maxPoints);
    }

    /**
     * Evenly sample a collection down to at most $maxPoints, always keeping the
     * most recent point so the sparkline ends at "now".
     *
     * @param  Collection<int, Observation>  $observations
     * @return Collection<int, Observation>
     */
    private function downsample(Collection $observations, int $maxPoints): Collection
    {
        $count = $observations->count();

        if ($maxPoints < 1 || $count === 0) {
            return new Collection;
        }

        $step = ($count - 1) / max(1, $maxPoints - 1);
        $sampled = new Collection;

        for ($i = 0; $i < $maxPoints; $i++) {
            $index = (int) round($i * $step);
            $sampled->push($observations[$index]);
        }

        return $sampled->unique(fn (Observation $o) => $o->getKey())->values();
    }
}
