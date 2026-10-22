<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\CollectionStatus;
use App\Models\Observation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

/**
 * The console commands are what the Pi's scheduler actually invokes. These
 * tests lock in the operator-facing contract: exit codes (so cron/monitoring
 * can react), the demo-mode short circuit, and the production safety guard.
 */
final class WeatherCommandsTest extends TestCase
{
    use MakesWeatherData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'weather.timezone' => 'America/New_York',
            'weather.ambient.api_key' => 'test-api-key',
            'weather.ambient.application_key' => 'test-app-key',
            'weather.ambient.device_mac' => 'AA:BB:CC:DD:EE:FF',
            'weather.ambient.retry_times' => 1,
            'weather.ambient.retry_sleep_ms' => 0,
            'weather.open_meteo.retry_times' => 1,
            'weather.open_meteo.retry_sleep_ms' => 0,
            'weather.demo_mode' => false,
        ]);
    }

    // --- weather:collect ----------------------------------------------------

    public function test_collect_stores_an_observation_on_success(): void
    {
        Http::fake(['*' => Http::response($this->ambientDevicesBody(['tempf' => 77.0]), 200)]);

        $this->artisan('weather:collect')->assertSuccessful();

        $this->assertDatabaseCount('observations', 1);
    }

    public function test_collect_exits_with_failure_when_the_api_fails(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->artisan('weather:collect')
            ->expectsOutputToContain('Collection failed')
            ->assertFailed();

        // Failure is recorded but no observation is stored (last-good preserved).
        $this->assertDatabaseCount('observations', 0);
        $status = CollectionStatus::query()->where('source', 'ambient')->first();
        $this->assertSame(1, $status->consecutive_failures);
    }

    public function test_collect_in_demo_mode_generates_fixtures_without_calling_ambient(): void
    {
        config(['weather.demo_mode' => true]);
        Http::fake();

        $this->artisan('weather:collect')
            ->expectsOutputToContain('Demo mode is enabled')
            ->assertSuccessful();

        // Demo branch seeds data and never touches the network.
        $this->assertGreaterThan(0, Observation::query()->count());
        Http::assertNothingSent();
    }

    // --- weather:demo -------------------------------------------------------

    public function test_demo_command_seeds_data_and_reports_counts(): void
    {
        Http::fake();

        $this->artisan('weather:demo', ['--hours' => 3])
            ->expectsOutputToContain('Generating')
            ->assertSuccessful();

        $this->assertGreaterThan(0, Observation::query()->count());
        $this->assertDatabaseCount('forecast_days', 7);
    }

    public function test_demo_command_refuses_to_run_in_production_without_force(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('weather:demo')
            ->expectsOutputToContain('Refusing to generate demo data in production')
            ->assertFailed();

        $this->assertDatabaseCount('observations', 0);
    }

    public function test_demo_command_runs_in_production_with_force(): void
    {
        app()->detectEnvironment(fn () => 'production');
        Http::fake();

        $this->artisan('weather:demo', ['--hours' => 1, '--force' => true])
            ->assertSuccessful();

        $this->assertGreaterThan(0, Observation::query()->count());
    }

    // --- weather:forecast ---------------------------------------------------

    public function test_forecast_command_stores_days_on_success(): void
    {
        Http::fake(['*' => Http::response($this->openMeteoBody(7), 200)]);

        $this->artisan('weather:forecast')
            ->expectsOutputToContain('Stored 7 forecast day(s).')
            ->assertSuccessful();

        $this->assertDatabaseCount('forecast_days', 7);
    }

    public function test_forecast_command_exits_with_failure_when_the_api_fails(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $this->artisan('weather:forecast')
            ->expectsOutputToContain('Forecast refresh failed')
            ->assertFailed();
    }

    // --- weather:status -----------------------------------------------------

    public function test_status_exits_with_failure_when_no_data_has_been_collected(): void
    {
        $this->artisan('weather:status')
            ->expectsOutputToContain('No observations collected yet.')
            ->assertFailed();
    }

    public function test_status_exits_successfully_when_data_is_fresh(): void
    {
        $this->storeObservation(CarbonImmutable::now('UTC')->subMinutes(3));

        $this->artisan('weather:status')->assertSuccessful();
    }

    public function test_status_exits_with_failure_when_data_is_stale(): void
    {
        config(['weather.staleness.stale_minutes' => 20]);
        $this->storeObservation(CarbonImmutable::now('UTC')->subMinutes(90));

        $this->artisan('weather:status')->assertFailed();
    }

    // --- weather:cleanup ----------------------------------------------------

    public function test_cleanup_prunes_old_observations_and_reports(): void
    {
        config(['weather.retention_days' => 10]);
        $this->storeObservation(CarbonImmutable::now('UTC')->subDays(2));
        $this->storeObservation(CarbonImmutable::now('UTC')->subDays(30));

        $this->artisan('weather:cleanup')
            ->expectsOutputToContain('Cleanup complete')
            ->assertSuccessful();

        // The 30-day-old observation is pruned; the recent one remains.
        $this->assertDatabaseCount('observations', 1);
    }
}
