<?php

declare(strict_types=1);

use App\Http\Controllers\CanonicalSummaryController;
use App\Http\Controllers\CurrentSummaryApiController;
use App\Http\Controllers\DailyForecastController;
use App\Http\Controllers\DisplayApiController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\OperationalContractController;
use App\Http\Controllers\WeatherAdminController;
use App\Http\Controllers\WeatherLoginController;
use App\Http\Controllers\WeatherProvidersController;
use App\Http\Middleware\PrivateWeatherAdmin;
use App\Http\Middleware\WeatherAdminSession;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Midway Weather Routes
|--------------------------------------------------------------------------
|
| Public, unauthenticated endpoints (this is a local-network appliance):
|
|   GET /                      The server-rendered kiosk page (Blade).
|   GET /display/weather       Compatibility alias for existing installs.
|   GET /api/display/weather  The JSON view model the page polls (~every 60s).
|   GET /manifest.webmanifest Deployment-aware PWA installation metadata.
|
| The browser only ever talks to the local JSON endpoint — it never calls
| Ambient or Open-Meteo directly, so third-party API keys never reach the
| client. Both routes are read-only; there are no public mutation endpoints.
|
*/

// Weather is the application entry point. Keep the historical display path
// available so existing Home Screen installs and bookmarks continue to open.
Route::get('/', DisplayController::class)
    ->name('display.weather');

Route::get('/display/weather', DisplayController::class);

Route::get('/api/display/weather', DisplayApiController::class)
    ->name('api.display.weather');

Route::get('/api/v1/summary/current', CurrentSummaryApiController::class)
    ->name('api.v1.summary.current');

Route::get('/manifest.webmanifest', function () {
    return response()->json([
        'name' => 'Midway Weather',
        'short_name' => 'Midway',
        'description' => 'Live weather conditions, radar, and forecast for the Turner Home in Powder Springs, Georgia.',
        // Preserve the installed-app identity while moving its launch URL to /.
        'id' => url('/display/weather'),
        'start_url' => route('display.weather'),
        'scope' => rtrim(url('/'), '/').'/',
        'display' => 'standalone',
        'orientation' => 'any',
        'background_color' => '#021b39',
        'theme_color' => '#04385c',
        'icons' => [
            [
                'src' => asset('icons/midway-weather-192.png'),
                'sizes' => '192x192',
                'type' => 'image/png',
            ],
            [
                'src' => asset('icons/midway-weather-512.png'),
                'sizes' => '512x512',
                'type' => 'image/png',
            ],
            [
                'src' => asset('icons/midway-weather-maskable-512.png'),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
        ],
    ])->header('Content-Type', 'application/manifest+json');
})->name('pwa.manifest');

// Canonical read APIs have no session, cookies, CSRF state, or authentication.
Route::withoutMiddleware('web')->group(function (): void {
    Route::get('/api/v2/summary/current', CanonicalSummaryController::class);
    Route::get('/api/v1/forecast/daily', DailyForecastController::class);
    Route::get('/api/v1/health', [OperationalContractController::class, 'health']);
    Route::get('/api/v1/version', [OperationalContractController::class, 'version']);
});

// Weather administration follows the ecosystem email/password session flow.
Route::middleware(PrivateWeatherAdmin::class)->group(function () {
    Route::get('/login', [WeatherLoginController::class, 'show'])->name('login');
    Route::post('/login', [WeatherLoginController::class, 'login'])->middleware('throttle:15,1')->name('login.store');
    Route::middleware(WeatherAdminSession::class)->group(function () {
        Route::post('/logout', [WeatherLoginController::class, 'logout'])->name('logout');
        Route::get('/admin', [WeatherAdminController::class, 'index']);
        Route::post('/admin/settings', [WeatherAdminController::class, 'save']);
        Route::post('/admin/locate', [WeatherAdminController::class, 'locate'])->middleware('throttle:10,1');
        Route::get('/admin/diagnostics', [WeatherAdminController::class, 'diagnostics']);
        Route::get('/admin/providers', [WeatherProvidersController::class, 'index']);
        Route::post('/admin/providers/stations', [WeatherProvidersController::class, 'stations']);
        Route::post('/admin/providers/{provider}', [WeatherProvidersController::class, 'save'])->whereIn('provider', ['ambient', 'open_meteo']);
        Route::post('/admin/providers/{provider}/probe', [WeatherProvidersController::class, 'probe'])->whereIn('provider', ['ambient', 'open_meteo'])->middleware('throttle:6,1');
        Route::post('/admin/refresh', [WeatherAdminController::class, 'refresh'])->middleware('throttle:1,1');
    });
});
// This same-origin preview is protected, but intentionally frameable by admin.
Route::post('/admin/preview', [WeatherAdminController::class, 'preview'])
    ->middleware(WeatherAdminSession::class);
