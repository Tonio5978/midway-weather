# Third-Party Environmental Sensor Integration

Environmental measurements can be useful alongside the weather: pool water temperature before a swim, indoor conditions, or soil measurements near a garden. Midway Weather's configurable tiles provide a common way to name and display verified readings. Provider support is a separate question from whether a tile can present a measurement.

## What ships in this public repository

- Ambient Weather observation collection and station/measurement discovery, plus Open-Meteo forecasts.
- Explicit sensor tile bindings for verified Ambient measurement channels. Administrators choose the measurement, display label, icon, size, and supported unit before publishing.
- °F/°C conversion for temperature tiles, source observation time, freshness and last-known states, and available recent measurement history.
- Available 24-hour ranges and bounded timestamped history derived from retained station packets. Missing or pruned history is not backfilled or invented.

The existing Pool Temperature tile can be assigned to a suitable Ambient temperature channel; it has no reading until configured. The software does not infer a channel's physical purpose or guarantee accessory compatibility. Indoor, numbered temperature/humidity, soil-temperature, and other verified Ambient fields may already be usable when the station and account API supply them. See [providers and sensor tile publication](providers.md) for the shipped behavior.

## Privately validated: Govee pool water temperature

A private Midway Weather deployment successfully integrated a **Govee H5109 floating pool thermometer and H5042 gateway**. It retrieves live pool water temperature through scheduled cloud collection and displays it through the existing configurable sensor tile system, with temperature conversion, timestamped historical samples, observation freshness, and available sparkline history.

This demonstrates that an external environmental measurement can join the dashboard without replacing the existing weather and forecast sources. The private deployment publishes the selected Pool Temperature presentation while keeping connector credentials, device identities, authentication state, and provider diagnostics private.

**This public repository does not contain the Govee connector, its authentication commands, or its collection/storage implementation.** The demonstration is not an installation guide, a claim of official API support, or evidence that other Govee models work. Public source and the maintainer's live deployment can differ.

## Expansion opportunities

| Direction | Status and boundary |
| --- | --- |
| Additional Ambient sensors | Verified API channels can use the shipped tile system; hardware and console compatibility must be checked for each installation. |
| Govee | The H5109/H5042 pool use case is privately validated. A public connector or broader model support is future work. |
| Ecowitt | A possible additional provider; no Ecowitt connector or model compatibility is shipped or claimed here. |
| Home Assistant | A possible integration bridge for household sensors; no Home Assistant integration is shipped, and it is not required for the current public app. |
| MQTT | A possible measurement transport; no MQTT subscriber or broker configuration is shipped. |

Future work could extend indoor temperature/humidity, pool, soil, air-quality, and other environmental measurements across providers. Each integration would need model-specific evidence, an appropriate access method, source timestamps and units, stale-data handling, and a clear boundary between public readings and private management data. Appearance in this table is an idea, not supported hardware or a delivery promise.

The existing history serves recent trends and available ranges. Full date-range exploration, historical comparisons, and CSV export remain separate enhancement opportunities, not prerequisites for displaying a live sensor reading. See [the roadmap](roadmap.md).

Suggest a use case through [GitHub issues](https://github.com/thenumber3/midway-weather/issues), including the model and a link to public API or integration documentation where available. Do not post credentials, device identifiers, private endpoints, account payloads, or household configuration.
