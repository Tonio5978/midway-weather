# Requirements and compatibility

## What runs where?

**Host computer:** runs Midway Weather, collects observations, and stores them in a local SQLite database. It needs PHP (the program that runs the app), Composer (the library downloader), Internet access, and writable local storage. Keep this computer awake while you want collection to continue.

**Display:** a browser showing the host's dashboard. This can be the same Windows PC, a separate desktop, tablet, phone, or a Raspberry Pi connected to a screen. A display-only device does not need PHP, Composer, or a database. A Raspberry Pi is optional, not a requirement or a special station adapter.

**Station:** for real observations, an Ambient Weather station must already upload current readings to your own AmbientWeather.net account. Midway Weather reads that account's REST API using your API and Application keys. It does not connect directly to station hardware or import its entire historical archive. For a WS5000, verify the existing account upload first. Model-specific extra sensors are not automatically supported. You can try synthetic demo data without any station or Ambient account.

## Software and access

| Requirement | Why it is needed |
| --- | --- |
| PHP 8.3+ satisfying `composer.lock` | Runs the Laravel application; use the tested 8.3/8.4 path in the Windows guide |
| Composer 2 | Installs the exact libraries in the committed lockfile; no coding required |
| PHP extensions | SQLite via `pdo_sqlite` and `sqlite3`; cURL, mbstring, OpenSSL, fileinfo, and Laravel/locked-package requirements including DOM/XML, ctype, filter, hash, session, tokenizer and PDO. On Windows enable ZIP for package extraction. `composer check-platform-reqs` checks installed package requirements; check SQLite explicitly too. |
| Writable project/data folders | SQLite and its sidecars, file cache, logs and sessions need write access |
| Current browser with JavaScript | Renders the dashboard, polls the local app, and loads maps/radar |
| Internet access | Package downloads during setup; Ambient/Open-Meteo collection on the host; Stadia Maps/RainViewer in the browser; optional ZIP lookup |
| Keys for real station data | Your Ambient API key and Application key; station MAC recommended, required for deliberate selection among multiple devices |

SQLite is an application data file, not a separate database service. The default setup needs no Node.js/npm, frontend compilation, Redis, queue worker, Apache, IIS, MySQL, or additional database installer. Git is optional when downloading a source ZIP. Node/npm and the Bash regression script are for contributors/CI, not normal use. Do not use the developer-oriented `composer dev` command as the Windows startup path; use the explicit Artisan commands in the guide.

The project has **no measured minimum CPU, RAM, or disk specification** and no claimed low-end hardware performance guarantee. Dependencies, observation history, logs and backups consume storage; the ten-day detailed-observation default does not cap logs or all database tables. A host must be able to run the locked PHP application and store its data. Do not buy hardware based on an invented minimum.

## Compatibility and validation boundaries

- **Linux:** application regression and contract checks run in Ubuntu GitHub CI. The maintainer's hosted installation uses Linux. This is not a general-purpose production installer.
- **macOS:** fresh local dependency installation, SQLite migration, demo generation and browser startup have been exercised on the maintainer's machine.
- **Windows:** fresh native installation, SQLite, demo HTTP/API and background scheduling pass automated checks on Windows Server 2022 x64, Windows PowerShell 5.1 and PHP 8.3/8.4 with Composer 2. See the evidence and remaining limits in the [Windows guide](windows.md). Windows desktop tool installers, GUI prompts, firewall/network setup and a real WS5000 account remain untested.
- **Windows ARM, WSL, IIS/Apache, automatic Windows startup/services:** not covered or claimed validated by this guide.
- **Live station compatibility:** the application consumes Ambient account API fields, not model-specific drivers. A Windows demo test does not establish a live WS5000 hardware/account test. Supported readings are described in the [README](../../README.md#what-you-can-see); missing sensors remain unavailable.

## Choose your starting point

- **Windows, first-time setup:** [step-by-step Windows guide](windows.md), including demo, real station connection, daily startup and troubleshooting.
- **macOS/Linux, tools already installed:** [local demo commands](../../README.md#try-it-locally).
- **All platforms, settings:** [configuration](../configuration.md), [administration](../admin.md), and [map setup](../configuration.md#maps-and-radar).
- **Unattended or network-facing hosting:** [operations](../operations.md). The local PHP development server is not an Internet-facing deployment solution. HTTPS is needed for hosted PWA use; a local demo is not a full offline-weather app.
