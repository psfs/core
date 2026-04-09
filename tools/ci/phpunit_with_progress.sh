#!/bin/sh

set -u

LOG_FILE="${LOG_FILE:-/tmp/phpunit-progress.log}"
CONFIG_FILE="${CONFIG_FILE:-phpunit.xml.dist}"
COVERAGE_FILE="${COVERAGE_FILE:-cache/coverage/coverage.xml}"

mkdir -p "$(dirname "$LOG_FILE")"
mkdir -p "$(dirname "$COVERAGE_FILE")"

if command -v phpdbg >/dev/null 2>&1; then
  RUNTIME_CMD="XDEBUG_MODE=off phpdbg -qrr vendor/bin/phpunit"
else
  RUNTIME_CMD="XDEBUG_MODE=coverage vendor/bin/phpunit"
fi

CMD="$RUNTIME_CMD --configuration $CONFIG_FILE --colors=never --coverage-clover $COVERAGE_FILE --debug"

echo "[CI] Running: $CMD"
sh -lc "$CMD" >"$LOG_FILE" 2>&1
STATUS=$?

cat "$LOG_FILE"

if [ "$STATUS" -ne 0 ]; then
  echo ""
  echo "[CI] PHPUnit failed with exit code: $STATUS"
  echo "[CI] Last executed tests/events:"
  grep -E "Test (Prepared|Passed|Failed|Errored|Finished|Skipped)|Test Runner|Test Suite|Segmentation fault" "$LOG_FILE" | tail -n 60 || true
  if [ "$STATUS" -eq 139 ]; then
    echo "[CI] Segmentation fault detected (exit 139)."
  fi
fi

exit "$STATUS"
