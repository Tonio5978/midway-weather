<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Health record for a single collection source (e.g. "ambient", "forecast").
 *
 * @property string $source
 * @property Carbon|null $last_success_at
 * @property Carbon|null $last_failure_at
 * @property int $consecutive_failures
 * @property string|null $last_error
 */
class CollectionStatus extends Model
{
    public const SOURCE_AMBIENT = 'ambient';

    public const SOURCE_FORECAST = 'forecast';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'consecutive_failures' => 'integer',
        ];
    }

    /**
     * Record a successful poll, resetting the failure counter.
     */
    public static function recordSuccess(string $source): self
    {
        $status = static::firstOrNew(['source' => $source]);
        $status->last_success_at = now();
        $status->consecutive_failures = 0;
        $status->last_error = null;
        $status->save();

        return $status;
    }

    /**
     * Record a failed poll with a short, display-safe message.
     */
    public static function recordFailure(string $source, string $safeMessage): self
    {
        $status = static::firstOrNew(['source' => $source]);
        $status->last_failure_at = now();
        $status->consecutive_failures = (int) $status->consecutive_failures + 1;
        // Keep the stored message short and free of URLs/keys.
        $status->last_error = mb_substr($safeMessage, 0, 250);
        $status->save();

        return $status;
    }
}
