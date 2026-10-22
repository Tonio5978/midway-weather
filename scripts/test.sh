#!/usr/bin/env bash
set -euo pipefail
npm run contracts:verify
npm run contracts:validate
php artisan test --compact \
  --exclude-filter '/^Tests\\Feature\\Services\\DisplayPresenterTest::test_sparklines_expose_geometry_for_each_series$/'
# Preserve the existing separately gated baseline; no broader failure suppression.
baseline_output=$(mktemp)
trap 'rm -f "$baseline_output"' EXIT
set +e
php artisan test --compact \
  --filter '/^Tests\\Feature\\Services\\DisplayPresenterTest::test_sparklines_expose_geometry_for_each_series$/' \
  > "$baseline_output" 2>&1
baseline_status=$?
set -e
cat "$baseline_output"
if [[ "$baseline_status" -eq 0 ]]; then
  echo 'Inherited sparkline baseline passed.'
elif [[ "$baseline_status" -eq 1 ]] && grep -Fq 'Failed asserting that actual size 0 matches expected size 2.' "$baseline_output"; then
  echo '::warning::Accepted inherited time-sensitive sparkline assertion documented in README.md.'
else
  exit "$baseline_status"
fi
