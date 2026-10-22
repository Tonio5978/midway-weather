<?php

declare(strict_types=1);

namespace App\Weather\Services;

use App\Models\Observation;
use Carbon\CarbonImmutable;

/**
 * Prunes detailed observations older than the configured retention window.
 *
 * Only the detailed `observations` table is pruned. Persisted daily summaries
 * and forecast rows are deliberately left intact so long-term context survives
 * even after the raw readings that produced them are gone.
 */
class RetentionService
{
    /**
     * Delete observations older than the retention cutoff.
     *
     * @return array{deleted: int, cutoff: string, retention_days: int}
     */
    public function prune(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $retentionDays = max(1, (int) config('weather.retention_days', 10));
        $cutoff = $now->subDays($retentionDays);

        $deleted = Observation::query()
            ->where('observed_at', '<', $cutoff)
            ->delete();

        return [
            'deleted' => $deleted,
            'cutoff' => $cutoff->toIso8601String(),
            'retention_days' => $retentionDays,
        ];
    }
}
