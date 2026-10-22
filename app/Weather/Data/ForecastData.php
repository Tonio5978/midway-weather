<?php

declare(strict_types=1);

namespace App\Weather\Data;

use App\Weather\Support\Number;
use Carbon\CarbonImmutable;

/**
 * Immutable collection of forecast days plus the moment it was fetched.
 */
final readonly class ForecastData
{
    /**
     * @param  list<ForecastDayData>  $days
     */
    public function __construct(
        public array $days,
        public CarbonImmutable $fetchedAt,
    ) {}

    /**
     * Build from an Open-Meteo `/forecast` response.
     *
     * Open-Meteo returns daily data as parallel columnar arrays keyed by field,
     * with a shared `daily.time` array of ISO dates. We zip them into per-day
     * DTOs, tolerating missing fields and short/absent arrays.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromOpenMeteo(array $data, ?CarbonImmutable $fetchedAt = null): self
    {
        $fetchedAt ??= CarbonImmutable::now('UTC');

        $daily = is_array($data['daily'] ?? null) ? $data['daily'] : [];
        $times = is_array($daily['time'] ?? null) ? array_values($daily['time']) : [];

        $timezone = is_string($data['timezone'] ?? null) ? $data['timezone'] : 'UTC';

        $days = [];

        foreach ($times as $index => $isoDate) {
            if (! is_string($isoDate) || $isoDate === '') {
                continue;
            }

            try {
                $date = CarbonImmutable::parse($isoDate, $timezone)->startOfDay();
            } catch (\Throwable) {
                continue;
            }

            $days[] = new ForecastDayData(
                date: $date,
                weatherCode: Number::int(self::col($daily, 'weather_code', $index)),
                temperatureHigh: Number::float(self::col($daily, 'temperature_2m_max', $index)),
                temperatureLow: Number::float(self::col($daily, 'temperature_2m_min', $index)),
                precipitationProbability: Number::intInRange(
                    self::col($daily, 'precipitation_probability_max', $index), 0, 100
                ),
                precipitationAmount: Number::float(self::col($daily, 'precipitation_sum', $index)),
                windSpeedMax: Number::float(self::col($daily, 'wind_speed_10m_max', $index)),
                windGustMax: Number::float(self::col($daily, 'wind_gusts_10m_max', $index)),
                sunrise: self::parseTime(self::col($daily, 'sunrise', $index), $timezone),
                sunset: self::parseTime(self::col($daily, 'sunset', $index), $timezone),
                fetchedAt: $fetchedAt,
            );
        }

        return new self($days, $fetchedAt);
    }

    /**
     * Safely read a value from a columnar daily array at an index.
     *
     * @param  array<string, mixed>  $daily
     */
    private static function col(array $daily, string $key, int $index): mixed
    {
        $column = $daily[$key] ?? null;

        if (! is_array($column)) {
            return null;
        }

        return array_values($column)[$index] ?? null;
    }

    private static function parseTime(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            // Open-Meteo returns local wall-clock times; interpret in the
            // response timezone, then store as UTC.
            return CarbonImmutable::parse($value, $timezone)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
