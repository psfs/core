#!/usr/bin/env bash
set -u

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p cache/coverage

QUALITY_WORKDIR=".quality-artifacts"
mkdir -p "$QUALITY_WORKDIR"

PHPUNIT_OUTPUT="$QUALITY_WORKDIR/phpunit-output.txt"
SCRUTINIZER_FILE="$QUALITY_WORKDIR/scrutinizer.json"
QUALITY_JSON="quality-report.json"
QUALITY_MD="quality-report.md"

write_scrutinizer_placeholder() {
  local note="$1"
  cat > "$SCRUTINIZER_FILE" <<EOF
{
  "delta": null,
  "note": "$note"
}
EOF
}

is_numeric_delta() {
  [[ "${1:-}" =~ ^-?[0-9]+([.][0-9]+)?$ ]]
}

write_scrutinizer_delta() {
  local delta="$1"
  local note="$2"
  if ! is_numeric_delta "$delta"; then
    write_scrutinizer_placeholder "Invalid SCRUTINIZER_DELTA format"
    return
  fi
  cat > "$SCRUTINIZER_FILE" <<EOF
{
  "delta": ${delta},
  "note": "$note"
}
EOF
}

if [[ -n "${SCRUTINIZER_DELTA:-}" ]]; then
  write_scrutinizer_delta "${SCRUTINIZER_DELTA}" "${SCRUTINIZER_NOTE:-Delta provided from CI environment}"
elif [[ -s "$SCRUTINIZER_FILE" ]] && grep -q '"delta"' "$SCRUTINIZER_FILE"; then
  :
else
  write_scrutinizer_placeholder "No Scrutinizer token/data configured in CI"
fi

PHP_CONTAINER_ID="$(docker compose ps -q php 2>/dev/null || true)"
PHPUNIT_EXIT=0

if [[ -n "$PHP_CONTAINER_ID" ]]; then
  set +e
  docker exec "$PHP_CONTAINER_ID" sh -lc \
    "cd /var/www && if [ ! -x vendor/bin/phpunit ]; then echo 'vendor/bin/phpunit not found' && exit 2; fi && XDEBUG_MODE=coverage vendor/bin/phpunit" \
    2>&1 | tee "$PHPUNIT_OUTPUT"
  PHPUNIT_EXIT=${PIPESTATUS[0]}
  set -e

  docker exec "$PHP_CONTAINER_ID" sh -lc \
    "cd /var/www && php tools/quality/generate-quality-report.php --phpunit-output=$PHPUNIT_OUTPUT --coverage=cache/coverage/coverage.xml --scrutinizer=$SCRUTINIZER_FILE --output-json=$QUALITY_JSON --output-md=$QUALITY_MD" \
    || true
else
  echo "php service container not found; generating report without runtime metrics" > "$PHPUNIT_OUTPUT"
  if command -v php >/dev/null 2>&1; then
    php tools/quality/generate-quality-report.php \
      --phpunit-output="$PHPUNIT_OUTPUT" \
      --coverage="cache/coverage/coverage.xml" \
      --scrutinizer="$SCRUTINIZER_FILE" \
      --output-json="$QUALITY_JSON" \
      --output-md="$QUALITY_MD" || true
  else
    cat > "$QUALITY_JSON" <<EOF
{
  "generated_at": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "summary": {
    "tests": null,
    "assertions": null,
    "time_seconds": null,
    "memory": null,
    "coverage_line_rate": null,
    "coverage_percent": null
  },
  "scrutinizer": {
    "delta": null,
    "note": "No runtime and no local php available to build report"
  },
  "hotspots": [],
  "notes": [
    "quality report fallback generated"
  ]
}
EOF
    cat > "$QUALITY_MD" <<EOF
# Quality Report

- Runtime data unavailable.
- Scrutinizer delta: \`null\`
EOF
  fi
fi

echo "quality-report generated: $QUALITY_JSON and $QUALITY_MD"
echo "phpunit exit code: $PHPUNIT_EXIT (non-blocking)"
exit 0
