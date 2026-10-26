# Battery status troubleshooting

The Batteries tile shows the outdoor array and indoor/console flags supplied by the Ambient **account API**. It does not measure battery voltage or query your station hardware directly. Enable **Station batteries** in Admin → Dashboard layout if the tile is hidden.

## What the app reads

The collector requests `/v1/devices` and selects your configured station's `lastData`. It maps `battout` to outdoor battery status and `battin` to indoor battery status, stores nullable booleans in SQLite, and supplies them to both the initial dashboard HTML and its periodic JSON refresh.

| Ambient value | Dashboard meaning |
| --- | --- |
| `1` or `"1"` | OK |
| `0` or `"0"` | Low |
| Field absent or `null` | Unknown, shown as `—` |

The headline is **Low battery** if either reported flag is low, **All OK** if at least one flag is reported and none is low, and `—` if neither is reported. “All OK” covers reported flags only; an absent indoor/outdoor reading still displays `—` in its own row. Missing data is never evidence of a healthy battery.

Ambient's [device data specification](https://github.com/ambient-weather/api-docs/wiki/Device-Data-Specs) says not all devices supply all fields. It also documents different flag polarity for Meteobridge uploads. Midway Weather currently uses the normal Ambient polarity above; it does not detect or invert Meteobridge flags. Other fields such as `batt1`, `batt_co2`, or `batt_lightning` refer to different sensors (some have different meanings/polarity). They are not substitutes for `battout` or `battin`, and the tile does not currently display accessory-sensor batteries.

A WS2902B model name alone does not establish which flags its account returns. A console or Ambient website indicator is useful context, but does not prove the same fields are present in the REST response. We have not yet received a WS2902B payload for the reported issue; do not infer a model-specific mapping or firmware remedy from the blank tile.

## Check without sharing secrets

1. Run `php artisan weather:status` on the host and confirm it has recent real observations, not demo data. If collection is stale, fix that first using [operations troubleshooting](../operations.md#troubleshooting).
2. Open your own `/api/display/weather` and look only at `observation.battery`. Note `outdoor_ok`, `indoor_ok` and `all_ok`. Do not attach the entire response; it also includes your display/location configuration.
3. From the application folder on a host with a Unix shell, this read-only command prints only the two relevant raw flags, whether they were present, and their stored normalized values. It uses the latest **stored** observation and makes no provider request:

   ```bash
   php artisan tinker --execute='$o = App\Models\Observation::orderByDesc("observed_at")->first(); $r = $o?->raw ?? []; dump(["has_observation" => $o !== null, "battout_present" => array_key_exists("battout", $r), "battout" => $r["battout"] ?? null, "battin_present" => array_key_exists("battin", $r), "battin" => $r["battin"] ?? null, "outdoor_ok" => $o?->battery_outdoor_ok, "indoor_ok" => $o?->battery_indoor_ok]);'
   ```

   Run this locally on the host, not by putting Ambient API keys into a browser URL. If shell access is unavailable, ask the host to run it or provide the equivalent restricted database read.

4. If the raw flags are absent, the current stored observation cannot populate those readings. If present as normal Ambient `0`/`1`, they should appear. Check whether the browser's API values agree with the stored values; refresh the dashboard and record any browser error if they disagree.

## What to include in a support reply

Send the diagnostic output above, just the three `observation.battery` values, the installed public commit/version, whether observations are fresh, and whether you use a direct Ambient upload or Meteobridge/another bridge. Say whether the console and Ambient website show battery status (OK, low, or absent). If other battery fields exist, send their **field names** first so we can identify their documented meaning.

Do not send `.env`, either API key, passwords, request URLs containing keys, the whole raw payload/database, station MAC/ID, precise coordinates, or unredacted screenshots. No credentials are needed to investigate these flags. If normal `battout`/`battin` values are present but the tile disagrees, that is actionable evidence for a parser/persistence/rendering fix. If they are missing, the next question is provider availability, not a guessed station driver.
