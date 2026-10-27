<?php

namespace Tests\Feature\Providers;

use App\Http\Controllers\WeatherAdminController;
use App\Http\Middleware\WeatherAdminSession;
use App\Weather\Configuration\WeatherSettings;
use App\Weather\Presentation\SensorTiles;
use App\Weather\Providers\StationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SensorTilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['weather_admin.email' => 'sensors@example.test', 'weather_admin.password_hash' => password_hash('password', PASSWORD_BCRYPT, ['cost' => 4]), 'weather.ambient.device_mac' => 'AA:BB:CC:DD:EE:01']);
        app(StationCatalog::class)->ingest($this->devices(), true);
        DB::table('weather_stations')->update(['enabled' => true]);
        app(StationCatalog::class)->ingest($this->devices(), true);
    }

    private function devices(): array
    {
        return array_map(fn ($i) => ['macAddress' => 'AA:BB:CC:DD:EE:0'.$i, 'info' => ['name' => 'Station '.$i], 'lastData' => ['dateutc' => now()->getTimestampMs(), 'tempf' => 70 + $i, 'temp1f' => $i === 1 ? 80 : 98, 'mystery_voltage' => 123]], [1, 2]);
    }

    private function admin(): self
    {
        return $this->withSession(['weather_auth' => ['version' => WeatherAdminSession::version(), 'started' => time(), 'seen' => time()]]);
    }

    private function draft(int $station = 1): array
    {
        return ['revision' => app(WeatherSettings::class)->read()['revision'], 'measurement_id' => DB::table('weather_measurements')->where('station_id', $station)->where('field', 'temp1f')->value('id'), 'label' => 'Greenhouse sensor', 'icon' => 'thermometer', 'unit' => '°F', 'size' => 'wide', 'publish' => true];
    }

    public function test_discovery_and_private_preview_do_not_publish_or_write_tiles(): void
    {
        $this->getJson('/admin/sensor-tiles')->assertUnauthorized();
        $this->getJson('/api/display/weather')->assertJsonPath('sensor_tiles', []);
        $this->admin()->postJson('/admin/sensor-tiles', [...$this->draft(), 'preview' => true, 'unit' => '°C'])->assertOk()->assertJsonPath('preview.value', 26.7)->assertJsonPath('preview.history.min', 26.7);
        $this->assertDatabaseCount('weather_sensor_tiles', 0);
        $this->assertDatabaseCount('weather_settings', 0);
    }

    public function test_explicit_publish_selects_station_and_does_not_change_primary_contract(): void
    {
        $before = $this->getJson('/api/v2/summary/current')->json('data');
        $this->admin()->postJson('/admin/sensor-tiles', [...$this->draft(2), 'publish' => false])->assertUnprocessable();
        $this->admin()->postJson('/admin/sensor-tiles', $this->draft(2))->assertOk();
        $id = DB::table('weather_sensor_tiles')->value('id');
        $tile = $this->getJson('/api/display/weather')->json('sensor_tiles.'.$id);
        $this->assertEquals(98, $tile['value']);
        $this->assertEquals(98, $tile['history']['min']);
        $this->assertStringNotContainsString('AA:BB', json_encode($tile));
        $this->assertArrayNotHasKey('measurement_id', $tile);
        $this->assertSame($before, $this->getJson('/api/v2/summary/current')->json('data'));
        $this->get('/')->assertOk()->assertSee('Greenhouse sensor')->assertSee('data-sensor-tile', false);
    }

    public function test_pool_binding_keeps_id_and_order_and_updates_temperature_units(): void
    {
        $before = app(WeatherSettings::class)->read()['values'];
        $this->admin()->postJson('/admin/sensor-tiles', [...$this->draft(), 'id' => 'pool', 'unit' => '°C'])->assertOk();
        $after = app(WeatherSettings::class)->read()['values'];
        $this->assertSame(array_column($before['tiles'], 'id'), array_column($after['tiles'], 'id'));
        $this->assertSame($before['theme'], $after['theme']);
        $this->getJson('/api/display/weather')->assertJsonPath('sensor_tiles.pool.value', 26.7)->assertJsonPath('sensor_tiles.pool.unit', '°C');
        $this->admin()->postJson('/admin/sensor-tiles', [...$this->draft(), 'id' => 'pool', 'unit' => '°F'])->assertOk();
        $this->assertDatabaseCount('weather_sensor_tiles', 1);
    }

    public function test_unknown_disabled_and_unsupported_unit_cannot_be_published(): void
    {
        $unknown = DB::table('weather_measurements')->where('field', 'mystery_voltage')->value('id');
        $this->admin()->postJson('/admin/sensor-tiles', [...$this->draft(), 'measurement_id' => $unknown])->assertUnprocessable();
        $this->admin()->postJson('/admin/sensor-tiles', [...$this->draft(), 'unit' => 'kelvin'])->assertUnprocessable();
        $this->admin()->postJson('/admin/sensor-tiles', [...$this->draft(), 'label' => "bad\nlabel"])->assertUnprocessable();
        DB::table('weather_stations')->where('id', 1)->update(['enabled' => false]);
        $this->admin()->postJson('/admin/sensor-tiles', $this->draft())->assertUnprocessable();
        $this->assertDatabaseCount('weather_sensor_tiles', 0);
    }

    public function test_conflicting_publish_rolls_back_binding_and_layout(): void
    {
        $this->admin()->postJson('/admin/sensor-tiles', [...$this->draft(), 'revision' => 99])->assertUnprocessable();
        $this->assertDatabaseCount('weather_sensor_tiles', 0);
        $this->assertDatabaseCount('weather_settings', 0);
    }

    public function test_missing_invalid_stale_and_disabled_keep_last_known_and_real_time(): void
    {
        $this->admin()->postJson('/admin/sensor-tiles', [...$this->draft(), 'id' => 'pool'])->assertOk();
        $tile = (array) DB::table('weather_sensor_tiles')->first();
        $m = DB::table('weather_measurements')->where('id', $tile['measurement_id']);
        $m->update(['present' => false]);
        $vm = app(SensorTiles::class)->present($tile);
        $this->assertSame('missing', $vm['state']);
        $this->assertNull($vm['value']);
        $this->assertEquals(80, $vm['last_known']);
        $m->update(['present' => true, 'quality' => 'invalid']);
        $this->assertSame('invalid', app(SensorTiles::class)->present($tile)['state']);
        $m->update(['quality' => 'valid', 'observed_at' => now()->subHours(2)]);
        $vm = app(SensorTiles::class)->present($tile);
        $this->assertSame('stale', $vm['state']);
        $this->assertEquals(80, $vm['value']);
        $this->assertSame(now()->subHours(2)->toIso8601String(), $vm['observed_at']);
        DB::table('weather_stations')->where('id', 1)->update(['enabled' => false]);
        $this->assertSame('disabled', app(SensorTiles::class)->present($tile)['state']);
    }

    public function test_disabling_layout_unpublishes_html_and_api_data(): void
    {
        $this->admin()->postJson('/admin/sensor-tiles', $this->draft())->assertOk();
        $bundle = app(WeatherSettings::class)->read();
        foreach ($bundle['values']['tiles'] as &$tile) {
            if (str_starts_with($tile['id'], 'sensor-')) {
                $tile['enabled'] = false;
            }
        }
        unset($tile);
        app(WeatherSettings::class)->save($bundle['values'], $bundle['revision']);
        $this->getJson('/api/display/weather')->assertJsonPath('sensor_tiles', []);
        $this->get('/')->assertDontSee('Greenhouse sensor')->assertDontSee('data-sensor-tile', false);
    }

    public function test_history_does_not_mix_stations_or_include_old_or_invalid_values(): void
    {
        $this->admin()->postJson('/admin/sensor-tiles', [...$this->draft(), 'id' => 'pool'])->assertOk();
        foreach ([[1, 3, 77], [2, 4, 111], [1, 25, 70], [1, 5, 9999]] as [$station, $hours, $value]) {
            DB::table('weather_station_observations')->insert(['station_id' => $station, 'observed_at' => now()->subHours($hours), 'raw' => json_encode(['temp1f' => $value])]);
        }
        $this->getJson('/api/display/weather')->assertJsonPath('sensor_tiles.pool.history.min', 77)->assertJsonPath('sensor_tiles.pool.history.max', 80)->assertJsonCount(2, 'sensor_tiles.pool.history.points');
    }

    public function test_legacy_history_requires_matching_station_identity_and_is_bounded(): void
    {
        $this->admin()->postJson('/admin/sensor-tiles', [...$this->draft(), 'id' => 'pool'])->assertOk();
        foreach ([['aa:bb:cc:dd:ee:01', 3, 75], ['aa:bb:cc:dd:ee:02', 4, 120], [null, 5, 10]] as [$mac, $hours, $value]) {
            DB::table('observations')->insert(['device_mac' => $mac, 'observed_at' => now()->subHours($hours), 'raw' => json_encode(['temp1f' => $value])]);
        }
        $this->getJson('/api/display/weather')->assertJsonPath('sensor_tiles.pool.history.min', 75)->assertJsonPath('sensor_tiles.pool.history.max', 80);
        for ($i = 1; $i <= 100; $i++) {
            DB::table('weather_station_observations')->insert(['station_id' => 1, 'observed_at' => now()->subMinutes($i), 'raw' => json_encode(['temp1f' => 80])]);
        }
        cache()->forget('display.weather.payload');
        $this->getJson('/api/display/weather')->assertJsonCount(48, 'sensor_tiles.pool.history.points');
    }

    public function test_saved_markup_is_escaped_and_cached_admin_cannot_drop_custom_tiles(): void
    {
        $old = app(WeatherSettings::class)->read()['values'];
        $this->admin()->postJson('/admin/sensor-tiles', [...$this->draft(), 'label' => '<script>alert(1)</script>'])->assertOk();
        $this->get('/')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $validated = app(WeatherAdminController::class)->validateValues($old);
        $this->assertCount(13, $validated['tiles']);
    }
}
