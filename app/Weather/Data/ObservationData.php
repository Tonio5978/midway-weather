<?php

declare(strict_types=1);

namespace App\Weather\Data;

use App\Weather\Support\Number;
use Carbon\CarbonImmutable;

/**
 * Immutable, normalized representation of a single Ambient Weather observation.
 *
 * This is the boundary between the messy provider payload and the rest of the
 * app: raw keys, string numbers, and missing fields are resolved here once, so
 * everything downstream works with clean typed values. The original payload is
 * preserved verbatim in {@see $raw} for persistence and future fields.
 */
final readonly class ObservationData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public CarbonImmutable $observedAt,
        public ?float $temperature,
        public ?float $feelsLike,
        public ?float $dewPoint,
        public ?int $humidity,
        public ?float $windSpeed,
        public ?float $windGust,
        public ?int $windDirection,
        public ?float $windSpeedAvg2m,
        public ?int $windDirectionAvg2m,
        public ?float $windSpeedAvg10m,
        public ?int $windDirectionAvg10m,
        public ?float $maxDailyGust,
        public ?float $pressureRelative,
        public ?float $pressureAbsolute,
        public ?float $rainHourly,
        public ?float $rainDaily,
        public ?float $rain24h,
        public ?float $rainWeekly,
        public ?float $rainMonthly,
        public ?float $rainEvent,
        public ?float $uvIndex,
        public ?float $solarRadiation,
        public ?float $indoorTemperature,
        public ?int $indoorHumidity,
        public ?bool $batteryOutdoorOk,
        public ?bool $batteryIndoorOk,
        public ?string $deviceMac,
        public ?string $stationTz,
        public array $raw,
    ) {}

    /**
     * Build from a raw Ambient `lastData` array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromAmbient(array $data, ?string $deviceMac = null): self
    {
        return new self(
            observedAt: self::resolveObservedAt($data),
            temperature: Number::float($data['tempf'] ?? null),
            feelsLike: Number::float($data['feelsLike'] ?? null),
            dewPoint: Number::float($data['dewPoint'] ?? null),
            humidity: Number::intInRange($data['humidity'] ?? null, 0, 100),
            windSpeed: Number::float($data['windspeedmph'] ?? null),
            windGust: Number::float($data['windgustmph'] ?? null),
            windDirection: Number::intInRange($data['winddir'] ?? null, 0, 360),
            windSpeedAvg2m: Number::float($data['windspdmph_avg2m'] ?? null),
            windDirectionAvg2m: Number::intInRange($data['winddir_avg2m'] ?? null, 0, 360),
            windSpeedAvg10m: Number::float($data['windspdmph_avg10m'] ?? null),
            windDirectionAvg10m: Number::intInRange($data['winddir_avg10m'] ?? null, 0, 360),
            maxDailyGust: Number::float($data['maxdailygust'] ?? null),
            pressureRelative: Number::float($data['baromrelin'] ?? null),
            pressureAbsolute: Number::float($data['baromabsin'] ?? null),
            rainHourly: Number::float($data['hourlyrainin'] ?? null),
            rainDaily: Number::float($data['dailyrainin'] ?? null),
            rain24h: Number::float($data['24hourrainin'] ?? null),
            rainWeekly: Number::float($data['weeklyrainin'] ?? null),
            rainMonthly: Number::float($data['monthlyrainin'] ?? null),
            rainEvent: Number::float($data['eventrainin'] ?? null),
            uvIndex: Number::float($data['uv'] ?? null),
            solarRadiation: Number::float($data['solarradiation'] ?? null),
            indoorTemperature: Number::float($data['tempinf'] ?? null),
            indoorHumidity: Number::intInRange($data['humidityin'] ?? null, 0, 100),
            batteryOutdoorOk: Number::batteryOk($data['battout'] ?? null),
            batteryIndoorOk: Number::batteryOk($data['battin'] ?? null),
            deviceMac: self::stringOrNull($data['macAddress'] ?? $deviceMac),
            stationTz: self::stringOrNull($data['tz'] ?? null),
            raw: $data,
        );
    }

    /**
     * Resolve the observation time. Ambient's `dateutc` is milliseconds since
     * the epoch (authoritative); fall back to the ISO `date` string, and only
     * as a last resort to "now" so an observation is never silently dropped.
     *
     * @param  array<string, mixed>  $data
     */
    private static function resolveObservedAt(array $data): CarbonImmutable
    {
        $dateUtc = $data['dateutc'] ?? null;

        if (is_numeric($dateUtc)) {
            // Milliseconds since epoch.
            return CarbonImmutable::createFromTimestampMs((int) $dateUtc, 'UTC');
        }

        $dateString = $data['date'] ?? null;

        if (is_string($dateString) && $dateString !== '') {
            try {
                return CarbonImmutable::parse($dateString)->utc();
            } catch (\Throwable) {
                // fall through
            }
        }

        return CarbonImmutable::now('UTC');
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Column-ready attributes for persisting to the `observations` table.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'observed_at' => $this->observedAt,
            'temperature' => $this->temperature,
            'feels_like' => $this->feelsLike,
            'dew_point' => $this->dewPoint,
            'humidity' => $this->humidity,
            'wind_speed' => $this->windSpeed,
            'wind_gust' => $this->windGust,
            'wind_direction' => $this->windDirection,
            'wind_speed_avg_2m' => $this->windSpeedAvg2m,
            'wind_direction_avg_2m' => $this->windDirectionAvg2m,
            'wind_speed_avg_10m' => $this->windSpeedAvg10m,
            'wind_direction_avg_10m' => $this->windDirectionAvg10m,
            'max_daily_gust' => $this->maxDailyGust,
            'pressure_relative' => $this->pressureRelative,
            'pressure_absolute' => $this->pressureAbsolute,
            'rain_hourly' => $this->rainHourly,
            'rain_daily' => $this->rainDaily,
            'rain_24h' => $this->rain24h,
            'rain_weekly' => $this->rainWeekly,
            'rain_monthly' => $this->rainMonthly,
            'rain_event' => $this->rainEvent,
            'uv_index' => $this->uvIndex,
            'solar_radiation' => $this->solarRadiation,
            'indoor_temperature' => $this->indoorTemperature,
            'indoor_humidity' => $this->indoorHumidity,
            'battery_outdoor_ok' => $this->batteryOutdoorOk,
            'battery_indoor_ok' => $this->batteryIndoorOk,
            'device_mac' => $this->deviceMac,
            'station_tz' => $this->stationTz,
            'raw' => $this->raw,
        ];
    }
}
