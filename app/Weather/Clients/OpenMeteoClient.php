<?php

declare(strict_types=1);

namespace App\Weather\Clients;

use App\Weather\Data\ForecastData;
use App\Weather\Exceptions\WeatherApiException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Throwable;

/**
 * Focused client for the free, keyless Open-Meteo forecast API.
 *
 * Requests only the daily fields the display needs, in the units the display
 * uses, so no client-side unit conversion is required. Timeouts are always set
 * and retries are conservative.
 */
class OpenMeteoClient
{
    public function __construct(private readonly HttpFactory $http) {}

    /**
     * Fetch the multi-day daily forecast for the configured coordinates.
     *
     * @throws WeatherApiException on request failure or an unusable response.
     */
    public function dailyForecast(): ForecastData
    {
        $query = [
            'latitude' => (float) config('weather.latitude'),
            'longitude' => (float) config('weather.longitude'),
            'timezone' => (string) config('weather.timezone', 'America/New_York'),
            'forecast_days' => (int) config('weather.open_meteo.forecast_days', 7),
            'temperature_unit' => (string) config('weather.open_meteo.temperature_unit', 'fahrenheit'),
            'wind_speed_unit' => (string) config('weather.open_meteo.wind_speed_unit', 'mph'),
            'precipitation_unit' => (string) config('weather.open_meteo.precipitation_unit', 'inch'),
            'daily' => implode(',', [
                'weather_code',
                'temperature_2m_max',
                'temperature_2m_min',
                'precipitation_probability_max',
                'precipitation_sum',
                'wind_speed_10m_max',
                'wind_gusts_10m_max',
                'sunrise',
                'sunset',
            ]),
        ];

        try {
            $response = $this->request()->get((string) config('weather.open_meteo.base_url'), $query);
        } catch (Throwable $e) {
            throw WeatherApiException::forSource('Open-Meteo', $this->safeReason($e));
        }

        if (! $response->successful()) {
            throw WeatherApiException::forSource(
                'Open-Meteo',
                sprintf('Unexpected HTTP status %d.', $response->status())
            );
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['daily']) || ! is_array($data['daily'])) {
            throw WeatherApiException::forSource('Open-Meteo', 'Response was missing daily data.');
        }

        $forecast = ForecastData::fromOpenMeteo($data);

        if ($forecast->days === []) {
            throw WeatherApiException::forSource('Open-Meteo', 'Response contained no forecast days.');
        }

        return $forecast;
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->acceptJson()
            ->timeout((int) config('weather.open_meteo.timeout', 10))
            ->connectTimeout((int) config('weather.open_meteo.connect_timeout', 5))
            ->retry(
                (int) config('weather.open_meteo.retry_times', 2),
                (int) config('weather.open_meteo.retry_sleep_ms', 1500),
                throw: false,
            );
    }

    private function safeReason(Throwable $e): string
    {
        $class = class_basename($e);

        return match (true) {
            str_contains(strtolower($e->getMessage()), 'timed out') => 'Request timed out.',
            str_contains(strtolower($e->getMessage()), 'could not resolve host') => 'Network unavailable.',
            str_contains(strtolower($e->getMessage()), 'connection') => 'Could not connect to Open-Meteo.',
            default => sprintf('%s while contacting Open-Meteo.', $class),
        };
    }
}
