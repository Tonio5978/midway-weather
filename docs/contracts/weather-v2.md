# Canonical Weather v2 producer

`GET /api/v2/summary/current` implements `contracts-weather/v1.0.0`.
The artifact version is 1.0.0; the API/schema major is 2.

## Pinned conformance

The exact schema archives, checksum sidecars, and semantic validator are bundled in
`contracts/vendor/`. The adjacent lockfiles preserve their upstream provenance.
Run `npm run contracts:verify` to verify checksums and extract them into the ignored
`.contracts-cache/` directory. The Weather contract is then available locally at
`.contracts-cache/contracts-weather/contracts/weather/v2/contract.md`.
No access to an upstream repository is needed for validation, and there is no
request-time schema fetch or Node production runtime.

## Source mapping

- `CanonicalSummary` reads one consistent SQLite snapshot, without provider
  calls, cache updates, domain writes or session state. Existing collection,
  normalization, retention, classification and v1/display services are unchanged.
- The latest usable observation must have a finite temperature above absolute
  zero and a trustworthy source timestamp in its retained raw provenance. Epoch
  milliseconds take precedence; otherwise a valid explicitly zoned timestamp is
  required. It must match the stored minute-rounded observation time and not be
  in the future. Legacy synthetic-now rows are excluded. Valid retained fallback
  stays within the existing configured observation-retention window; rejected
  newer rows degrade the projection. No usable observation produces the canonical
  `weather_data_unavailable` 503 envelope.
- Observation retrieval is null: neither row update time nor global successful
  poll time proves retrieval of a selected retained row. Response generation
  never renews observation/forecast freshness.
- Daily extrema are computed directly from usable current-local-day observations,
  with their own newest included observation time. The cached `daily_summaries`
  table has no covered-through provenance and is not used by v2. Day windows use
  local midnights, including 23/25-hour DST days; yesterday is never relabeled.
- Today's forecast uses its own row-specific `fetched_at`. Other forecast rows
  cannot refresh it. Invalid/future retrieval, inverted ranges or empty data
  produce a null forecast; missing individual measurements remain null.
- Source-status evidence drives operational availability separately from age.
  Missing evidence, rejected newest data, missing forecast or refresh failures
  degrade usable data. Known failed refreshes mark the retained component
  `last_known_good`; ordinary stored/stale data does not automatically set it.
- The existing 10/20-minute observation and 3-hour forecast configuration supplies
  inclusive deadlines. Interpretation inherits the earliest deadlines of every
  considered input, including a present forecast even when observed rain wins.
- Existing atmosphere precedence is reused, mapped to the published vocabulary;
  `severe-storm` becomes the qualified `thunderstorm_with_hail`. Unknown/missing
  evidence is null, never an unconditional clear claim. Raw provider codes,
  device identity, credentials, schema details and presentation severity do not
  enter the response.
- Optional advisory `interpretation.summary` is null. The legacy phrase service
  writes rotation history and uses response-anchored trends; v2 does not invoke
  it or claim its prose has proven source freshness. Existing v1 prose is intact.

The operation is public, unauthenticated and read-only, preserving valid
`X-Midway-Request-ID` values and returning `Cache-Control: no-store, max-age=0`.
Unexpected errors use a sanitized canonical 500 envelope, even in debug mode.
`GET /api/v1/health` and `/api/v1/version` implement the pinned core contracts.
Health distinguishes the critical local store from degraded Weather projection
availability. Version reads private `RELEASE.json`, reports both adopted contract
versions and the exact application release/source, and refuses fabricated
production metadata. `/up` and all existing routes remain unchanged.

## Validation

Install locked PHP dependencies and run `npm ci`, then `bash scripts/test.sh`.
Use only an in-memory or disposable database. The regression script includes
schema and semantic validation of actual application HTTP responses, plus the
separately gated inherited sparkline-clock assertion described in
[developer validation](../operations.md#developer-validation).

For a self-hosted instance, `bash scripts/smoke-contracts.sh https://your-weather-host`
validates v2 and operational responses plus v1 compatibility. Production version
responses require an installation-owned `RELEASE.json` with valid release metadata;
this public repository does not provision or deploy a production installation.
