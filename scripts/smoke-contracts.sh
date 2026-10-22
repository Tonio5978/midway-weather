#!/usr/bin/env bash
set -euo pipefail
base_url=${1:?Usage: smoke-contracts.sh https://weather-host [expected-release] [expected-source]}
expected_release=${2:-}
expected_source=${3:-}
output_dir=${WEATHER_SMOKE_OUTPUT:-$(mktemp -d)}
mkdir -p "$output_dir"
for route in weather health version legacy forecast; do
  case "$route" in
    weather) endpoint=/api/v2/summary/current ;;
    health) endpoint=/api/v1/health ;;
    version) endpoint=/api/v1/version ;;
    legacy) endpoint=/api/v1/summary/current ;;
    forecast) endpoint=/api/v1/forecast/daily ;;
  esac
  status=$(curl --silent --show-error --connect-timeout 10 --max-time 30 \
    --header 'X-Midway-Request-ID: weather.phase2.smoke' \
    --dump-header "$output_dir/$route.headers" --output "$output_dir/$route.json" --write-out '%{http_code}' \
    "$base_url$endpoint")
  if [[ "$status" != 200 ]]; then
    echo "Hosted $endpoint returned HTTP $status; expected 200." >&2
    exit 1
  fi
  if [[ "$route" != legacy ]]; then
    if [[ "$route" != forecast ]]; then
      node scripts/validate-contracts.mjs "$route" "$output_dir/$route.json" "$status"
    fi
    jq -e '.meta.request_id == "weather.phase2.smoke"' "$output_dir/$route.json" > /dev/null
  fi
done
jq -e '.data.availability != "unavailable"' "$output_dir/health.json" > /dev/null
jq -e '.contractVersion == 1 and .kind == "weather.summary.current" and .source == "midway-weather" and (.data.temperature.value | type == "number") and .data.temperature.unit == "F" and .error == null' "$output_dir/legacy.json" > /dev/null
jq -e '.data.application == "midway-weather" and .data.contracts["contracts-weather"] == "1.0.0" and .data.contracts["contracts-core"] == "1.0.0"' "$output_dir/version.json" > /dev/null
if [[ -n "$expected_release" ]]; then
  jq -e --arg release "$expected_release" --arg source "$expected_source" '.data.release == $release and .data.source_commit == $source' "$output_dir/version.json" > /dev/null
  jq -e --arg release "$expected_release" '.data.release == $release' "$output_dir/health.json" > /dev/null
fi
jq -e '(.data.timezone | type == "string" and length > 0) and (.data.days | length >= 1 and length <= 7) and (.data.availability == "available" or .data.availability == "degraded") and all(.data.days[]; (.date | test("^[0-9]{4}-[0-9]{2}-[0-9]{2}$")) and (.retrieved_at | test("(Z|[+-][0-9]{2}:[0-9]{2})$")) and (.freshness.state == "fresh" or .freshness.state == "stale") and (.last_known_good | type == "boolean"))' "$output_dir/forecast.json" > /dev/null
curl --fail --silent --show-error --connect-timeout 10 --max-time 30 "$base_url/up" > /dev/null
# Private release metadata stays outside the public document root.
status=$(curl --silent --show-error --connect-timeout 10 --max-time 30 --output /dev/null --write-out '%{http_code}' "$base_url/RELEASE.json")
[[ "$status" = 404 || "$status" = 403 ]]
echo "Hosted daily forecast, v2 schema/semantics, operational contracts, release identity, v1 compatibility and /up passed. Evidence: $output_dir"
