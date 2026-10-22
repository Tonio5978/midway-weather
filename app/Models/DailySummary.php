<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Persisted per-day aggregate of station observations.
 *
 * @property Carbon $date
 * @property float|null $temperature_high
 * @property float|null $temperature_low
 * @property float|null $max_wind_gust
 * @property float|null $total_rain
 * @property float|null $max_uv
 * @property float|null $max_solar_radiation
 * @property float|null $min_pressure
 * @property float|null $max_pressure
 * @property int $observation_count
 */
class DailySummary extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            // Store as a pure calendar date (Y-m-d). A bare `date` cast would
            // serialize to `Y-m-d 00:00:00`, which then never matches the
            // `Y-m-d` key used by updateOrCreate() — breaking idempotent
            // rebuilds with a UNIQUE violation on the second write.
            'date' => 'date:Y-m-d',
            'temperature_high' => 'float',
            'temperature_low' => 'float',
            'max_wind_gust' => 'float',
            'total_rain' => 'float',
            'max_uv' => 'float',
            'max_solar_radiation' => 'float',
            'min_pressure' => 'float',
            'max_pressure' => 'float',
            'observation_count' => 'integer',
        ];
    }
}
