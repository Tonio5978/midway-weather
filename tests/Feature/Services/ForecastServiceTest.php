<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\CollectionStatus;
use App\Models\ForecastDay;
use App\Weather\Services\ForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

final class ForecastServiceTest extends TestCase
{
    use MakesWeatherData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'weather.timezone' => 'America/New_York',
            'weather.open_meteo.base_url' => 'https://api.open-meteo.com/v1/forecast',
            'weather.open_meteo.retry_times' => 1,
            'weather.open_meteo.retry_sleep_ms' => 0,
        ]);
    }

    private function service(): ForecastService
    {
        return app(ForecastService::class);
    }

    public function test_a_successful_refresh_upserts_days_and_records_success(): void
    {
        Http::fake(['*' => Http::response($this->openMeteoBody(7), 200)]);

        $result = $this->service()->refresh();

        $this->assertTrue($result['success']);
        $this->assertSame(7, $result['days']);
        $this->assertDatabaseCount('forecast_days', 7);

        $status = CollectionStatus::query()->where('source', 'forecast')->first();
        $this->assertSame(0, $status->consecutive_failures);
        $this->assertNotNull($status->last_success_at);
    }

    public function test_refresh_is_idempotent_for_the_same_dates(): void
    {
        Http::fake(['*' => Http::response($this->openMeteoBody(7), 200)]);

        $this->service()->refresh();
        $this->service()->refresh();

        // Days are keyed by date and upserted, so re-running does not duplicate.
        $this->assertDatabaseCount('forecast_days', 7);
    }

    public function test_a_failed_refresh_keeps_the_last_good_forecast(): void
    {
        // Seed a previously stored forecast (today + tomorrow).
        $today = CarbonImmutable::now('America/New_York')->startOfDay();
        $this->storeForecastDay($today, ['temperature_high' => 88.0]);
        $this->storeForecastDay($today->addDay(), ['temperature_high' => 90.0]);

        Http::fake(['*' => Http::response('', 500)]);

        $result = $this->service()->refresh();

        $this->assertFalse($result['success']);
        $this->assertSame('Open-Meteo request failed: Unexpected HTTP status 500.', $result['error']);
        // The prior forecast is untouched — the display keeps showing it.
        $this->assertDatabaseCount('forecast_days', 2);

        $status = CollectionStatus::query()->where('source', 'forecast')->first();
        $this->assertSame(1, $status->consecutive_failures);
    }

    public function test_refresh_prunes_past_days_not_present_in_the_new_forecast(): void
    {
        // A stale day well in the past that the new forecast will not include.
        $stalePast = CarbonImmutable::now('America/New_York')->startOfDay()->subDays(5);
        $this->storeForecastDay($stalePast, ['temperature_high' => 50.0]);
        $this->assertDatabaseCount('forecast_days', 1);

        Http::fake(['*' => Http::response($this->openMeteoBody(7), 200)]);
        $this->service()->refresh();

        // The past day is gone; only the 7 fresh days remain.
        $this->assertDatabaseMissing('forecast_days', ['date' => $stalePast->toDateString()]);
        $this->assertSame(7, ForecastDay::query()->count());
    }
}
