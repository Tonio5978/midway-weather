<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Weather\Clients\OpenMeteoClient;
use App\Weather\Data\ForecastData;
use App\Weather\Exceptions\WeatherApiException;
use Illuminate\Support\Facades\Http;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

/**
 * The forecast client is the second (and only other) server-side outbound
 * caller. Open-Meteo is keyless, but the request must still be built correctly
 * and every failure mode must degrade to a clean exception the service layer
 * can catch.
 */
final class OpenMeteoClientTest extends TestCase
{
    use MakesWeatherData;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'weather.latitude' => 33.8595,
            'weather.longitude' => -84.6769,
            'weather.timezone' => 'America/New_York',
            'weather.open_meteo.base_url' => 'https://api.open-meteo.com/v1/forecast',
            'weather.open_meteo.retry_times' => 1,
            'weather.open_meteo.retry_sleep_ms' => 0,
        ]);
    }

    private function client(): OpenMeteoClient
    {
        return app(OpenMeteoClient::class);
    }

    public function test_it_parses_a_successful_forecast_response(): void
    {
        Http::fake(['*' => Http::response($this->openMeteoBody(7), 200)]);

        $forecast = $this->client()->dailyForecast();

        $this->assertInstanceOf(ForecastData::class, $forecast);
        $this->assertCount(7, $forecast->days);
        $this->assertSame(90.0, $forecast->days[0]->temperatureHigh);
        $this->assertSame(70.0, $forecast->days[0]->temperatureLow);
        $this->assertSame(0, $forecast->days[0]->weatherCode);
    }

    public function test_it_requests_the_configured_coordinates_and_daily_fields(): void
    {
        Http::fake(['*' => Http::response($this->openMeteoBody(7), 200)]);

        $this->client()->dailyForecast();

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://api.open-meteo.com/v1/forecast')
                && (float) $request['latitude'] === 33.8595
                && (float) $request['longitude'] === -84.6769
                && str_contains($request['daily'], 'temperature_2m_max')
                && $request['temperature_unit'] === 'fahrenheit';
        });
    }

    public function test_unexpected_status_is_reported(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('Open-Meteo request failed: Unexpected HTTP status 503.');

        $this->client()->dailyForecast();
    }

    public function test_response_missing_daily_data_is_reported(): void
    {
        Http::fake(['*' => Http::response(['latitude' => 33.8], 200)]);

        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('Open-Meteo request failed: Response was missing daily data.');

        $this->client()->dailyForecast();
    }

    public function test_response_with_no_forecast_days_is_reported(): void
    {
        Http::fake(['*' => Http::response(['daily' => ['time' => []]], 200)]);

        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('Open-Meteo request failed: Response contained no forecast days.');

        $this->client()->dailyForecast();
    }

    public function test_connection_failure_is_wrapped(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        $this->expectException(WeatherApiException::class);
        $this->expectExceptionMessage('Open-Meteo request failed:');

        $this->client()->dailyForecast();
    }
}
