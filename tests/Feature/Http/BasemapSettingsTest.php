<?php

namespace Tests\Feature\Http;

use App\Http\Middleware\WeatherAdminSession;
use App\Weather\Configuration\Basemap;
use App\Weather\Configuration\WeatherSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BasemapSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['weather_admin.email' => 'maps@example.test', 'weather_admin.password_hash' => password_hash('test-password', PASSWORD_BCRYPT, ['cost' => 4]), 'weather.basemap.provider' => 'openstreetmap']);
        Http::preventStrayRequests();
    }

    private function admin(): self
    {
        return $this->withSession(['weather_auth' => ['version' => WeatherAdminSession::version(), 'started' => time(), 'seen' => time()]]);
    }

    private function values(string $provider = 'environment', string $authentication = 'domain'): array
    {
        return array_replace(app(WeatherSettings::class)->read()['values'], ['basemap' => compact('provider', 'authentication')]);
    }

    public function test_existing_configuration_inherits_stadia_without_changing_revision_or_layout(): void
    {
        $values = $this->values();
        unset($values['basemap']);
        $values['tiles'] = array_reverse($values['tiles']);
        $values['theme']['selection'] = 'default';
        DB::table('weather_settings')->insert(['id' => 1, 'revision' => 17, 'configuration' => json_encode($values), 'created_at' => now(), 'updated_at' => now()]);
        config(['weather.basemap.provider' => 'stadia', 'weather.basemap.stadia_api_key' => 'host-key']);
        $this->getJson('/api/display/weather')->assertOk()->assertJsonPath('meta.basemap.provider', 'stadia')->assertJsonPath('configuration.revision', 17)->assertJsonPath('configuration.values.tiles', $values['tiles'])->assertJsonPath('seasonal_theme', 'default');
        $stored = DB::table('weather_settings')->find(1);
        $this->assertSame($values, json_decode($stored->configuration, true));
        $this->assertSame(17, $stored->revision);
        Http::assertNothingSent();
    }

    public function test_fresh_install_is_osm_and_admin_can_override_and_restore_environment(): void
    {
        $this->getJson('/api/display/weather')->assertJsonPath('meta.basemap.provider', 'openstreetmap');
        $this->admin()->postJson('/admin/settings', ['revision' => 0, 'values' => $this->values('stadia')])->assertOk();
        $this->getJson('/api/display/weather')->assertJsonPath('meta.basemap.provider', 'stadia');
        $this->postJson('/admin/settings', ['revision' => 1, 'values' => $this->values()])->assertOk();
        $this->getJson('/api/display/weather')->assertJsonPath('meta.basemap.provider', 'openstreetmap');
    }

    public function test_write_only_map_key_is_encrypted_and_only_selected_public_tile_key_is_exposed(): void
    {
        config(['weather.ambient.api_key' => 'private-weather-sentinel']);
        $values = $this->values('maptiler');
        $key = 'test-maptiler-public-key';
        $this->admin()->postJson('/admin/settings', ['revision' => 0, 'values' => $values, 'map_credential' => ['provider' => 'maptiler', 'key' => $key]])->assertOk()->assertDontSee($key)->assertJsonPath('map_credentials.maptiler.configured', true);
        $cipher = DB::table('weather_map_credentials')->value('credential');
        $this->assertStringNotContainsString($key, $cipher);
        $this->assertSame($key, Crypt::decryptString($cipher));
        $this->assertStringNotContainsString($key, DB::table('weather_settings')->value('configuration'));
        $this->assertArrayNotHasKey('basemap', json_decode(DB::table('weather_settings')->value('configuration'), true));
        $this->get('/admin')->assertOk()->assertDontSee($key)->assertDontSee('private-weather-sentinel');
        $this->getJson('/api/display/weather')->assertJsonPath('meta.basemap.provider', 'maptiler')->assertJsonPath('meta.basemap.tile_size', 512)->assertJsonPath('meta.basemap.zoom_offset', -1)->assertSee('key='.$key)->assertDontSee('private-weather-sentinel');
        $this->postJson('/admin/settings', ['revision' => 1, 'values' => $this->values('openstreetmap')])->assertOk();
        $this->getJson('/api/display/weather')->assertDontSee($key);
        $this->get('/')->assertDontSee($key);
        Http::assertNothingSent();
    }

    public function test_preview_uses_unsaved_key_and_provider_without_persisting_or_leaking_to_other_previews(): void
    {
        $before = app(WeatherSettings::class)->read();
        $this->admin()->postJson('/admin/preview', ['values' => $this->values('mapbox'), 'map_credential' => ['provider' => 'mapbox', 'key' => 'pk.preview-only']])->assertOk()->assertSee('data-basemap-provider="mapbox"', false)->assertSee('dark-v11')->assertSee('pk.preview-only')->assertSee('Improve this map')->assertSee('vendor/mapbox-logo.svg')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($before, app(WeatherSettings::class)->read());
        $this->assertDatabaseCount('weather_map_credentials', 0);
        $this->postJson('/admin/preview', ['values' => $before['values']])->assertOk()->assertDontSee('pk.preview-only')->assertSee('data-basemap-provider="openstreetmap"', false);
    }

    public function test_missing_keys_secret_tokens_untrusted_providers_and_mismatched_credentials_are_rejected(): void
    {
        $this->admin();
        foreach (['maptiler', 'mapbox'] as $provider) {
            $this->postJson('/admin/settings', ['revision' => 0, 'values' => $this->values($provider)])->assertUnprocessable()->assertJsonValidationErrors('map_credential.key');
        }
        foreach (['sk.secret-token', 'pk.bad?token', 'pk.bad<script>'] as $key) {
            $this->postJson('/admin/settings', ['revision' => 0, 'values' => $this->values('mapbox'), 'map_credential' => ['provider' => 'mapbox', 'key' => $key]])->assertUnprocessable();
        }
        $this->postJson('/admin/settings', ['revision' => 0, 'values' => $this->values('https://attacker.test')])->assertUnprocessable();
        $this->postJson('/admin/settings', ['revision' => 0, 'values' => $this->values('mapbox'), 'map_credential' => ['provider' => 'stadia', 'key' => 'wrong-provider']])->assertUnprocessable();
        $this->assertDatabaseCount('weather_map_credentials', 0);
        $this->assertSame(0, app(WeatherSettings::class)->read()['revision']);
    }

    public function test_key_replacement_conflict_rolls_back_and_old_admin_saves_preserve_selection(): void
    {
        $values = $this->values('stadia', 'key');
        $this->admin()->postJson('/admin/settings', ['revision' => 0, 'values' => $values, 'map_credential' => ['provider' => 'stadia', 'key' => 'original-key']])->assertOk();
        $this->postJson('/admin/settings', ['revision' => 0, 'values' => $values, 'map_credential' => ['provider' => 'stadia', 'key' => 'conflicting-key']])->assertUnprocessable();
        $this->assertSame('original-key', app(Basemap::class)->key('stadia'));
        unset($values['basemap']);
        $values['display']['edge_padding'] = 31;
        $this->postJson('/admin/settings', ['revision' => 1, 'values' => $values])->assertOk()->assertJsonPath('values.basemap.provider', 'stadia');
        $this->getJson('/api/display/weather')->assertSee('original-key');
    }

    public function test_domain_auth_ignores_saved_key_and_environment_mode_ignores_admin_key(): void
    {
        config(['weather.basemap.provider' => 'stadia', 'weather.basemap.stadia_api_key' => 'environment-key']);
        $this->admin()->postJson('/admin/settings', ['revision' => 0, 'values' => $this->values('stadia', 'key'), 'map_credential' => ['provider' => 'stadia', 'key' => 'admin-key']])->assertOk();
        $this->postJson('/admin/settings', ['revision' => 1, 'values' => $this->values('stadia')])->assertOk();
        $this->getJson('/api/display/weather')->assertDontSee('admin-key')->assertDontSee('environment-key');
        $this->postJson('/admin/settings', ['revision' => 2, 'values' => $this->values()])->assertOk();
        $this->getJson('/api/display/weather')->assertSee('environment-key')->assertDontSee('admin-key');
    }

    public function test_removing_admin_key_restores_environment_and_does_not_remove_other_keys(): void
    {
        config(['weather.basemap.maptiler_api_key' => 'host-maptiler']);
        app(Basemap::class)->saveCredential(['provider' => 'stadia', 'key' => 'other-key']);
        $this->admin()->postJson('/admin/settings', ['revision' => 0, 'values' => $this->values('maptiler'), 'map_credential' => ['provider' => 'maptiler', 'key' => 'admin-maptiler']])->assertOk();
        $this->postJson('/admin/settings', ['revision' => 1, 'values' => $this->values('maptiler'), 'map_credential' => ['provider' => 'maptiler', 'clear' => true]])->assertOk();
        $this->assertDatabaseMissing('weather_map_credentials', ['provider' => 'maptiler']);
        $this->assertSame('other-key', app(Basemap::class)->key('stadia'));
        $this->getJson('/api/display/weather')->assertSee('host-maptiler')->assertDontSee('admin-maptiler');
    }

    public function test_guests_cannot_store_keys_and_environment_secret_token_never_reaches_display(): void
    {
        $this->postJson('/admin/settings', ['revision' => 0, 'values' => $this->values('mapbox'), 'map_credential' => ['provider' => 'mapbox', 'key' => 'pk.guest']])->assertUnauthorized();
        config(['weather.basemap.provider' => 'mapbox', 'weather.basemap.mapbox_public_token' => 'sk.accidental-secret']);
        $this->getJson('/api/display/weather')->assertDontSee('sk.accidental-secret')->assertJsonPath('meta.basemap.tile_url', null)->assertJsonPath('meta.basemap.fallback.provider', 'openstreetmap');
    }
}
