<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\ForecastDay;
use App\Models\Observation;
use Carbon\CarbonImmutable;

/**
 * Test helpers for building station data without model factories.
 *
 * The app ships no user-facing factories (there are no user accounts), so these
 * small builders give tests a concise, readable way to seed the observation and
 * forecast tables with sensible defaults that individual tests override.
 */
trait MakesWeatherData
{
    /**
     * Insert a single observation at the given instant, with realistic defaults
     * that tests override per-field.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function storeObservation(CarbonImmutable $observedAt, array $attributes = []): Observation
    {
        return Observation::query()->create(array_merge([
            'observed_at' => $observedAt,
            'temperature' => 80.0,
            'feels_like' => 82.0,
            'dew_point' => 68.0,
            'humidity' => 60,
            'wind_speed' => 5.0,
            'wind_gust' => 9.0,
            'wind_direction' => 180,
            'wind_speed_avg_10m' => 5.0,
            'wind_direction_avg_10m' => 180,
            'max_daily_gust' => 14.0,
            'pressure_relative' => 30.00,
            'pressure_absolute' => 28.75,
            'rain_hourly' => 0.0,
            'rain_daily' => 0.0,
            'uv_index' => 4.0,
            'solar_radiation' => 400.0,
            'indoor_temperature' => 72.0,
            'indoor_humidity' => 48,
            'battery_outdoor_ok' => true,
            'battery_indoor_ok' => true,
            'device_mac' => 'AA:BB:CC:DD:EE:FF',
            'station_tz' => 'America/New_York',
        ], $attributes));
    }

    /**
     * Insert a single forecast day.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function storeForecastDay(CarbonImmutable $date, array $attributes = []): ForecastDay
    {
        return ForecastDay::query()->create(array_merge([
            'date' => $date->toDateString(),
            'weather_code' => 0,
            'temperature_high' => 90.0,
            'temperature_low' => 70.0,
            'precipitation_probability' => 10,
            'precipitation_amount' => 0.0,
            'wind_speed_max' => 10.0,
            'wind_gust_max' => 18.0,
            'sunrise' => $date->setTime(6, 43),
            'sunset' => $date->setTime(20, 48),
            'fetched_at' => $date->setTime(0, 5),
        ], $attributes));
    }

    /**
     * A minimal, valid Ambient `/devices` response body (one device).
     *
     * @param  array<string, mixed>  $lastData
     * @return array<int, array<string, mixed>>
     */
    protected function ambientDevicesBody(array $lastData = [], string $mac = 'AA:BB:CC:DD:EE:FF'): array
    {
        return [[
            'macAddress' => $mac,
            'info' => ['name' => 'Test Station'],
            'lastData' => array_merge([
                'dateutc' => 1_700_000_000_000,
                'date' => '2023-11-14T22:13:20.000Z',
                'tempf' => 72.4,
                'humidity' => 55,
                'baromrelin' => 30.02,
                'windspeedmph' => 4.0,
                'winddir' => 200,
                'hourlyrainin' => 0.0,
                'dailyrainin' => 0.1,
                'uv' => 3,
                'solarradiation' => 210.5,
                'battout' => 1,
                'battin' => 1,
                'tz' => 'America/New_York',
            ], $lastData),
        ]];
    }

    /**
     * A minimal, valid Open-Meteo daily forecast response body.
     *
     * @return array<string, mixed>
     */
    protected function openMeteoBody(int $days = 7): array
    {
        $time = [];
        $codes = [];
        $max = [];
        $min = [];
        $pop = [];
        $sum = [];
        $windMax = [];
        $gustMax = [];
        $sunrise = [];
        $sunset = [];

        // Anchor the horizon on "today" in the station timezone so the first
        // day is never pruned as past. Assertions here are index-based (values
        // and counts), never tied to a specific calendar date.
        $base = CarbonImmutable::now('America/New_York')->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            $date = $base->addDays($i);
            $time[] = $date->toDateString();
            $codes[] = 0;
            $max[] = 90 + $i;
            $min[] = 70 + $i;
            $pop[] = 10 * $i;
            $sum[] = 0.0;
            $windMax[] = 10;
            $gustMax[] = 18;
            $sunrise[] = $date->setTime(6, 43)->format('Y-m-d\TH:i');
            $sunset[] = $date->setTime(20, 48)->format('Y-m-d\TH:i');
        }

        return [
            'latitude' => 33.86,
            'longitude' => -84.68,
            'timezone' => 'America/New_York',
            'daily' => [
                'time' => $time,
                'weather_code' => $codes,
                'temperature_2m_max' => $max,
                'temperature_2m_min' => $min,
                'precipitation_probability_max' => $pop,
                'precipitation_sum' => $sum,
                'wind_speed_10m_max' => $windMax,
                'wind_gusts_10m_max' => $gustMax,
                'sunrise' => $sunrise,
                'sunset' => $sunset,
            ],
        ];
    }
}
