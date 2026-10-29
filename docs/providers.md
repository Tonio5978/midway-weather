# Data Sources & Providers

Admin → Data Sources & Providers supports the existing **Ambient Weather** observation account and **Open-Meteo** keyless forecast. No new weather service or paid account is enabled.

## Connections

Environment management remains the default. Existing Ambient keys, device selection, forecast location/time zone, forecast horizon, collection schedule, dashboard settings and history are retained. Admin overrides are optional: Ambient requires both keys on first activation; blank replacement fields keep existing Admin keys. Overrides are encrypted with the installation's `APP_KEY`, in a separate table, never returned by Admin or public APIs, and never written to `.env`. Preserve `APP_KEY` and private SQLite backups. Switching back to environment management explicitly removes the stored override. An unreadable encrypted override fails closed instead of silently changing accounts. Map credentials remain separate.

Open-Meteo remains keyless. An Admin override can enable/disable collection and set a 1–16 day forecast horizon; the existing map/location configuration remains authoritative. The current free-service terms still apply to the installation's intended use.

Save a connection before testing. Tests verify transport/payload handling; the Ambient test also discovers account stations and measurement channels. A connection test does not refresh the persisted forecast. Scheduled and manual collection remain the paths that persist readings.

## Stations and measurements

Discovery keeps a station identity by provider + device MAC, separately from its editable name. The existing configured MAC (or the station already identified in legacy observations) remains the initial primary station; the first device is used only when neither configuration nor previous history identifies a station. An unavailable configured/previous station is never silently replaced. Other discovered stations start disabled. Enable up to eight stations, select one enabled primary, and save station settings. Revision checks prevent stale Admin sessions from overwriting each other.

The original station continues using the original `observations` and `daily_summaries` tables. Enabled stations also have station-scoped observations, unique by station and observation minute, and separate daily aggregates. Selecting another primary changes read projections for the dashboard, trends, daily comparisons and v1/v2 APIs; it does not overwrite the original station's rows or mix station histories. Old code can still read its original tables after a code rollback. Provider/station choices are stored separately from the legacy dashboard JSON.

Known measurement channels have explicit quantity/unit/range mappings in `MeasurementRegistry`. These include standard weather measurements, numbered temperature/humidity channels, soil temperature, PM2.5 and CO₂. The registry follows [Ambient's Device Data Specs](https://github.com/ambient-weather/api-docs/wiki/Device-Data-Specs); ambiguous battery/soil/other fields remain diagnostic. Numbered channels do not identify a physical use such as a pool. Unknown fields show their names and diagnostic state, without inferred units or publishable values. Existing discovered channels remain listed when absent; the last valid value and its original timestamp are retained. Invalid/missing timestamps never become a new “now” observation. Out-of-order values cannot replace a channel's newer valid reading.

Discovery does not add public tiles or expose channel inventories. The public v1/v2 schemas remain unchanged. New channels stay private until a later explicit publication action.

## Health, request budgets and retention

Admin separates:

- **Connectivity:** attempted/rejected/unreachable or successful provider contact, independent of data age.
- **Collection:** successful persistence, safe failures and per-station state; a missing primary does not stop another enabled station being stored.
- **Measurement freshness:** observation time, independent of request or discovery time. Repeated old packets remain stale.
- **Missing/invalid data:** absent channels/stations, unsupported fields or invalid values, while retaining last-good evidence.

Discovery, tests and scheduled collection share persistent SQLite request budgets and a short request mutex. Ambient account keys are limited to one request per second, application keys to three per second within the installation. HTTP failures honor bounded `Retry-After` backoff (default 60 seconds, maximum one hour); no immediate retry bypasses the budget. Legacy timeout settings remain supported; retries occur on future collection cycles. Other applications sharing the same provider keys still consume the provider's external quota. Disabled or deferred collection preserves last-good data without inventing a successful poll.

The existing detailed retention window (default ten days) also prunes station observations. Station/measurement identities and daily summaries remain. Discovery does not backfill unavailable history; historical sensor data exists only where retained raw readings contain it. This phase is additive and requires the normal backed-up migration process before collection/Admin use.

## Explicit sensor tile publication

Admin → Dashboard layout includes a Sensor-backed tiles builder. Select an enabled station and a verified measurement, choose a public label, icon, standard/wide size and units, then preview. Check **Publish this measurement on the public dashboard** and publish. A new tile receives a stable ID and is appended to the layout; configuring an existing tile preserves its ID and position. The historical `pool` ID can be bound to any verified temperature channel; the software does not infer that a numbered channel measures a pool. Unbound existing Pool tiles show an unavailable setup prompt.

Up to twelve sensor bindings can be configured. Reuse an existing binding to replace its channel. Disable a tile in the layout editor and save to remove its readings and label from both public HTML and the display JSON. Re-enabling it is an explicit publication action. Sensor configuration and channel inventories remain authenticated. Save any pending dashboard draft before publishing; concurrent configuration changes are rejected atomically.

Temperature supports °F and °C, including current values and history; other quantities retain their verified native unit. Tiles show observation timestamps and fresh/delayed/stale states separately from provider contact. Missing, invalid, disabled and unavailable data display a dash; retained last-known readings are labeled explicitly. A stale reading retains its timestamp and stale label.

Tiles show the available 24-hour min/max range and expose at most 48 timestamped samples in the display model. No continuous graph bridges collection gaps. Original-station history is reused only from retained raw packets with matching recorded station identity; station-scoped packets take precedence. Unknown, absent and out-of-range values are excluded. No historical values are invented or recovered after retention pruning. The display model gains `sensor_tiles`; versioned v1/v2 contracts remain unchanged.

The migrations are additive. Take the normal private database and APP_KEY backup, run migrations, then activate code through the established release workflow. Existing layouts, themes, tile IDs and original observations/summaries remain. Roll back code without dropping the additive tables; older releases cannot configure new sensor bindings. No production activation is implied by these review branches.

## Third-party environmental sensors

The public connectors described above are Ambient Weather and Open-Meteo. A private deployment has demonstrated a Govee H5109/H5042 pool thermometer using the configurable tile approach, but its connector is not included here. See [Third-Party Environmental Sensor Integration](environmental-sensors.md) for shipped functionality, the private proof of concept, and possible future providers or bridges.
