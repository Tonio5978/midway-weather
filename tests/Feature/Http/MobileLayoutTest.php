<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

/**
 * Mobile-responsive regression guard.
 *
 * These tests lock in the presentation contract for the phone/tablet layout
 * WITHOUT changing the appliance's behaviour: same single Blade view, same
 * routes, same JSON, same "browser only talks to this app" guarantee. They
 * assert the two things that make the mobile layout even possible —
 *   1. the viewport meta tag lets mobile media queries match, and
 *   2. the stylesheet actually ships a mobile layer —
 * plus that the map/radar/poll code remains single-instance so a second
 * breakpoint can never spawn a duplicate map or timer.
 */
final class MobileLayoutTest extends TestCase
{
    use MakesWeatherData;
    use RefreshDatabase;

    public function test_viewport_meta_allows_mobile_media_queries_to_match(): void
    {
        $this->storeObservation(CarbonImmutable::now('UTC')->subMinutes(2), ['temperature' => 78.0]);

        $response = $this->get('/display/weather')->assertOk();

        // device-width is what makes the phone report its real CSS width so the
        // max-width breakpoints fire. The old hard-coded 1920 pinned every
        // device to the kiosk layout — that must be gone.
        $response->assertSee('name="viewport"', false);
        $response->assertSee('width=device-width', false);
        $response->assertSee('viewport-fit=cover', false);
        $response->assertDontSee('width=1920', false);
    }

    public function test_viewport_keeps_pinch_zoom_enabled_for_accessibility(): void
    {
        $this->storeObservation(CarbonImmutable::now('UTC')->subMinutes(2), ['temperature' => 78.0]);

        $response = $this->get('/display/weather')->assertOk();

        // WCAG 1.4.4 — never disable user zoom.
        $response->assertDontSee('user-scalable=no', false);
        $response->assertDontSee('maximum-scale', false);
    }

    public function test_stylesheet_ships_a_mobile_layer_with_the_required_breakpoints(): void
    {
        $css = file_get_contents(public_path('display/app.css'));

        $this->assertIsString($css);
        // The mobile activation breakpoint and its refinements. 1024px is the
        // activation width so that landscape phones (up to ~932px wide) also get
        // the mobile layout — an 820px breakpoint left a 821–932px dead zone
        // where the fixed kiosk shell leaked back in and caused body overflow.
        $this->assertStringContainsString('@media (max-width: 1024px)', $css);
        $this->assertStringContainsString('@media (max-width: 430px)', $css);
        $this->assertStringContainsString('orientation: landscape', $css);
        // The kiosk default cascade must remain untouched (no width media query
        // wraps it) — the fixed shell is still declared unconditionally.
        $this->assertStringContainsString('width: 1920px', $css);
    }

    public function test_mobile_layer_prevents_body_level_horizontal_scroll(): void
    {
        $css = file_get_contents(public_path('display/app.css'));

        $this->assertIsString($css);
        // Belt-and-suspenders overflow guard is present in the mobile layer.
        $this->assertStringContainsString('overflow-x: hidden', $css);
        // The phone forecast is a true four-column grid, not a horizontal
        // scroller, and later forecast days are hidden at the breakpoint.
        $this->assertStringContainsString('@media (max-width: 600px)', $css);
        $this->assertStringContainsString('grid-template-columns: repeat(4, minmax(0, 1fr))', $css);
        $this->assertStringContainsString('.forecast-day:nth-child(n + 5) { display: none; }', $css);
        $this->assertStringContainsString('overflow: hidden', $css);
    }

    public function test_installed_iphone_preserves_the_top_safe_area_at_phone_breakpoints(): void
    {
        $css = file_get_contents(public_path('display/app.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString(
            'html.iphone-standalone .display {',
            $css,
        );
        $this->assertStringContainsString(
            'padding-top: max(14px, env(safe-area-inset-top));',
            $css,
        );
        $this->assertStringContainsString(
            'html.iphone-standalone .display { padding-top: max(12px, env(safe-area-inset-top)); }',
            $css,
        );
    }

    public function test_mobile_forecast_uses_phone_specific_four_day_labels(): void
    {
        $css = file_get_contents(public_path('display/app.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('content: attr(data-phone-label)', $css);
        $this->assertStringContainsString('content: "Tomorrow"', $css);
        $this->assertStringContainsString('repeat(var(--forecast-day-count, 7), minmax(0, 1fr))', $css);
    }

    public function test_480px_layout_gives_indoor_values_a_full_width_row(): void
    {
        $css = file_get_contents(public_path('display/app.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('@media (max-width: 480px)', $css);
        $this->assertMatchesRegularExpression(
            '/\\.metric--indoor,\\s*\\.metric--battery\\s*\\{\\s*grid-column:\\s*1\\s*\\/\\s*-1;\\s*\\}/',
            $css,
        );
    }

    public function test_forecast_heading_and_grid_reflect_available_day_count(): void
    {
        $today = CarbonImmutable::now('America/New_York')->startOfDay();
        for ($day = 0; $day < 5; $day++) {
            $this->storeForecastDay($today->addDays($day));
        }

        $this->get('/display/weather')
            ->assertOk()
            ->assertSee('id="forecast-title" data-phone-label="4-Day Forecast">5-Day Forecast</span>', false)
            ->assertSee('style="--forecast-day-count: 5"', false);
    }

    public function test_mobile_layer_promotes_radar_directly_under_the_hero(): void
    {
        $css = file_get_contents(public_path('display/app.css'));

        $this->assertIsString($css);
        // The required mobile hierarchy is achieved with flex order (visual
        // only — DOM order is unchanged, so the kiosk grid is unaffected).
        $this->assertMatchesRegularExpression('/\.hero\s*\{\s*order:\s*1;\s*\}/', $css);
        $this->assertMatchesRegularExpression('/\.rail\s*\{\s*order:\s*2;\s*\}/', $css);
        $this->assertMatchesRegularExpression('/\.metrics\s*\{\s*order:\s*3;\s*\}/', $css);
    }

    public function test_map_and_timers_stay_single_instance_across_breakpoints(): void
    {
        $script = file_get_contents(public_path('display/app.js'));

        $this->assertIsString($script);

        // Exactly one Leaflet map is ever constructed, guarded against re-init.
        $this->assertSame(1, substr_count($script, 'L.map('));
        $this->assertStringContainsString('if (!el || typeof L === \'undefined\' || map) return;', $script);

        // Exactly one radar refresh timer, guarded against duplication.
        $this->assertSame(1, substr_count($script, 'setInterval(refreshRadar'));
        $this->assertStringContainsString('if (radarRefreshTimer) return;', $script);

        // Exactly one weather polling timer.
        $this->assertSame(1, substr_count($script, 'setInterval(poll,'));

        // Resize/orientation handling only nudges the existing map — it must
        // never construct a new one.
        $this->assertStringContainsString('map.invalidateSize()', $script);
        $this->assertStringContainsString("addEventListener('orientationchange'", $script);
    }
}
