# Self-hosting and operations

## Production essentials

Use a web server with PHP-FPM and a document root pointing only at `public/`. Keep source, `.env`, SQLite, sessions, logs, caches, and administrator credentials outside public downloads. Use HTTPS and production Laravel settings; the public dashboard and weather APIs intentionally have no login requirement. A private installation needs its own network/access boundary.

The bundled GitHub Actions workflow validates the application and its dependencies. Production deployment is installation-specific: provision your PHP runtime, web server, writable storage, scheduler, TLS/DNS, and backups separately.

Back up SQLite before migrations and plan code rollback and data recovery independently; rolling back code does not restore the database or saved settings.

## Collection and scheduling

| Command | Purpose | Schedule |
| --- | --- | --- |
| `php artisan weather:collect` | Latest Ambient reading and today's summary | Every minute |
| `php artisan weather:forecast` | Open-Meteo daily forecast | Hourly |
| `php artisan weather:cleanup` | Recent daily aggregation and detailed observation pruning | Daily 03:00, station time |
| `php artisan weather:status` | Observation freshness and source status | Manual check |
| `php artisan weather:demo --hours=48` | Synthetic local preview data | Disposable development only |

Install one once-per-minute `schedule:run` cron entry, as shown in the [README](../README.md#connect-your-station). Use an absolute PHP executable path. Collection and forecast jobs run in the background with overlap locks; verify subprocess support and file-cache permissions on your host. Missed ticks are not backfilled.

`routes/console.php` also records a one-minute scheduler heartbeat and clears framework caches at midnight on the first of each month, in station time. Use `php artisan schedule:list` to inspect the schedule.

`weather:status` is observation-centric: fresh/delayed observations can give a successful exit code despite forecast problems. Use `/api/v1/health` and authenticated admin source/diagnostics views to assess the wider system. `/up` proves Laravel boot, not healthy provider collection.

## Data retention and backup

Detailed observations default to ten days of retention. Cleanup rebuilds recent daily summaries before pruning. Daily summaries and forecast rows are not pruned by that retention setting. Raw Ambient payloads persist with observations until those observations are removed. Logs need their own rotation policy.

Back up the database using SQLite's online backup mechanism, or another WAL-safe procedure. Copying only the main SQLite file while live WAL writes exist can lose committed data. Protect and separately back up `.env`, the administrator sidecar, and persistent application key. Test restore on an isolated copy before depending on a backup.

Create verified backups before deployment as well as scheduled off-host backups. Each installation must supply its own backup and recovery policy.

## Troubleshooting

| Symptom | Useful next step |
| --- | --- |
| No station data | Run `weather:status`, then `weather:collect`; confirm both Ambient keys and the selected device |
| Stale/missing forecast | Run `weather:forecast`; check coordinates, provider access, and source diagnostics |
| Weather stops updating | Check cron, PHP path, scheduler heartbeat, subprocess support, permissions, and `storage/logs/` |
| Environment edits ignored | Clear config cache; saved admin settings take precedence over supported environment defaults |
| SQLite locked/read-only | Check WAL support, database-directory permissions, competing writers, and scheduler locks |
| Map is blank | Check browser provider requests, domain authorization, attribution, and connectivity |
| Map loads without radar | Check RainViewer connectivity; base-map fallback is intentional |
| Admin login unavailable | Configure email and password hash; there is no default login; inspect session storage permissions |
| PWA install prompt absent | Use HTTPS; install controls depend on browser/platform and are hidden in standalone mode |

Known implementation limits retained from the earlier baseline: reducing the forecast horizon does not delete previously stored future rows; enabling demo mode in production can report successful collection even when demo generation refuses the write. Keep production demo mode false.

## Developer validation

Normal regression and pinned producer-contract conformance checks:

```bash
npm ci
bash scripts/test.sh
./vendor/bin/pint --test
```

Use a disposable application key and database. Tests must not target a live database. The test script separately gates an inherited time-sensitive `DisplayPresenterTest::test_sparklines_expose_geometry_for_each_series` assertion: fixture timestamps and the recent-observation query use different clocks. It accepts only that known assertion, not arbitrary failures. A successful script run should not be described as every individual test passing if it reports the accepted baseline failure.

Additional inspection commands:

```bash
php artisan route:list
php artisan schedule:list
php artisan migrate:status
node --check public/display/app.js
node --check public/admin-assets/admin.js
node --check public/display/pwa.js
node --check public/service-worker.js
```
