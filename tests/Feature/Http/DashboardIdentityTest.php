<?php

namespace Tests\Feature\Http;

use App\Http\Middleware\WeatherAdminSession;
use App\Weather\Configuration\WeatherSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): self
    {
        config(['weather_admin.email' => 'weather@example.test', 'weather_admin.password_hash' => password_hash('test-only-password', PASSWORD_BCRYPT, ['cost' => 4])]);

        return $this->withSession(['weather_auth' => ['version' => WeatherAdminSession::version(), 'started' => time(), 'seen' => time()]]);
    }

    public function test_new_install_defaults_are_independent_of_station_name_and_have_no_household_suffix(): void
    {
        config(['weather.station_name' => 'My sensor', 'weather.location_name' => 'My town']);
        $this->get('/')->assertOk()->assertSee('<title>Midway Weather</title>', false)
            ->assertSee('id="dashboard-title">Midway Weather</h1>', false)
            ->assertSee('id="dashboard-subtitle" >My town</p>', false)
            ->assertDontSee('Turner Home');
        $this->getJson('/api/display/weather')->assertJsonPath('identity.title', 'Midway Weather')
            ->assertJsonPath('identity.subtitle', 'My town')->assertJsonPath('identity.icon', 'dynamic')
            ->assertJsonPath('identity.symbol', null)->assertJsonPath('meta.station_name', 'My sensor');
        $this->assertDatabaseCount('weather_settings', 0);
    }

    public function test_legacy_migration_snapshots_existing_appearance_without_replacing_other_settings(): void
    {
        $values = app(WeatherSettings::class)->defaults();
        unset($values['identity']);
        $values['map']['location'] = 'Existing town';
        $values['display']['edge_padding'] = 30;
        DB::table('weather_settings')->insert(['id' => 1, 'revision' => 7, 'configuration' => json_encode($values), 'created_at' => now(), 'updated_at' => now()]);
        config(['weather.station_name' => 'Existing heading']);
        $migration = require database_path('migrations/2026_10_25_000001_preserve_existing_dashboard_identity.php');
        $migration->up();
        $bundle = app(WeatherSettings::class)->read();
        $this->assertSame(8, $bundle['revision']);
        $this->assertSame(['title' => 'Existing heading', 'subtitle' => 'Existing town · Turner Home', 'icon' => 'dynamic'], $bundle['values']['identity']);
        unset($bundle['values']['identity']);
        $this->assertSame($values, $bundle['values']);
        $stored = DB::table('weather_settings')->find(1);
        $migration->up();
        $migration->down();
        $this->assertEquals($stored, DB::table('weather_settings')->find(1));
    }

    public function test_upgrade_with_weather_data_but_no_saved_configuration_preserves_old_identity(): void
    {
        config(['weather.station_name' => 'Alaska Weather Station', 'weather.location_name' => 'My Alaska town']);
        DB::table('observations')->insert(['observed_at' => now(), 'temperature' => 32, 'created_at' => now(), 'updated_at' => now()]);
        $migration = require database_path('migrations/2026_10_25_000001_preserve_existing_dashboard_identity.php');
        $migration->up();
        $bundle = app(WeatherSettings::class)->read();
        $this->assertSame(1, $bundle['revision']);
        $this->assertSame(['title' => 'Alaska Weather Station', 'subtitle' => 'My Alaska town · Turner Home', 'icon' => 'dynamic'], $bundle['values']['identity']);
        $stored = DB::table('weather_settings')->find(1);
        $migration->up();
        $this->assertEquals($stored, DB::table('weather_settings')->find(1));
    }

    public function test_identity_preview_is_escaped_draft_only_and_save_updates_dashboard_without_changing_product_metadata(): void
    {
        $productName = config('app.name');
        $settings = app(WeatherSettings::class);
        $values = $settings->defaults();
        $values['identity'] = ['title' => "Jeff's Weather <b>", 'subtitle' => '<img src=x onerror=alert(1)>', 'icon' => 'snow'];
        $this->admin()->get('/admin')->assertSee('Dashboard identity')->assertSee('Weather Admin · Midway Weather');
        $this->postJson('/admin/preview', ['values' => $values])->assertOk()
            ->assertSee('Jeff&#039;s Weather &lt;b&gt;', false)->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertSee('class="brand-mark" aria-hidden="true"><use href="#wi-snow"', false)
            ->assertDontSee('<img src=x', false)->assertDontSee('id="pwa-client"', false);
        $this->assertSame(0, $settings->read()['revision']);
        $this->getJson('/api/display/weather')->assertJsonPath('identity.title', 'Midway Weather');
        $this->postJson('/admin/settings', ['revision' => 0, 'values' => $values])->assertOk()->assertJsonPath('revision', 1);
        $this->get('/')->assertSee('<title>Jeff&#039;s Weather &lt;b&gt;</title>', false);
        $this->getJson('/api/display/weather')->assertJsonPath('identity.title', $values['identity']['title'])->assertJsonPath('identity.symbol', 'wi-snow');
        $this->assertSame($productName, config('app.name'));
        $this->postJson('/admin/settings', ['revision' => 0, 'values' => $values])->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->assertSame(1, $settings->read()['revision']);
    }

    public function test_empty_subtitle_hides_the_entire_line_and_older_clients_preserve_identity(): void
    {
        $values = app(WeatherSettings::class)->defaults();
        $values['identity'] = ['title' => 'Smith Family Weather', 'subtitle' => '', 'icon' => 'wind'];
        $this->admin()->postJson('/admin/settings', ['revision' => 0, 'values' => $values])->assertOk();
        $response = $this->get('/')->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $this->assertTrue($document->getElementById('dashboard-subtitle')->hasAttribute('hidden'));
        $this->getJson('/api/display/weather')->assertJsonPath('identity.subtitle', '')->assertJsonPath('identity.symbol', 'ui-wind');
        unset($values['identity']);
        $values['map']['location'] = 'Unrelated location change';
        $this->postJson('/admin/settings', ['revision' => 1, 'values' => $values])->assertOk()->assertJsonPath('values.identity.title', 'Smith Family Weather')->assertJsonPath('values.identity.subtitle', '');
    }

    public function test_dynamic_heading_icon_follows_daylight_but_a_fixed_icon_does_not_change_weather_icon(): void
    {
        $this->travelTo(now(config('weather.timezone'))->setTime(23, 0));
        $this->get('/')->assertSee('class="brand-mark" aria-hidden="true"><use href="#wi-clear-night"', false);
        $values = app(WeatherSettings::class)->defaults();
        $values['identity']['icon'] = 'sun';
        $this->admin()->postJson('/admin/settings', ['revision' => 0, 'values' => $values])->assertOk();
        $this->get('/')->assertSee('class="brand-mark" aria-hidden="true"><use href="#wi-clear-day"', false)
            ->assertSee('id="hero-icon" aria-hidden="true"><use href="#wi-clear-night"', false);
    }

    public function test_invalid_identity_fields_cannot_save_or_preview(): void
    {
        foreach ([['title', ''], ['title', str_repeat('x', 61)], ['subtitle', str_repeat('x', 121)], ['title', "bad\nline"], ['subtitle', "bad\x00value"], ['icon', 'javascript:alert(1)'], ['icon', '<svg>'], ['icon', null]] as [$key, $value]) {
            $values = app(WeatherSettings::class)->defaults();
            $values['identity'][$key] = $value;
            $this->admin()->postJson('/admin/settings', ['revision' => 0, 'values' => $values])->assertUnprocessable()->assertJsonValidationErrors('identity.'.$key);
            $this->postJson('/admin/preview', ['values' => $values])->assertUnprocessable()->assertJsonValidationErrors('identity.'.$key);
        }
        $this->assertDatabaseCount('weather_settings', 0);
    }

    public function test_curated_icons_and_maximum_unicode_text_work_in_every_theme(): void
    {
        foreach (array_keys(WeatherSettings::themes()) as $theme) {
            foreach (WeatherSettings::identityIcons() as $id => $icon) {
                $values = app(WeatherSettings::class)->defaults();
                $values['theme']['selection'] = $theme;
                $values['identity'] = ['title' => str_repeat('雪', 60), 'subtitle' => str_repeat('晴', 120), 'icon' => $id];
                $this->admin()->postJson('/admin/preview', ['values' => $values])->assertOk()
                    ->assertSee(str_repeat('雪', 60))->assertSee(str_repeat('晴', 120));
            }
        }
        $this->assertDatabaseCount('weather_settings', 0);
    }
}
