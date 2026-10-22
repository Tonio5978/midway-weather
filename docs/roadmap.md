# Roadmap

This page separates implemented features from possible future work, based on the repository as of October 22, 2026. The earlier V1/V2/V3 labels were an idea list, not release milestones. No dates or delivery commitments are implied below.

## Implemented

- Ambient Weather collection for one station per installation, with normalized observations and source freshness/failure tracking.
- SQLite observation history, daily summaries, configurable detailed-data retention, and recent trend/sparkline presentation.
- Open-Meteo daily forecasts, normally seven days.
- Leaflet/Stadia Maps basemap and optional RainViewer latest-frame radar. Radar is implemented; animation and public map pan/zoom are not.
- Responsive desktop, tablet, and mobile dashboard, plus PWA installation and a limited shell cache. Live weather still requires a network connection.
- Persistent tile enable/disable, ordering, supported sizes, and unsaved desktop/mobile previews.
- Authenticated administration for layout, quips/messages and condition thresholds, location/ZIP lookup, radar framing, padding, clock/date, source health, and diagnostics.
- Standard Weather and October / Fall presentation, weather/time/temperature styling, and recurring annual theme schedules.
- Public display JSON, versioned summary APIs, daily forecast API, operational health/version APIs, and scheduler heartbeat.
- Application validation CI and pinned API-contract conformance checks. Production deployment is managed separately from this public repository.

See the [README](../README.md), [admin guide](admin.md), and [architecture](architecture.md) for current behavior and boundaries.

## Extension placeholder

**Pool Temperature:** a registered, inactive-by-default tile explicitly awaiting a sensor integration. It has no live source and does not invent a reading. The registry is an extension point, not evidence of an implemented integration.

## Ideas, not implemented

| Idea | Current boundary |
| --- | --- |
| Lightning | No lightning provider, event feed, or strike visualization |
| Pollen and air quality | No collection services or dashboard tiles for these data |
| Nearby stations / multiple stations | One configured station per installation; no comparison dashboard |
| Sun-position animation | No animated solar-position visualization; existing day/night styling is presentation |
| Weather alerts | No warning feed, notifications, or severe-weather alerting service |
| Historical records explorer | Recent trends and retained daily aggregates exist; no full date-range/history browsing UI |
| Monthly climate views | No monthly climate report or dedicated visualization |
| Storm tracking | Radar shows a single recent frame; no animation, storm tracks, or projected paths |
| Scout Weather Mode | An earlier exploration idea with no defined product scope or implementation |

## Known limits to consider before expanding scope

- Display units are Fahrenheit, mph, inches, and inHg; there is no unit-selection UI.
- PWA shell caching does not support a cold offline weather navigation.
- Reducing the configured forecast horizon does not prune previously stored future forecast rows.
- The regression script separately gates an inherited clock-dependent sparkline assertion; see [developer validation](operations.md#developer-validation).
- General self-hosting needs environment-specific provisioning and backup procedures; there is no one-click production installer.

Feature suggestions are welcome through [GitHub issues](https://github.com/thenumber3/midway-weather/issues). Explain the use case before proposing a provider or architecture change; see [CONTRIBUTING.md](../CONTRIBUTING.md).
