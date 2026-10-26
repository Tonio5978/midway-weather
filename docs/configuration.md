# Setup and configuration

Start with the [README quick start](../README.md#try-it-locally). Use `.env.example` as your environment template; never commit the filled-in file. Commands below run from the repository root.

## Runtime and storage

New users: start with [requirements and compatibility](installation/requirements.md) and the [Windows installation guide](installation/windows.md) for tool installation and daily startup. This page is the shared settings reference.

- PHP 8.3+ and Composer 2. Install the extensions required by Composer plus PDO SQLite and SQLite3; use `composer check-platform-reqs` after installation. Development dependencies may impose stricter PHP requirements than production dependencies: the lock file is authoritative.
- SQLite is the supported database. With `DB_DATABASE` unset, the database is `database/database.sqlite`. For production, use an absolute path outside the web root and release directories.
- Copy `.env.example` to `.env` and create the database file **before `composer install`**: Composer package discovery boots the app, which checks for the settings table. Keep its parent directory writable for WAL/SHM files as well as the database itself.
- Keep `storage/` and `bootstrap/cache/` writable by PHP. Use the example's `CACHE_STORE=file`, `SESSION_DRIVER=file`, and `QUEUE_CONNECTION=sync`. The app does not ship database-backed cache/session/job tables.
- Use `APP_URL` for the actual base URL. HTTPS or localhost is required for service workers.
- Generate `APP_KEY` once per installation. Preserve it across deployments; production requires `APP_ENV=production` and `APP_DEBUG=false`.

## Provider and location settings

| Variable | Purpose |
| --- | --- |
| `AMBIENT_API_KEY` | Grants read access to your Ambient account |
| `AMBIENT_APPLICATION_KEY` | Identifies the application to Ambient |
| `AMBIENT_DEVICE_MAC` | Selects the desired station; recommended even with one device |
| `WEATHER_STATION_NAME` | Station metadata name; personalize the visible heading in Admin → Display settings → Dashboard identity |
| `WEATHER_LOCATION_NAME` | Initial map/location label and fresh-install subtitle default |
| `WEATHER_LATITUDE`, `WEATHER_LONGITUDE` | Forecast location and initial map center |
| `WEATHER_TIMEZONE` | Local clock, daily summaries, forecast dates, theme dates, and cleanup time |
| `OPEN_METEO_FORECAST_DAYS` | Requested daily forecast horizon; defaults to 7 |
| `WEATHER_OBSERVATION_RETENTION_DAYS` | Detailed observation retention; defaults to 10 |
| `WEATHER_RADAR_ENABLED` | Initial radar overlay visibility |
| `WEATHER_RADAR_ZOOM` | Initial map zoom |
| `WEATHER_RADAR_REFRESH_MINUTES` | Latest radar frame refresh interval; defaults to 10 |
| `WEATHER_RADAR_OPACITY` | Overlay opacity |
| `WEATHER_DEMO_MODE` | Synthetic collection outside production; leave false for real data |

Obtain Ambient keys from your account. Open-Meteo is keyless in this implementation. Check each provider's current terms and quotas for your intended use. Server requests need outbound access to Ambient, Open-Meteo, and the optional US ZIP lookup service. Browser requests need outbound access to the selected base-map provider and, for radar, RainViewer.

Credentials, device MAC, and raw provider payloads are not included in the public display model. Location labels and configured map coordinates **are** public. Use approximate coordinates, such as a town center.

Additional timing, timeouts/retries, freshness thresholds, trend thresholds, sparkline limits, and phrase rotation defaults are in `config/weather.php`.

## Saved settings and defaults

Dashboard identity is saved separately from station and map metadata. After it is saved, changing `WEATHER_STATION_NAME` or a map location does not replace its title/subtitle. Edit them in [Dashboard identity](admin.md#dashboard-identity); leave the subtitle blank to hide it.

Once saved in `/admin`, supported layout, map, display, theme, threshold, and quip values live in `weather_settings` and take precedence over their environment defaults. Changing an environment default will not replace a saved admin value. Use the admin interface for those edits.

After changing `.env`, clear cached configuration:

```bash
php artisan optimize:clear
```

For a production installation with finalized environment configuration, rebuild caches:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Cache clearing also clears file-cache scheduler locks. Avoid doing this during a running collector; prepare caches during a controlled maintenance window.

## Administrator setup

The app has no default credentials or registration screen. For local setup, set `MIDWAY_WEATHER_ADMIN_EMAIL` and a PHP password hash in `MIDWAY_WEATHER_ADMIN_PASSWORD_HASH` in `.env`. Do not use your email account's password.

Generate a hash interactively in Tinker; the password is entered through a hidden prompt, rather than embedded in a shell command:

```bash
php artisan tinker
```

At the Tinker prompt:

```php
password_hash(\Laravel\Prompts\password('Choose a Weather admin password'), PASSWORD_DEFAULT);
exit
```

Copy the resulting hash into `.env` **inside single quotes** so its dollar signs remain literal:

```dotenv
MIDWAY_WEATHER_ADMIN_EMAIL=admin@example.com
MIDWAY_WEATHER_ADMIN_PASSWORD_HASH='paste-the-generated-hash-here'
```

Clear configuration caches and sign in at `/admin` with the email and the password you entered. Treat the hash as sensitive configuration too.

A hosted installation can instead provision a hash-only JSON sidecar with `email` and `password_hash`, outside the public root. Set `WEATHER_ADMIN_CREDENTIALS_FILE` to its absolute path; no sidecar is loaded unless configured. Environment credentials take precedence. Restrict file access to the deployment and PHP runtime users. Rotate credentials by replacing the configured hash and clearing cached configuration; existing sessions are invalidated when credentials change.

## Maps and radar

Leaflet is bundled locally. **The default is OpenStreetMap standard raster tiles**, with a dark presentation applied only to the base layer. No map account or key is required for normal, modest household viewing, whether opened on localhost, a LAN address, or a hosted domain. The computer showing the dashboard contacts the map provider directly; it needs Internet access even when the application runs on your local network. Map imagery is not included with demo data.

```dotenv
WEATHER_BASEMAP_PROVIDER=openstreetmap
STADIA_MAPS_API_KEY=
```

Keep the visible OpenStreetMap attribution. The [OSM tile usage policy](https://operations.osmfoundation.org/policies/tiles/) requires normal browser caching and a valid browser `Referer`. Do not disable referrers, bulk-download/prefetch tiles, use offline tile downloads, or proxy tiles anonymously. Midway loads only the displayed base-map area and does not refresh it on weather polling or cache third-party tiles in its service worker. This community service is best-effort, with no SLA; access can be withdrawn. For high-traffic/public signage fleets or guaranteed availability, use an appropriately licensed provider such as Stadia with your own account. Neither provider is an offline solution.

### Optional: Stadia Maps (Alidade Smooth Dark)

**Upgrading an existing Stadia installation:** the default changes to OpenStreetMap when no provider is configured. To retain the original cartography and existing domain authorization, explicitly select Stadia in the host's `.env` before upgrading:

```dotenv
WEATHER_BASEMAP_PROVIDER=stadia
STADIA_MAPS_API_KEY=
```

Then choose the appropriate authentication method from [Stadia's documentation](https://docs.stadiamaps.com/authentication/):

| Address in the displaying browser | Stadia setup |
| --- | --- |
| `http://localhost:8000` or `http://127.0.0.1:8000` | Keyless local development is supported, with strict rate limits; browser referrers/origins must reach Stadia |
| Your own public hostname, such as `https://weather.example.com` | Register that exact domain/subdomain in your Stadia account; no key is needed in Midway |
| LAN IP, computer name, intranet, or another address without domain authorization | Use the keyless OSM default, or obtain your own Stadia tile key and set `STADIA_MAPS_API_KEY` |

`localhost` means the **displaying computer**. Opening `http://192.168.1.20:8000` on the host itself or on a phone is not localhost development. Changing `APP_URL` alone does not authorize the address in the browser. A PHP server bound to `127.0.0.1` is local-only; hosting for other devices also needs appropriate binding, firewall and web-server security. Never expose the development server to the Internet.

If you choose a key, paste **your own** value into `STADIA_MAPS_API_KEY`. This is a browser tile key: it is deliberately included in dashboard HTML, display JSON, previews, and tile requests. Anyone who can view the dashboard can inspect it. Restrict its scope/usage using Stadia's available controls, monitor quotas, and revoke it if exposed unexpectedly. Never reuse Ambient credentials, use a shared project key, or post the key in an issue. Browser-visible tile authentication is separate from server-only station credentials. Domain authentication is preferable for public websites. Check your account's current licensing and quotas.

After changing `.env`, run `php artisan optimize:clear`, restart the local server/scheduler if running, and reload the dashboard. On a managed host, rebuild its configuration cache through that host's normal release process. Provider selection is host configuration shared by the dashboard and both admin previews; it is not a saved dashboard-setting change.

### Base-map troubleshooting

- **“401 Error, Invalid Authentication” with radar still visible:** the base map and radar are separate. With Stadia selected, confirm the actual browser address, register the correct domain/subdomain or use your own key. The OSM default requires neither. Do not reset the station or database.
- **Localhost still fails:** inspect a failed tile request in browser Developer Tools → Network. Confirm the host is `tiles.stadiamaps.com` and check its HTTP status, `Origin`, and `Referer`. Privacy extensions, embedded browsers, a restrictive reverse-proxy policy, or an outdated page/service worker can interfere. Midway sets base-tile images to `strict-origin-when-cross-origin`, sending only the real origin; it does not forge headers or relay requests through a public proxy. Private admin pages retain their `no-referrer` policy for other requests, while base-tile images use the explicit origin-only policy, including iframe previews.
- **403/429, blank tiles, or “Base map unavailable”:** check provider policy/quotas, connectivity, browser extensions and hosting Content Security Policy (`img-src` must allow the selected tile host; Stadia may use retina tiles). Keep normal browser HTTP caching enabled. Avoid repeated reloads when rate-limited. A provider may return an error image, so appearance matters as well as status codes.
- **Old tiles after changing provider:** run the configuration-clear command above, wait at least one browser polling interval, then reload the page. Installed PWAs should update their shell; reopen or refresh if needed. Do not clear station data.

For an issue report, share the provider choice, browser/OS, whether the address is localhost/LAN/hostname, and the HTTP status. Redact API keys, query strings, private hostnames/IPs, precise coordinates and station identifiers from screenshots or network exports.

RainViewer metadata supplies the latest radar frame. The app refreshes that single frame, rather than animating history. A provider failure can leave the base map without radar. Public pan/zoom is disabled; set zoom and independent map-center offsets under Radar & location. Location coordinates also affect forecast collection; offsets affect only map framing. Keep all attribution visible.

## Demo and live data

`php artisan weather:demo --hours=48` writes synthetic observations, daily summaries, forecasts, and source-health rows to the configured database. It refuses production writes unless forced. Do not use `--force` on a live installation. Blank Ambient keys alone do not generate a demo.

For a disposable development instance, `WEATHER_DEMO_MODE=true` makes scheduled observation collection generate demo data. Keep it false in production. When moving from demo to real data, create a separate empty database, run migrations, configure real credentials, and collect station/forecast data. This avoids mixing synthetic history with your station history.
