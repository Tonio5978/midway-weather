<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Station & Location
    |--------------------------------------------------------------------------
    |
    | Identity shown on the display. Coordinates are used both for the map
    | center and the Open-Meteo forecast request. Keep the coordinates
    | privacy-conscious (an approximate area, not an exact private address).
    |
    */

    'station_name' => env('WEATHER_STATION_NAME', 'Midway Weather'),
    'location_name' => env('WEATHER_LOCATION_NAME', 'Powder Springs, Georgia'),
    'timezone' => env('WEATHER_TIMEZONE', 'America/New_York'),

    'latitude' => (float) env('WEATHER_LATITUDE', 33.8595),
    'longitude' => (float) env('WEATHER_LONGITUDE', -84.6769),

    /*
    |--------------------------------------------------------------------------
    | Ambient Weather API
    |--------------------------------------------------------------------------
    |
    | Credentials + device identifier for the source-of-truth station data.
    | These are read server-side only and never reach the browser.
    | Timeouts and retries are kept conservative to respect the documented
    | rate limits (1 req/sec per apiKey, 3 req/sec per applicationKey).
    |
    */

    'ambient' => [
        'base_url' => env('AMBIENT_BASE_URL', 'https://rt.ambientweather.net/v1'),
        'api_key' => env('AMBIENT_API_KEY'),
        'application_key' => env('AMBIENT_APPLICATION_KEY'),
        'device_mac' => env('AMBIENT_DEVICE_MAC'),
        'timeout' => (int) env('AMBIENT_TIMEOUT', 10),
        'connect_timeout' => (int) env('AMBIENT_CONNECT_TIMEOUT', 5),
        'retry_times' => (int) env('AMBIENT_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('AMBIENT_RETRY_SLEEP_MS', 1500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Open-Meteo Forecast API
    |--------------------------------------------------------------------------
    |
    | Free, keyless forecast provider. We request only the daily fields the
    | display needs. forecast_days controls how many days the strip shows.
    |
    */

    'open_meteo' => [
        'base_url' => env('OPEN_METEO_BASE_URL', 'https://api.open-meteo.com/v1/forecast'),
        'timeout' => (int) env('OPEN_METEO_TIMEOUT', 10),
        'connect_timeout' => (int) env('OPEN_METEO_CONNECT_TIMEOUT', 5),
        'retry_times' => (int) env('OPEN_METEO_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('OPEN_METEO_RETRY_SLEEP_MS', 1500),
        'forecast_days' => (int) env('OPEN_METEO_FORECAST_DAYS', 7),
        'temperature_unit' => 'fahrenheit',
        'wind_speed_unit' => 'mph',
        'precipitation_unit' => 'inch',
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Detailed observations older than this many days are pruned by
    | `weather:cleanup`. Forecast rows and persisted daily summaries are NOT
    | pruned by this value (summaries are cheap and useful for history).
    |
    */

    'retention_days' => (int) env('WEATHER_OBSERVATION_RETENTION_DAYS', 10),

    /*
    |--------------------------------------------------------------------------
    | Collection Timing
    |--------------------------------------------------------------------------
    |
    | Documents the target cadence used by the scheduler and referenced by the
    | display for the browser polling interval. The browser polls the LOCAL
    | Laravel endpoint only — never Ambient or Open-Meteo directly.
    |
    */

    'collection' => [
        'ambient_interval_minutes' => 1,
        'forecast_interval_minutes' => 60,
        'browser_poll_seconds' => (int) env('WEATHER_BROWSER_POLL_SECONDS', 60),
    ],

    'api' => [
        'summary_ttl_seconds' => (int) env('WEATHER_SUMMARY_TTL_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Current Radar
    |--------------------------------------------------------------------------
    |
    | The kiosk requests RainViewer's public metadata directly and displays
    | only its single newest past frame. The regional default intentionally
    | shows approaching weather rather than street-level detail.
    |
    */

    'radar' => [
        'enabled' => (bool) env('WEATHER_RADAR_ENABLED', true),
        'metadata_url' => 'https://api.rainviewer.com/public/weather-maps.json',
        'zoom' => (int) env('WEATHER_RADAR_ZOOM', 8),
        'refresh_minutes' => (int) env('WEATHER_RADAR_REFRESH_MINUTES', 10),
        'opacity' => (float) env('WEATHER_RADAR_OPACITY', 0.65),
    ],

    /*
    |--------------------------------------------------------------------------
    | Staleness Thresholds
    |--------------------------------------------------------------------------
    |
    | How old the most recent observation may be before the display flags it.
    | The station reports every ~1 min and we poll every minute, so "fresh"
    | comfortably covers a couple of missed polls.
    |
    |   fresh   : <= fresh_minutes old
    |   delayed : between fresh_minutes and stale_minutes old
    |   stale   : >  stale_minutes old
    |
    | The forecast is flagged stale once it exceeds forecast_stale_hours.
    |
    */

    'staleness' => [
        'fresh_minutes' => (int) env('WEATHER_FRESH_MINUTES', 10),
        'stale_minutes' => (int) env('WEATHER_STALE_MINUTES', 20),
        'forecast_stale_hours' => (int) env('WEATHER_FORECAST_STALE_HOURS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trend Thresholds
    |--------------------------------------------------------------------------
    |
    | Minimum change over the comparison window for a trend to be considered
    | meaningful rather than sensor noise. Below the threshold the trend is
    | reported as "Steady". Units match the stored observation units.
    |
    |   temperature  : °F  (compared over 1 hour and since local midnight)
    |   pressure     : inHg (compared over 3 hours)
    |   humidity     : %   (compared over 1 hour)
    |   wind         : mph (10-minute average vs. 1 hour ago)
    |
    */

    'trends' => [
        'temperature_delta_f' => (float) env('WEATHER_TREND_TEMP_F', 0.5),
        'pressure_delta_inhg' => (float) env('WEATHER_TREND_PRESSURE_INHG', 0.02),
        'humidity_delta_pct' => (float) env('WEATHER_TREND_HUMIDITY_PCT', 2.0),
        'wind_delta_mph' => (float) env('WEATHER_TREND_WIND_MPH', 2.0),

        // Comparison windows (minutes). Observations are matched to the point
        // nearest each window, within tolerance_minutes, else the trend is null.
        'windows' => [
            'temperature_minutes' => 60,
            'pressure_minutes' => 180,
            'humidity_minutes' => 60,
            'wind_minutes' => 60,
        ],
        'tolerance_minutes' => (int) env('WEATHER_TREND_TOLERANCE_MINUTES', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Display Settings
    |--------------------------------------------------------------------------
    |
    | Presentation-only knobs. sparkline_points caps how many recent
    | observations feed each sparkline; sparkline_hours bounds how far back
    | those points may range. Phrase rotation keeps the household copy stable
    | between polls while preventing a small set of lines from feeling stuck.
    |
    */

    'display' => [
        'sparkline_points' => (int) env('WEATHER_SPARKLINE_POINTS', 48),
        'sparkline_hours' => (int) env('WEATHER_SPARKLINE_HOURS', 24),
        'phrase_rotation_minutes' => 45,
        'phrase_history_size' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Mode
    |--------------------------------------------------------------------------
    |
    | When true, collection commands generate realistic fixture data instead of
    | calling the real APIs, so the dashboard is viewable before credentials are
    | configured. Guarded against production in the commands themselves.
    |
    */

    'demo_mode' => (bool) env('WEATHER_DEMO_MODE', false),

];
