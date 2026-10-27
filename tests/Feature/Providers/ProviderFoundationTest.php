<?php

namespace Tests\Feature\Providers;

use App\Http\Middleware\WeatherAdminSession;
use App\Models\CollectionStatus;
use App\Weather\Configuration\WeatherSettings;
use App\Weather\Providers\Connections;
use App\Weather\Providers\PollDeferred;
use App\Weather\Providers\PrimaryWeatherStore;
use App\Weather\Providers\ProviderRequests;
use App\Weather\Providers\StationCatalog;
use App\Weather\Services\DailySummaryService;
use App\Weather\Services\ObservationRepository;
use App\Weather\Services\RetentionService;
use App\Weather\Services\WeatherCollectionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

class ProviderFoundationTest extends TestCase
{
    use MakesWeatherData, RefreshDatabase;

    private const A = 'AA:BB:CC:DD:EE:01';

    private const B = 'AA:BB:CC:DD:EE:02';

    private $responses = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['weather_admin.email' => 'providers@example.test', 'weather_admin.password_hash' => password_hash('test-password', PASSWORD_BCRYPT, ['cost' => 4]), 'weather.ambient.api_key' => 'environment-api-secret', 'weather.ambient.application_key' => 'environment-app-secret', 'weather.ambient.device_mac' => self::A, 'weather.ambient.retry_times' => 1]);
        Http::preventStrayRequests();
    }

    private function admin(): self
    {
        return $this->withSession(['weather_auth' => ['version' => WeatherAdminSession::version(), 'started' => time(), 'seen' => time()]]);
    }

    private function devices(?CarbonImmutable $time = null): array
    {
        $time ??= CarbonImmutable::now('UTC')->startOfMinute();

        return [
            ['macAddress' => self::A, 'info' => ['name' => 'Garden weather'], 'lastData' => ['dateutc' => $time->getTimestampMs(), 'tempf' => 72, 'temp1f' => 80, 'humidity' => 50, 'mystery_voltage' => 987]],
            ['macAddress' => self::B, 'info' => ['name' => 'Greenhouse'], 'lastData' => ['dateutc' => $time->getTimestampMs(), 'tempf' => 91, 'humidity' => 75, 'temp2f' => 99]],
        ];
    }

    private function discover(array $devices): void
    {
        app(StationCatalog::class)->ingest($devices, false);
    }

    private function enableBoth(?int $primary = null): void
    {
        DB::table('weather_stations')->update(['enabled' => true]);
        if ($primary !== null) {
            DB::table('weather_station_settings')->where('id', 1)->update(['primary_station_id' => $primary]);
        }
    }

    private function collect(array $devices): array
    {
        $this->travel(61)->seconds();
        if (! $this->responses) {
            $this->responses = Http::sequence();
            Http::fake(['*' => $this->responses]);
        }
        $this->responses->push($devices);

        return app(WeatherCollectionService::class)->collect();
    }

    public function test_authenticated_discovery_keeps_new_stations_private_and_unknown_fields_diagnostic(): void
    {
        Http::fake(['*' => Http::response($this->devices())]);
        $this->admin()->postJson('/admin/providers/ambient/probe', [])->assertOk();
        $model = $this->getJson('/admin/providers')->assertOk()->json();
        $this->assertCount(2, $model['providers']);
        $this->assertCount(2, $model['stations']);
        $this->assertTrue((bool) $model['stations'][0]['enabled']);
        $this->assertFalse((bool) $model['stations'][1]['enabled']);
        $unknown = collect($model['stations'][0]['measurements'])->firstWhere('field', 'mystery_voltage');
        $this->assertSame('diagnostic', $unknown['state']);
        $this->assertNull($unknown['unit']);
        $this->assertFalse($unknown['publishable']);
        $this->assertNull($unknown['last_value']);
        $this->assertDatabaseCount('weather_station_observations', 0);
        $this->assertDatabaseCount('observations', 0);
        $this->getJson('/api/display/weather')->assertDontSee('mystery_voltage')->assertDontSee('temp1f')->assertDontSee(self::A)->assertDontSee('environment-api-secret');
        Http::assertSentCount(1);
        $this->getJson('/admin/providers')->assertOk();
        Http::assertSentCount(1);
    }

    public function test_same_time_readings_are_station_scoped_and_primary_switch_never_overwrites_legacy_history(): void
    {
        $devices = $this->devices();
        $this->discover($devices);
        $this->enableBoth();
        $this->assertTrue($this->collect($devices)['success']);
        $this->assertDatabaseCount('weather_station_observations', 2);
        $this->assertDatabaseCount('observations', 1);
        $legacy = DB::table('observations')->first();
        $b = DB::table('weather_stations')->where('external_id', strtolower(self::B))->value('id');
        DB::table('weather_station_settings')->where('id', 1)->update(['primary_station_id' => $b]);
        cache()->forget('display.weather.payload');
        $this->getJson('/api/display/weather')->assertJsonPath('observation.temperature', 91);
        $this->getJson('/api/v1/summary/current')->assertJsonPath('data.temperature.value', 91);
        $this->getJson('/api/v2/summary/current')->assertOk()->assertJsonPath('data.observation.temperature_f', 91)->assertJsonPath('data.observed_today.temperature_high_f', 91)->assertJsonPath('data.observed_today.temperature_low_f', 91);
        $this->assertEquals($legacy, DB::table('observations')->first());
        $again = $this->collect($devices);
        $this->assertFalse($again['created']);
        $this->assertDatabaseCount('weather_station_observations', 2);
        DB::table('weather_station_settings')->where('id', 1)->update(['primary_station_id' => 1]);
        cache()->forget('display.weather.payload');
        $this->getJson('/api/display/weather')->assertJsonPath('observation.temperature', 72);
    }

    public function test_missing_channels_are_retained_and_repeated_old_packets_do_not_refresh_measurements(): void
    {
        $devices = $this->devices();
        $this->collect($devices);
        $before = DB::table('weather_measurements')->where('field', 'temp1f')->first();
        unset($devices[0]['lastData']['temp1f']);
        $this->travel(25)->minutes();
        $this->collect($devices);
        $after = DB::table('weather_measurements')->where('field', 'temp1f')->first();
        $this->assertSame($before->id, $after->id);
        $this->assertSame($before->observed_at, $after->observed_at);
        $this->assertSame(80.0, $after->last_value);
        $this->assertFalse((bool) $after->present);
        $state = $this->admin()->getJson('/admin/providers')->json();
        $fields = collect($state['stations'][0]['measurements']);
        $this->assertSame('missing', $fields->firstWhere('field', 'temp1f')['state']);
        $this->assertSame('stale', $fields->firstWhere('field', 'tempf')['state']);
        $this->assertSame('connected', $state['providers'][0]['transport']['connectivity']);
        $this->assertSame(0, $state['providers'][0]['collection']['consecutive_failures']);
    }

    public function test_primary_missing_does_not_stop_secondary_storage_or_substitute_its_weather(): void
    {
        $devices = $this->devices();
        $this->discover($devices);
        $this->enableBoth();
        $this->collect($devices);
        $later = $this->devices(CarbonImmutable::now('UTC')->addMinute());
        $result = $this->collect([$later[1]]);
        $this->assertFalse($result['success']);
        $this->assertDatabaseCount('weather_station_observations', 3);
        $this->getJson('/api/display/weather')->assertJsonPath('observation.temperature', 72);
        $this->assertSame(0, CollectionStatus::where('source', 'station:2')->value('consecutive_failures'));
        $this->assertSame(1, CollectionStatus::where('source', 'station:1')->value('consecutive_failures'));
    }

    public function test_invalid_timestamps_are_missing_data_not_a_fresh_now_reading(): void
    {
        $devices = $this->devices();
        unset($devices[0]['lastData']['dateutc']);
        $result = $this->collect($devices);
        $this->assertFalse($result['success']);
        $this->assertDatabaseCount('observations', 0);
        $this->assertDatabaseCount('weather_station_observations', 0);
        $this->assertNull(DB::table('weather_measurements')->where('field', 'tempf')->where('station_id', 1)->value('observed_at'));
    }

    public function test_credentials_are_write_only_encrypted_revision_checked_and_environment_restorable(): void
    {
        $payload = ['revision' => 0, 'mode' => 'admin', 'enabled' => true, 'api_key' => 'admin-api-sentinel', 'application_key' => 'admin-app-sentinel'];
        $this->admin()->postJson('/admin/providers/ambient', $payload)->assertOk()->assertDontSee('admin-api-sentinel');
        $cipher = DB::table('weather_provider_connections')->where('provider', 'ambient')->value('credentials');
        $this->assertStringNotContainsString('admin-api-sentinel', $cipher);
        $this->assertSame('admin-api-sentinel', app(Connections::class)->credentials()['api_key']);
        $this->postJson('/admin/providers/ambient', $payload)->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->postJson('/admin/providers/ambient', ['revision' => 1, 'mode' => 'admin', 'enabled' => true])->assertOk();
        $this->assertSame('admin-api-sentinel', app(Connections::class)->credentials()['api_key']);
        $this->get('/admin')->assertDontSee('admin-api-sentinel')->assertDontSee('admin-app-sentinel');
        $this->getJson('/admin/providers')->assertDontSee('admin-api-sentinel');
        $this->postJson('/admin/providers/ambient', ['revision' => 2, 'mode' => 'environment', 'enabled' => true])->assertOk();
        $this->assertSame('environment-api-secret', app(Connections::class)->credentials()['api_key']);
        $this->assertNull(DB::table('weather_provider_connections')->value('credentials'));
    }

    public function test_corrupt_credentials_fail_closed_and_guests_cannot_read_or_mutate_provider_state(): void
    {
        $this->getJson('/admin/providers')->assertUnauthorized();
        $this->postJson('/admin/providers/ambient/probe', [])->assertUnauthorized();
        $this->postJson('/admin/providers/ambient', ['revision' => 0, 'mode' => 'environment', 'enabled' => true])->assertUnauthorized();
        DB::table('weather_provider_connections')->insert(['provider' => 'ambient', 'revision' => 1, 'mode' => 'admin', 'enabled' => true, 'credentials' => 'corrupt']);
        Http::fake();
        $this->admin()->postJson('/admin/providers/ambient/probe', [])->assertUnprocessable()->assertDontSee('environment-api-secret')->assertDontSee('corrupt');
        Http::assertNothingSent();
    }

    public function test_discovery_and_collection_share_persistent_rate_limits_and_429_backoff(): void
    {
        Http::fake(['*' => Http::sequence()->push([], 429, ['Retry-After' => '120'])->push($this->devices())]);
        $this->admin()->postJson('/admin/providers/ambient/probe', [])->assertUnprocessable();
        $this->travel(61)->seconds();
        $result = app(WeatherCollectionService::class)->collect();
        $this->assertTrue($result['skipped']);
        Http::assertSentCount(1);
        $this->travel(60)->seconds();
        $this->assertTrue(app(WeatherCollectionService::class)->collect()['success']);
        Http::assertSentCount(2);
        $this->postJson('/admin/providers/ambient/probe', [])->assertStatus(429);
        Http::assertSentCount(2);
        $limits = json_encode(DB::table('weather_provider_limits')->get());
        $this->assertStringNotContainsString('environment-api-secret', $limits);
        $this->assertStringNotContainsString('environment-app-secret', $limits);
    }

    public function test_station_settings_require_an_enabled_primary_complete_inventory_and_current_revision(): void
    {
        $this->discover($this->devices());
        $this->admin();
        $stations = DB::table('weather_stations')->get()->map(fn ($s) => ['id' => $s->id, 'label' => $s->label, 'enabled' => true])->all();
        $this->postJson('/admin/providers/stations', ['revision' => 0, 'primary_station_id' => 2, 'stations' => $stations])->assertOk();
        $this->postJson('/admin/providers/stations', ['revision' => 0, 'primary_station_id' => 1, 'stations' => $stations])->assertUnprocessable();
        $stations[1]['enabled'] = false;
        $this->postJson('/admin/providers/stations', ['revision' => 1, 'primary_station_id' => 2, 'stations' => $stations])->assertUnprocessable();
        $this->postJson('/admin/providers/stations', ['revision' => 1, 'primary_station_id' => 1, 'stations' => [$stations[0]]])->assertUnprocessable();
    }

    public function test_upgrade_adds_no_configuration_mutations_and_code_can_read_legacy_tables_without_foundation_schema(): void
    {
        $t = CarbonImmutable::now('UTC')->subMinute()->startOfMinute();
        $this->storeObservation($t, ['raw' => ['dateutc' => $t->getTimestampMs()]]);
        $settings = app(WeatherSettings::class);
        $settings->save($settings->defaults(), 0);
        $before = DB::table('weather_settings')->first();
        $old = DB::table('observations')->first();
        $migration = require database_path('migrations/2026_10_27_000002_create_weather_provider_foundation.php');
        $migration->down();
        $this->getJson('/api/v2/summary/current')->assertOk()->assertJsonPath('data.observation.temperature_f', 80);
        $migration->up();
        $this->admin()->getJson('/admin/providers')->assertOk();
        $this->assertEquals($before, DB::table('weather_settings')->first());
        $this->assertEquals($old, DB::table('observations')->first());
    }

    public function test_retention_prunes_all_station_readings_but_keeps_measurement_catalogs_and_daily_history(): void
    {
        $devices = $this->devices(CarbonImmutable::now('UTC')->subDays(11));
        $this->discover($devices);
        $this->enableBoth();
        $this->collect($devices);
        app(DailySummaryService::class)->rebuildForDate(CarbonImmutable::createFromTimestampMs($devices[1]['lastData']['dateutc'], 'UTC')->setTimezone(config('weather.timezone'))->startOfDay(), 2);
        $this->assertDatabaseCount('weather_station_summaries', 1);
        $this->assertDatabaseCount('daily_summaries', 1);
        app(RetentionService::class)->prune();
        $this->assertDatabaseCount('weather_station_observations', 0);
        $this->assertDatabaseCount('observations', 0);
        $this->assertDatabaseCount('weather_station_summaries', 1);
        $this->assertGreaterThan(0, DB::table('weather_measurements')->count());
    }

    public function test_disabled_integrations_make_no_network_requests_and_configuration_changes_discard_old_inventory(): void
    {
        $this->admin()->postJson('/admin/providers/ambient', ['revision' => 0, 'mode' => 'environment', 'enabled' => false])->assertOk();
        Http::fake();
        $this->assertTrue(app(WeatherCollectionService::class)->collect()['skipped']);
        Http::assertNothingSent();
        $this->expectException(PollDeferred::class);
        app(StationCatalog::class)->ingest($this->devices(), true, 0);
    }

    public function test_ambient_application_key_budget_is_shared_across_different_account_keys(): void
    {
        Http::fake(['*' => Http::response([])]);
        $gate = app(ProviderRequests::class);
        $keys = ['api_key' => 'account-one', 'application_key' => 'shared-application'];
        $gate->run('ambient', $keys, fn () => Http::get('https://provider.test/devices'));
        $this->travel(200)->milliseconds();
        try {
            $gate->run('ambient', ['api_key' => 'account-two', 'application_key' => 'shared-application'], fn () => Http::get('https://provider.test/devices'));
            $this->fail('Expected the shared app budget to defer.');
        } catch (PollDeferred) {
        }
        Http::assertSentCount(1);
        $this->travel(1)->seconds();
        $gate->run('ambient', ['api_key' => 'account-two', 'application_key' => 'shared-application'], fn () => Http::get('https://provider.test/devices'));
        Http::assertSentCount(2);
    }

    public function test_invalid_and_older_channel_values_never_replace_last_good_values(): void
    {
        $now = CarbonImmutable::now('UTC')->startOfMinute();
        $devices = $this->devices($now);
        $this->discover($devices);
        $devices[0]['lastData']['temp1f'] = 99999;
        $this->discover($devices);
        $m = DB::table('weather_measurements')->where('field', 'temp1f')->first();
        $this->assertSame(80.0, $m->last_value);
        $this->assertSame('invalid', $m->quality);
        $older = $this->devices($now->subHour());
        $older[0]['lastData']['temp1f'] = 40;
        $this->discover($older);
        $m = DB::table('weather_measurements')->where('field', 'temp1f')->first();
        $this->assertSame(80.0, $m->last_value);
        $this->assertTrue(CarbonImmutable::parse($m->observed_at, 'UTC')->equalTo($now));
    }

    public function test_unavailable_configured_station_does_not_silently_select_another_station(): void
    {
        config(['weather.ambient.device_mac' => 'AA:BB:CC:DD:EE:99']);
        $this->storeObservation(CarbonImmutable::now('UTC')->subMinute());
        $this->discover($this->devices());
        $this->assertNull(app(PrimaryWeatherStore::class)->settings());
        $this->assertSame(0, DB::table('weather_stations')->where('enabled', true)->count());
        $this->assertSame(80.0, app(ObservationRepository::class)->latest()->temperature);
    }

    public function test_open_meteo_stays_keyless_and_uses_saved_forecast_horizon_only_in_admin_mode(): void
    {
        $this->admin()->postJson('/admin/providers/open_meteo', ['revision' => 0, 'mode' => 'admin', 'enabled' => true, 'forecast_days' => 4])->assertOk()->assertJsonPath('forecast_days', 4);
        $this->assertSame(4, app(Connections::class)->forecastDays());
        $this->postJson('/admin/providers/open_meteo', ['revision' => 1, 'mode' => 'admin', 'enabled' => true, 'api_key' => 'not-supported'])->assertUnprocessable();
        $this->postJson('/admin/providers/open_meteo', ['revision' => 1, 'mode' => 'environment', 'enabled' => true])->assertOk();
        $this->assertSame((int) config('weather.open_meteo.forecast_days'), app(Connections::class)->forecastDays());
    }
}
