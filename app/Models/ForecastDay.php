<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single day of modeled forecast from Open-Meteo.
 *
 * @property Carbon $date
 * @property int|null $weather_code
 * @property float|null $temperature_high
 * @property float|null $temperature_low
 * @property int|null $precipitation_probability
 * @property float|null $precipitation_amount
 * @property float|null $wind_speed_max
 * @property float|null $wind_gust_max
 * @property Carbon|null $sunrise
 * @property Carbon|null $sunset
 * @property Carbon $fetched_at
 */
class ForecastDay extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            // Store as a pure calendar date (Y-m-d). A bare `date` cast would
            // serialize to `Y-m-d 00:00:00`, which then never matches the
            // `Y-m-d` key used by updateOrCreate() — breaking the hourly
            // forecast refresh with a UNIQUE violation on the second write.
            'date' => 'date:Y-m-d',
            'weather_code' => 'integer',
            'temperature_high' => 'float',
            'temperature_low' => 'float',
            'precipitation_probability' => 'integer',
            'precipitation_amount' => 'float',
            'wind_speed_max' => 'float',
            'wind_gust_max' => 'float',
            'sunrise' => 'datetime',
            'sunset' => 'datetime',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * Forecast rows on or after today, in date order.
     */
    public function scopeUpcoming(Builder $query, string $fromDate): Builder
    {
        return $query->whereDate('date', '>=', $fromDate)->orderBy('date');
    }
}
