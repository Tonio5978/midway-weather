# Install Midway Weather on Windows

This guide runs Midway Weather on your Windows computer and opens it in a browser on that same computer. You do not need to write code. You do need to install PHP and Composer, edit a text file, and keep two terminal windows open for live station updates.

**What has been tested:** automated installation on **Windows Server 2022 x64**, using **Windows PowerShell 5.1, PHP 8.3 and 8.4, and Composer 2**: fresh ZIP-source installation, dependency/platform checks, both SQLite database paths (including spaces), migrations, demo HTML/API, and `schedule:work` with a completed background demo collection. See [Windows CI results](https://github.com/thenumber3/midway-weather/actions/workflows/windows-install.yml).

**What has not been tested:** clicking through PHP/Composer installation or rendering the dashboard in a Windows 10/11 desktop browser, interactive administrator password prompts, Windows firewall/LAN access, automatic startup, or connecting a physical WS5000 account on Windows. The CI runner has tools provisioned automatically; that is useful native Windows runtime evidence, not a complete consumer-desktop certification. See [requirements and compatibility](requirements.md).

## Before you start

- Read [what runs where and what you need](requirements.md). No Raspberry Pi or new weather station is required to try the demo.
- Use a fresh folder. Do not overwrite an existing installation or its `.env` and database.
- Commands below are for **Windows PowerShell**, opened from Start. Run each command in order; stop if it reports an error and use [troubleshooting](#troubleshooting).

## 1. Install PHP and Composer

**PHP** runs the application. **Composer** downloads its PHP libraries. Neither is a weather-station driver.

1. From the [official PHP Windows downloads](https://www.php.net/downloads.php?os=windows), choose a supported PHP **8.3 or 8.4, x64, Non Thread Safe (NTS) ZIP** for an Intel/AMD 64-bit PC. Do not choose the source code or debug pack. This guide does not cover Windows on ARM.
2. Follow PHP's [Windows prerequisites](https://www.php.net/manual/en/install.windows.manual.php), including the matching Microsoft Visual C++ runtime. Extract the PHP ZIP to `C:\Tools\php` so that `C:\Tools\php\php.exe` exists.
3. In that folder, copy `php.ini-development` to **`php.ini`**. Turn on File Explorer's **View → Show → File name extensions** so you do not accidentally create `php.ini.txt`.
4. Open `php.ini` in Notepad. Set `extension_dir` and enable the following lines by removing a leading semicolon (`;`). Change existing lines; do not add duplicates:

   ```ini
   extension_dir = "C:\Tools\php\ext"
   extension=curl
   extension=fileinfo
   extension=mbstring
   extension=openssl
   extension=pdo_sqlite
   extension=sqlite3
   extension=zip
   ```

5. PHP also needs trusted HTTPS certificates to download packages and contact weather providers. Download the [CA certificate bundle from curl](https://curl.se/ca/cacert.pem) and save it as `C:\Tools\php\cacert.pem`. In `php.ini`, set:

   ```ini
   curl.cainfo = "C:\Tools\php\cacert.pem"
   openssl.cafile = "C:\Tools\php\cacert.pem"
   ```

   Keep this bundle updated from its official source. Do not disable HTTPS certificate verification.

6. Search Start for **Edit environment variables for your account**. Edit your user **Path**, add `C:\Tools\php`, and save. Close PowerShell and open a new window so it sees the change.
7. Download and run **Composer-Setup.exe** from the [official Composer installation instructions](https://getcomposer.org/doc/00-intro.md#installation-windows). When asked for PHP, choose `C:\Tools\php\php.exe`. Reopen PowerShell after installation.
8. Check the tools:

   ```powershell
   php -v
   php --ini
   php -m
   composer --version
   ```

   PHP should report 8.3 or 8.4, Composer should report version 2, and `php --ini` should show your `C:\Tools\php\php.ini`. In `php -m`, check for `curl`, `fileinfo`, `mbstring`, `openssl`, `PDO`, `pdo_sqlite`, `sqlite3`, and `zip`. Composer will check the remaining locked dependency requirements during installation. SQLite is a file-based database: no separate database server or SQLite desktop application is needed.

## 2. Download Midway Weather

No GitHub account, Git, Node.js, or npm is needed for this ZIP-based installation.

In PowerShell, download the public source into a new folder under your Windows user profile:

```powershell
New-Item -ItemType Directory -Path "$HOME\Midway" -Force | Out-Null
Set-Location "$HOME\Midway"
Invoke-WebRequest -UseBasicParsing -Uri "https://github.com/thenumber3/midway-weather/archive/refs/heads/main.zip" -OutFile "midway-weather.zip"
Expand-Archive -Path ".\midway-weather.zip" -DestinationPath "."
Set-Location ".\midway-weather-main"
```

If `midway-weather-main` already exists, stop and use a different empty parent folder. Keep the project outside OneDrive-synced folders, `Program Files`, and other protected folders. You should now see `artisan`, `composer.json`, and the `public` folder when you run `Get-ChildItem`.

## 3. Prepare the local demo

In that same PowerShell window:

```powershell
Copy-Item .env.example .env
New-Item -ItemType File -Path .\database\database.sqlite
composer install --prefer-dist
composer check-platform-reqs
php artisan key:generate
php artisan migrate
php artisan weather:demo --hours=48
php artisan weather:status
```

The `.env` file stores settings and secrets. The SQLite file stores observations and saved settings. Create both files **before** `composer install`, because installing libraries also starts part of the application. Run `composer install`, not `composer update`; the lockfile selects the versions used by this project. Do not use `--ignore-platform-reqs` to hide a missing extension.

The demo command creates synthetic observations and seven forecast days. It does not connect to your station or need Ambient keys. Empty Ambient keys alone do not create demo readings. This is a one-time preview: its readings will eventually be marked stale, which is expected.

Start the application:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Leave this window open and visit **[http://127.0.0.1:8000/](http://127.0.0.1:8000/)** in Edge, Chrome, or another current browser. You should see the dashboard with demo readings and a forecast. The default base map uses OpenStreetMap without a map account or key; it needs Internet access in the displaying browser. Radar uses RainViewer separately. A map failure does not mean PHP or station collection failed. See [map setup and 401 troubleshooting](../configuration.md#maps-and-radar) if you selected Stadia or tiles fail to load.

`127.0.0.1` and `localhost` mean **this computer**. They will not open this installation from a phone or another computer. The built-in PHP server is for local use, not an Internet-facing hosting service. Do not forward its port on your router.

## 4. Connect your Ambient Weather station

For a WS5000 or another Ambient station, the important requirement is that **your station already uploads current observations to your AmbientWeather.net account**. Midway Weather reads that account's REST API. It does not read a USB connection, console IP address, or Wunderground key, and it does not replace the station's existing upload setup. Individual readings depend on what the account's API actually returns; additional sensors are not automatically supported.

1. Sign in to [AmbientWeather.net](https://ambientweather.net/) and check that your own station shows current readings. Resolve missing/stale readings there first. Use Ambient's [station support](https://ambientweather.com/support/ambient-weather-network-support/) for console/account setup.
2. Open your [Ambient account settings](https://ambientweather.net/account). Create your own **API key** and **Application key**. Ambient explains [API keys](https://ambientweather.com/faqs/question/view/id/1834/) and [application keys](https://ambientweather.com/faqs/question/view/id/1934/). Both are needed; keep them private. If your account does not offer an Application key, follow Ambient's application-key guidance or contact Ambient support before proceeding; a third-party app's shared key is not provided by this project.
3. Find the station's device MAC in your Ambient station/device information. This identifies the station in the account; it is not your Windows PC's network adapter MAC. If your account has exactly one station, you may initially leave `AMBIENT_DEVICE_MAC` blank: the application selects the first device. With multiple stations, set the desired MAC explicitly.
4. Stop the local server with **Ctrl+C**. If a scheduler window is running, stop that too. Keep the demo database as a preview and create a **new, empty database** for real station data:

   ```powershell
   New-Item -ItemType File -Path .\database\station.sqlite
   $stationDb = (Join-Path (Get-Location).Path 'database\station.sqlite').Replace('\', '/')
   'DB_DATABASE="' + $stationDb + '"'
   ```

   The last command prints a setting with your actual full path. Copy that entire line for the next step. It uses forward slashes so Windows backslashes are not treated as escapes inside `.env` quotes. Do not overwrite an existing station database.

5. Open the settings file:

   ```powershell
   notepad .env
   ```

   Add the printed `DB_DATABASE="..."` line. Change the existing station settings below to **your own values**, keeping only one line per setting. Keep `APP_ENV=local`, `APP_URL=http://localhost:8000`, and the generated `APP_KEY` for this local setup.

   ```dotenv
   AMBIENT_API_KEY=paste-your-own-api-key
   AMBIENT_APPLICATION_KEY=paste-your-own-application-key
   AMBIENT_DEVICE_MAC=AA:BB:CC:DD:EE:FF
   WEATHER_STATION_NAME="My Weather"
   WEATHER_LOCATION_NAME="My town"
   WEATHER_LATITUDE=33.8595
   WEATHER_LONGITUDE=-84.6769
   WEATHER_TIMEZONE=America/New_York
   WEATHER_DEMO_MODE=false
   ```

   The MAC is an example: replace it or leave the value empty as described above. The coordinates are a public town-center example, not a detected location. Use your own approximate latitude/longitude and [PHP timezone name](https://www.php.net/manual/en/timezones.php). Location labels and map coordinates are visible in public display data. Never post your `.env`, keys, station identifiers, database, or unfiltered diagnostics to Reddit or an issue.

6. Save the file and run:

   ```powershell
   php artisan optimize:clear
   php artisan migrate
   php artisan weather:collect
   php artisan weather:forecast
   php artisan weather:status
   ```

   Collection should say **Stored observation**; forecast refresh should report stored forecast days. Status should show a recent observation and `Demo mode: off`. Check the temperature against your Ambient account and read both the Ambient and Forecast status sections. A successful `weather:status` exit alone does not prove the forecast is healthy. Do not run `weather:demo` against this real database.

## 5. Keep it updating and start it again tomorrow

For live station use, keep **two PowerShell windows** open, both in the project folder:

**Window 1 — serve the dashboard:**

```powershell
Set-Location "$HOME\Midway\midway-weather-main"
php artisan serve --host=127.0.0.1 --port=8000
```

**Window 2 — collect weather in the background:**

```powershell
Set-Location "$HOME\Midway\midway-weather-main"
php artisan schedule:work
```

If you chose a different folder, use that path. The scheduler waits for the next minute, collects station readings every minute, and refreshes forecasts hourly. It also runs daily cleanup and records a heartbeat. Leave both windows running and keep the computer awake and connected to the Internet. Closing the browser does not stop collection; closing either terminal or putting the host to sleep interrupts its work.

To stop, press **Ctrl+C** in each window. To start again after a reboot, repeat just these two startup commands and reopen the browser. Your data stays in the database file. Do not rerun setup, regenerate the application key, or recreate the database each day. This guide does not configure a Windows service, automatic startup, or Task Scheduler.

## Optional administration and another display

Normal viewing needs no login. `/admin` has **no default password**: follow [administrator setup](../configuration.md#administrator-setup) if you want to customize the dashboard title, subtitle, icon, tiles, messages, or maps. The visible heading is configured in **Display settings → Dashboard identity**, separately from the station metadata in `.env`. Password-prompt interaction on a consumer Windows desktop is not yet manually tested.

A different screen only needs a browser; it does not need PHP or Composer. A different computer/phone needs a reachable address for the **host**, not its own `localhost`. Network/firewall setup, HTTPS, and unattended hosting are separate from this same-PC guide; see [self-hosting and operations](../operations.md). Native Windows LAN hosting and firewall dialogs have not been manually validated. Keep this local server off the public Internet.

## Troubleshooting

| What you see | What to do |
| --- | --- |
| `php` or `composer` is not recognized | Reopen PowerShell after changing Path/installing Composer. Run `Get-Command php` and `Get-Command composer`; check that they use the intended installation. |
| Missing DLL / Visual C runtime error | Install the matching runtime from PHP's official prerequisites. Keep the complete extracted PHP distribution together. Do not download individual DLLs from unofficial sites. |
| `could not find driver`, missing `ext-*`, or SQLite errors | Check `php --ini` and `php -m`. Enable the extension in that loaded file and use the same PHP for Composer and Artisan. Run `composer check-platform-reqs` again. |
| Certificate / `curl error 60` | Check both certificate paths in `php.ini`, the CA bundle file, and the Windows clock. A managed PC may need its administrator's trusted corporate CA. Never disable TLS verification. |
| `vendor/autoload.php` missing | Run `composer install --prefer-dist` from the folder containing `artisan`, and fix its first error before continuing. |
| Application key missing / `.env` changes ignored | Check the filename is `.env`, not `.env.txt`. Generate the key once during setup. After edits run `php artisan optimize:clear`, then restart server and scheduler. |
| Database missing / no tables / read-only | Create the database before Composer, run migrations, and check the configured path. Your account needs write access to the database directory, `storage`, and `bootstrap/cache`. Avoid protected/synced folders. |
| `Address already in use` on port 8000 | Stop an older server with Ctrl+C or use `--port=8001`, set `APP_URL=http://localhost:8001`, clear config, and open that port instead. |
| Browser cannot connect | The serving terminal must still be running. Use the exact printed local address, including `http://` and port. Another device's localhost is a different machine. |
| Demo works but live collection fails | Verify current data on AmbientWeather.net, both keys, `WEATHER_DEMO_MODE=false`, the station MAC, and an empty real-data database. A PC MAC, Wunderground key, or station ID will not substitute. |
| HTTP 401/403, no devices, or MAC not found | Recheck account/key/device selection. Follow Ambient's key guidance; do not paste keys into browser URLs or public support requests. |
| HTTP 429 | Rate limited. Stop duplicate schedulers/collectors and wait before retrying. Do not keep generating new keys or repeatedly polling. |
| Readings go stale | Check the scheduler terminal and host sleep/network state, then `weather:status`. Browser refresh alone does not collect station readings. |
| Radar/map blank | Check provider/domain authorization, connectivity, and attribution requirements in [map setup](../configuration.md#maps-and-radar). Map providers are separate from the station API. |
| `/api/v1/version` unavailable locally | Production release metadata is not generated by this local guide. Use the dashboard, display API, and `weather:status` to validate local data. |

If you need help, open a [GitHub issue](https://github.com/thenumber3/midway-weather/issues) with the step that failed, Windows/PHP/Composer versions, and a **sanitized** error excerpt. Do not attach a whole log or settings file. See [CONTRIBUTING](../../CONTRIBUTING.md).
