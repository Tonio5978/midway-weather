<?php

declare(strict_types=1);

namespace App\Weather\Data;

use Carbon\CarbonImmutable;

/**
 * Immutable, normalized representation of a single forecast day from Open-Meteo.
 */
final readonly class ForecastDayData
{
    public function __construct(
        public CarbonImmutable $date,
        public ?int $weatherCode,
        public ?float $temperatureHigh,
        public ?float $temperatureLow,
        public ?int $precipitationProbability,
        public ?float $precipitationAmount,
        public ?float $windSpeedMax,
        public ?float $windGustMax,
        public ?CarbonImmutable $sunrise,
        public ?CarbonImmutable $sunset,
        public CarbonImmutable $fetchedAt,
    ) {}

    /**
     * Column-ready attributes for upserting into the `forecast_days` table.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'weather_code' => $this->weatherCode,
            'temperature_high' => $this->temperatureHigh,
            'temperature_low' => $this->temperatureLow,
            'precipitation_probability' => $this->precipitationProbability,
            'precipitation_amount' => $this->precipitationAmount,
            'wind_speed_max' => $this->windSpeedMax,
            'wind_gust_max' => $this->windGustMax,
            'sunrise' => $this->sunrise,
            'sunset' => $this->sunset,
            'fetched_at' => $this->fetchedAt,
        ];
    }
}
