<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single detailed station reading.
 *
 * @property Carbon $observed_at
 * @property float|null $temperature
 * @property float|null $feels_like
 * @property float|null $dew_point
 * @property int|null $humidity
 * @property float|null $wind_speed
 * @property float|null $wind_gust
 * @property int|null $wind_direction
 * @property float|null $wind_speed_avg_2m
 * @property int|null $wind_direction_avg_2m
 * @property float|null $wind_speed_avg_10m
 * @property int|null $wind_direction_avg_10m
 * @property float|null $max_daily_gust
 * @property float|null $pressure_relative
 * @property float|null $pressure_absolute
 * @property float|null $rain_hourly
 * @property float|null $rain_daily
 * @property float|null $rain_24h
 * @property float|null $rain_weekly
 * @property float|null $rain_monthly
 * @property float|null $rain_event
 * @property float|null $uv_index
 * @property float|null $solar_radiation
 * @property float|null $indoor_temperature
 * @property int|null $indoor_humidity
 * @property bool|null $battery_outdoor_ok
 * @property bool|null $battery_indoor_ok
 * @property string|null $device_mac
 * @property string|null $station_tz
 * @property array<string, mixed>|null $raw
 */
class Observation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'temperature' => 'float',
            'feels_like' => 'float',
            'dew_point' => 'float',
            'humidity' => 'integer',
            'wind_speed' => 'float',
            'wind_gust' => 'float',
            'wind_direction' => 'integer',
            'wind_speed_avg_2m' => 'float',
            'wind_direction_avg_2m' => 'integer',
            'wind_speed_avg_10m' => 'float',
            'wind_direction_avg_10m' => 'integer',
            'max_daily_gust' => 'float',
            'pressure_relative' => 'float',
            'pressure_absolute' => 'float',
            'rain_hourly' => 'float',
            'rain_daily' => 'float',
            'rain_24h' => 'float',
            'rain_weekly' => 'float',
            'rain_monthly' => 'float',
            'rain_event' => 'float',
            'uv_index' => 'float',
            'solar_radiation' => 'float',
            'indoor_temperature' => 'float',
            'indoor_humidity' => 'integer',
            'battery_outdoor_ok' => 'boolean',
            'battery_indoor_ok' => 'boolean',
            'raw' => 'array',
        ];
    }

    /**
     * Most recent observations first.
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('observed_at');
    }
}
