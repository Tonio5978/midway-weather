<?php

declare(strict_types=1);

namespace App\Weather\Clients;

use App\Weather\Data\ObservationData;
use App\Weather\Exceptions\WeatherApiException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Throwable;

/**
 * Focused client for the Ambient Weather REST API.
 *
 * Uses the `/devices` endpoint, which returns each device together with its
 * most up-to-date `lastData` reading (the source-of-truth for live display).
 * The historical `/devices/{mac}` endpoint is intentionally not used here: it
 * is delayed and grouped, whereas the display needs the freshest observation.
 *
 * Credentials are read from config (never logged, never sent to the browser).
 * Timeouts are always set and retries are conservative to respect the
 * documented rate limits.
 */
class AmbientWeatherClient
{
    public function __construct(private readonly HttpFactory $http) {}

    /**
     * Fetch the latest observation for the configured device.
     *
     * @throws WeatherApiException when credentials are missing, the request
     *                             fails, or no usable data is returned.
     */
    public function latestObservation(): ObservationData
    {
        $apiKey = (string) config('weather.ambient.api_key');
        $applicationKey = (string) config('weather.ambient.application_key');
        $deviceMac = config('weather.ambient.device_mac');
        $deviceMac = is_string($deviceMac) && $deviceMac !== '' ? $deviceMac : null;

        if ($apiKey === '' || $applicationKey === '') {
            throw WeatherApiException::forSource('Ambient', 'API credentials are not configured.');
        }

        try {
            $response = $this->request()->get('/devices', [
                'apiKey' => $apiKey,
                'applicationKey' => $applicationKey,
            ]);
        } catch (Throwable $e) {
            // Wrap transport-level errors (timeouts, DNS, connection refused).
            throw WeatherApiException::forSource('Ambient', $this->safeReason($e));
        }

        if ($response->status() === 429) {
            throw WeatherApiException::forSource('Ambient', 'Rate limit exceeded (HTTP 429).');
        }

        if (! $response->successful()) {
            throw WeatherApiException::forSource(
                'Ambient',
                sprintf('Unexpected HTTP status %d.', $response->status())
            );
        }

        $devices = $response->json();

        if (! is_array($devices) || $devices === []) {
            throw WeatherApiException::forSource('Ambient', 'Response contained no devices.');
        }

        $device = $this->selectDevice($devices, $deviceMac);

        if ($device === null) {
            throw WeatherApiException::forSource(
                'Ambient',
                'Configured device MAC was not found in the account.'
            );
        }

        $lastData = $device['lastData'] ?? null;

        if (! is_array($lastData) || $lastData === []) {
            throw WeatherApiException::forSource('Ambient', 'Device returned no lastData.');
        }

        // Attach the device MAC (top-level on /devices entries) so the
        // observation records which station it came from.
        $mac = is_string($device['macAddress'] ?? null) ? $device['macAddress'] : $deviceMac;

        return ObservationData::fromAmbient($lastData, $mac);
    }

    /**
     * Choose the device matching the configured MAC, or the first device when
     * no MAC is configured (single-station accounts).
     *
     * @param  array<int, mixed>  $devices
     * @return array<string, mixed>|null
     */
    private function selectDevice(array $devices, ?string $deviceMac): ?array
    {
        if ($deviceMac === null) {
            $first = $devices[0] ?? null;

            return is_array($first) ? $first : null;
        }

        $target = strtolower($deviceMac);

        foreach ($devices as $device) {
            if (! is_array($device)) {
                continue;
            }

            $mac = $device['macAddress'] ?? null;

            if (is_string($mac) && strtolower($mac) === $target) {
                return $device;
            }
        }

        return null;
    }

    private function request(): PendingRequest
    {
        $baseUrl = rtrim((string) config('weather.ambient.base_url'), '/');

        return $this->http
            ->baseUrl($baseUrl)
            ->acceptJson()
            ->timeout((int) config('weather.ambient.timeout', 10))
            ->connectTimeout((int) config('weather.ambient.connect_timeout', 5))
            ->retry(
                (int) config('weather.ambient.retry_times', 2),
                (int) config('weather.ambient.retry_sleep_ms', 1500),
                // Do not retry rate-limit responses; back off instead.
                throw: false,
            );
    }

    /**
     * Produce a short, credential-free reason string from an exception.
     */
    private function safeReason(Throwable $e): string
    {
        $class = class_basename($e);

        return match (true) {
            str_contains(strtolower($e->getMessage()), 'timed out') => 'Request timed out.',
            str_contains(strtolower($e->getMessage()), 'could not resolve host') => 'Network unavailable.',
            str_contains(strtolower($e->getMessage()), 'connection') => 'Could not connect to Ambient.',
            default => sprintf('%s while contacting Ambient.', $class),
        };
    }
}
