<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Weather\Clients\AmbientWeatherClient;
use App\Weather\Data\ObservationData;
use App\Weather\Exceptions\WeatherApiException;
use Illuminate\Support\Facades\Http;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

/**
 * The Ambient client is one of only two places the app is allowed to reach the
 * internet, and it runs strictly server-side. These tests fake the HTTP layer
 * (so no real network call is ever made) and pin the exact, credential-free
 * error messages the client surfaces for each failure mode.
 */
final class AmbientWeatherClientTest extends TestCase
{
    use MakesWeatherData;

    protected function setUp(): void
    {
        parent::setUp();

        // Valid credentials + fast, no-retry behavior for deterministic tests.
        config([
            'weather.ambient.api_key' => 'test-api-key',
            'weather.ambient.application_key' => 'test-app-key',
            'weather.ambient.device_mac' => 'AA:BB:CC:DD:EE:FF',
            'weather.ambient.base_url' => 'https://rt.ambientweather.net/v1',
            'weather.ambient.retry_times' => 1,
            'weather.ambient.retry_sleep_ms' => 0,
        ]);
    }

    private function client(): AmbientWeatherClient
    {
        return app(AmbientWeatherClient::class);
    }

    public function test_it_parses_a_successful_response_into_observation_data(): void
    {
        Http::fake([
            '*' => Http::response($this->ambientDevicesBody([
                'tempf' => 72.4,
                'humidity' => 55,
                'winddir' => 200,
                'baromrelin' => 30.02,
                'battout' => 1,
            ]), 200),
        ]);

        $observation = $this->client()->latestObservation();

        $this->assertInstanceOf(ObservationData::class, $observation);
        $this->assertSame(72.4, $observation->temperature);
        $this->assertSame(55, $observation->humidity);
        $this->assertSame(200, $observation->windDirection);
        $this->assertSame(30.02, $observation->pressureRelative);
        $this->assertTrue($observation->batteryOutdoorOk);
        $this->assertSame('AA:BB:CC:DD:EE:FF', $observation->deviceMac);
    }

    public function test_it_sends_credentials_as_query_parameters_server_side(): void
    {
        Http::fake(['*' => Http::response($this->ambientDevicesBody(), 200)]);

        $this->client()->latestObservation();

        // The keys travel server-to-server as query params; verify the request
        // shape without ever exposing them to a browser.
        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://rt.ambientweather.net/v1/devices')
                && $request['apiKey'] === 'test-api-key'
                && $request['applicationKey'] === 'test-app-key';
        });
    }

    public function test_missing_credentials_throw_before_any_request_is_made(): void
    {
        config(['weather.ambient.api_key' => '', 'weather.ambient.application_key' => '']);
        Http::fake();

        try {
            $this->client()->latestObservation();
            $this->fail('Expected WeatherApiException was not thrown.');
        } catch (WeatherApiException $e) {
            $this->assertSame('Ambient request failed: API credentials are not configured.', $e->getMessage());
        }

        // Critically: with no credentials we must not touch the network at all.
        Http::assertNothingSent();
    }

    public function test_rate_limit_response_is_reported_clearly(): void
    {
        Http::fake(['*' => Http::response('', 429)]);

        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('Ambient request failed: Rate limit exceeded (HTTP 429).');

        $this->client()->latestObservation();
    }

    public function test_unexpected_status_is_reported_with_the_code(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('Ambient request failed: Unexpected HTTP status 500.');

        $this->client()->latestObservation();
    }

    public function test_empty_device_list_is_reported(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('Ambient request failed: Response contained no devices.');

        $this->client()->latestObservation();
    }

    public function test_configured_mac_not_present_is_reported(): void
    {
        config(['weather.ambient.device_mac' => '11:22:33:44:55:66']);
        Http::fake(['*' => Http::response($this->ambientDevicesBody(mac: 'AA:BB:CC:DD:EE:FF'), 200)]);

        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('Ambient request failed: Configured device MAC was not found in the account.');

        $this->client()->latestObservation();
    }

    public function test_missing_last_data_is_reported(): void
    {
        Http::fake(['*' => Http::response([[
            'macAddress' => 'AA:BB:CC:DD:EE:FF',
            'lastData' => [],
        ]], 200)]);

        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('Ambient request failed: Device returned no lastData.');

        $this->client()->latestObservation();
    }

    public function test_it_selects_the_first_device_when_no_mac_is_configured(): void
    {
        config(['weather.ambient.device_mac' => null]);
        Http::fake(['*' => Http::response([
            ['macAddress' => '00:00:00:00:00:01', 'lastData' => ['tempf' => 61.0]],
            ['macAddress' => '00:00:00:00:00:02', 'lastData' => ['tempf' => 99.0]],
        ], 200)]);

        $observation = $this->client()->latestObservation();

        $this->assertSame(61.0, $observation->temperature);
        $this->assertSame('00:00:00:00:00:01', $observation->deviceMac);
    }

    public function test_connection_failure_is_wrapped_without_leaking_credentials(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        try {
            $this->client()->latestObservation();
            $this->fail('Expected WeatherApiException was not thrown.');
        } catch (WeatherApiException $e) {
            // Wrapped, prefixed, and free of the API keys.
            $this->assertStringStartsWith('Ambient request failed:', $e->getMessage());
            $this->assertStringNotContainsString('test-api-key', $e->getMessage());
            $this->assertStringNotContainsString('test-app-key', $e->getMessage());
        }
    }
}
