# Midway Weather

**Your weather station, made for the room you live in.**

Midway Weather is a customizable, self-hosted household weather display built around observations from an Ambient Weather station. It brings your station readings together with Open-Meteo forecasts, a radar map, recent trends, and weather-aware messages in one readable view.

It started as a Raspberry Pi kitchen display: something you can glance at across the room, without opening a weather app or reading a console full of tiny numbers. It grew into a self-hosted application with a responsive phone view and an administration interface for arranging tiles and adding your own personality. The server can run on another machine; a Raspberry Pi is an optional display device, not a hosting requirement.

**[Explore the live dashboard](https://weather.midway-ecosystem.com/)** · **[Try it locally](#try-it-locally)** · **[Documentation](docs/README.md)**

![Live Midway Weather dashboard with October / Fall styling, station observations, radar map, and seven-day forecast](docs/screenshots/dashboard-october.png)

<details>
<summary><strong>The same dashboard on your phone</strong></summary>

The mobile layout stacks the same observations, radar, and forecast for a scrolling view. Expand the full capture below, or open the [screenshot tour](docs/screenshots/README.md).

<img src="docs/screenshots/dashboard-mobile.png" alt="Midway Weather on a phone, with stacked conditions, radar, station readings, and forecast" width="320">

</details>

## What you can see

- **Your own station observations.** Outdoor temperature, feels-like temperature, daily high and low, dew point, humidity, wind direction and gusts, rainfall, pressure, UV, solar radiation, indoor conditions, and battery status where your station supplies them.
- **The direction things are heading.** Recent-history sparklines and changes in temperature, humidity, wind, and pressure, backed by local observation history.
- **The week ahead.** A seven-day Open-Meteo forecast with conditions, high/low temperatures, and precipitation probability.
- **Weather around you.** A Leaflet map with Stadia Maps tiles and an optional latest-frame RainViewer radar overlay. Configure its location, zoom, and framing in admin.
- **A layout that fits your household.** Enable, disable, reorder, and resize supported tiles, with desktop and mobile draft previews before saving.
- **A little personality.** Edit the quip library, match messages to weather or time of day, and let the existing rotation keep them from repeating too often.
- **Seasonal atmosphere.** Choose the standard Weather palette or October / Fall, or schedule recurring annual theme ranges. Colors also respond to conditions, time of day, and temperature.
- **A phone-friendly display.** Responsive layouts and optional home-screen installation through the PWA; no separate mobile app is required.
- **Useful visibility behind the scenes.** Source freshness, collection failures, scheduler heartbeat, release identity, and diagnostics in authenticated administration.

Available readings depend on the station's sensors and API payload. Collection runs every minute; the browser normally refreshes from the local application once a minute. This is polling, rather than a streaming connection to the station.

## Make it yours

### Arrange the display before you save

The layout editor previews the actual dashboard with current weather data. Change tile order, choose supported sizes, and hide readings you don't need. Drafts leave the live household display alone until you save.

![Dashboard layout administration with a live-data preview and active tile controls](docs/screenshots/admin-layout.png)

### Change the atmosphere

The standard Weather theme and October / Fall use the same observations and layout. Automatic date ranges let a seasonal theme come and go each year.

| Standard Weather palette | Alternate tile selection |
| --- | --- |
| ![Standard Weather theme in an unsaved dashboard preview](docs/screenshots/dashboard-standard-preview.png) | ![Unsaved alternate layout with reordered humidity and fewer active tiles](docs/screenshots/dashboard-alternate-preview.png) |

These two images are authentic **unsaved admin previews** using production observations. The hero above shows the live October / Fall dashboard.

### Add your own voice

![Quip editor with editable weather messages and condition matching](docs/screenshots/admin-quips.png)

See the [complete screenshot tour](docs/screenshots/README.md) for mobile, theme schedules, map configuration, data sources, and system diagnostics. Screenshots are snapshots of real weather, not a guarantee of current conditions.

## How it works

```text
Ambient Weather station → Ambient Weather API → observation collector ─┐
                                                                      ├→ SQLite → Blade dashboard + JSON APIs
Open-Meteo daily forecast ────────────────────→ forecast collector ─────┘

Browser → local weather API (periodic refresh)
Browser → Stadia Maps + RainViewer (map and radar)
Admin   → saved layout, quips, themes, and presentation settings
```

The server collects and stores the weather independently of any open browser. If a provider fails, the display keeps the last good data and reports freshness. Ambient credentials stay on the server.

The application uses **Laravel 13, PHP, SQLite, Blade, vanilla JavaScript, CSS, and locally bundled Leaflet**. There is no frontend build step, Redis requirement, or queue worker requirement with the example configuration. Node.js is used for contract validation during development and CI.

Read the [architecture guide](docs/architecture.md) for collection, persistence, public APIs, and extension points.

## Try it locally

You need **PHP 8.3+**, **Composer 2**, the PHP extensions required by the locked dependencies, and **PDO SQLite / SQLite3**. The PHP runtime must also support Laravel's usual extensions, including mbstring, DOM/XML, cURL, and OpenSSL. Composer validates dependency requirements; ensure SQLite support is installed separately.

```bash
git clone https://github.com/thenumber3/midway-weather.git
cd midway-weather
cp .env.example .env
touch database/database.sqlite
composer install
php artisan key:generate
php artisan migrate
php artisan weather:demo --hours=48
php artisan serve
```

Open [localhost:8000](http://localhost:8000/). The demo command writes synthetic station history and forecasts to your **local database**, so you can explore without an Ambient account. It does not connect a real station. Keep demo data separate from a live installation; never run the command against production.

The database directory, `storage/`, and `bootstrap/cache/` must be writable by the PHP process. Use `composer check-platform-reqs` to check your installed dependencies against your runtime.

### Connect your station

For a live installation, start with a separate, empty SQLite database and configure `.env` using your own values:

```dotenv
AMBIENT_API_KEY=your-ambient-api-key
AMBIENT_APPLICATION_KEY=your-ambient-application-key
AMBIENT_DEVICE_MAC=AA:BB:CC:DD:EE:FF
WEATHER_STATION_NAME="My Weather"
WEATHER_LOCATION_NAME="My town"
WEATHER_LATITUDE=33.8595
WEATHER_LONGITUDE=-84.6769
WEATHER_TIMEZONE=America/New_York
WEATHER_DEMO_MODE=false
```

The coordinates above are a public town-center example. Choose your own approximate location; coordinates and location labels appear in public display data. Obtain both Ambient keys from your [Ambient Weather account](https://ambientweather.net/account). The MAC selects a station from your account; without it, the collector selects the first device. Open-Meteo needs no API key in the current integration.

```bash
php artisan optimize:clear
php artisan weather:collect
php artisan weather:forecast
php artisan weather:status
```

Keep collection running with `php artisan schedule:work` during local development. On a server, install **one** scheduler cron entry, using your actual application and PHP paths:

```cron
* * * * * cd /absolute/path/to/midway-weather && /usr/bin/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

The scheduler collects observations every minute, forecasts hourly, and aggregates/prunes history daily at 03:00 station time. It also records a heartbeat and clears framework caches monthly. See [configuration and setup](docs/configuration.md) for admin credentials, provider access, map setup, and retention.

### Administration

Open `/admin` and sign in with your configured administrator email and password. There is no default password or public registration. Local setup uses `MIDWAY_WEATHER_ADMIN_EMAIL` and `MIDWAY_WEATHER_ADMIN_PASSWORD_HASH`; [the setup guide](docs/configuration.md#administrator-setup) explains generating a hash without placing your password in shell history.

Administration manages tiles and previews, quips, seasonal schedules, location and radar, display padding and clock, weather thresholds, source health, and diagnostics. Saves use revision checks to avoid overwriting another administrator's changes. Provider credentials remain deployment configuration, rather than editable browser fields. See the [admin guide](docs/admin.md).

### Production hosting

Serve **only `public/`** through your web server, use HTTPS, set `APP_ENV=production` and `APP_DEBUG=false`, and preserve your application key. Keep `.env`, SQLite and its sidecars, logs, sessions, and admin credentials outside the public web root and version control. Configure the scheduler and backups separately.

Provision your own web server, credentials, scheduler, and backup policy. This public repository includes application validation CI; production deployment remains installation-specific. Read [self-hosting and operations](docs/operations.md) before hosting your own instance.

## Documentation

| Guide | What's inside |
| --- | --- |
| [Setup and configuration](docs/configuration.md) | Providers, SQLite, admin setup, maps, settings precedence |
| [Administration](docs/admin.md) | Layout previews, quips, themes, sources, diagnostics |
| [Architecture](docs/architecture.md) | Collection lifecycle, storage, API boundaries |
| [Operations](docs/operations.md) | Scheduling, retention, backups, troubleshooting, tests |
| [PWA behavior](docs/pwa.md) | Installation, shell caching, network requirements |
| [Weather API contracts](docs/contracts/weather-v2.md) | Canonical summary and compatibility contracts |
| [Screenshot tour](docs/screenshots/README.md) | Dashboard, mobile, and administration gallery |

See the [documentation index](docs/README.md) for the roadmap, provider references, and legacy interface references.

## Status and boundaries

Midway Weather is actively developed and used as a household weather display. It is an independent project, unaffiliated with Ambient Weather.

- The data model is **one station per installation**, with Fahrenheit, mph, inches, and inHg presentation. There is no unit-selection UI or multi-station dashboard.
- Radar shows a periodically refreshed **single frame**, without animation or public pan/zoom controls. Provider availability and usage terms apply; browsers contact map/radar providers directly.
- Pool Temperature is an extension placeholder awaiting a sensor integration, not a connected feature.
- The dashboard and read APIs are public; administration requires authentication. Put the app behind an appropriate access boundary if you need private weather data.
- PWA installation provides a convenient app shell, not full offline weather. This is not a severe-weather alerting service.
- Retention defaults to ten days of detailed observations; daily summaries persist. The UI emphasizes recent trends rather than a full historical explorer.

Feedback from weather-station owners and household-dashboard builders is welcome through [GitHub issues](https://github.com/thenumber3/midway-weather/issues).

## Contributing and license

Bug reports, feature suggestions, and focused pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) and the [roadmap](docs/roadmap.md).

Original Midway Weather code is available under the [MIT license](LICENSE), copyright 2026 Ben Turner. Bundled libraries, provider imagery, and reference screenshots retain their respective licenses and rights; see [third-party notices](THIRD_PARTY_NOTICES.md).
