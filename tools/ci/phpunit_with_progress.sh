#!/bin/sh

set -u

LOG_FILE="${LOG_FILE:-/tmp/phpunit-progress.log}"
CONFIG_FILE="${CONFIG_FILE:-phpunit.xml.dist}"
COVERAGE_FILE="${COVERAGE_FILE:-cache/coverage/coverage.xml}"
UNSAFE_COVERAGE_GROUP="${UNSAFE_COVERAGE_GROUP:-xdebug_coverage_unsafe}"

mkdir -p "$(dirname "$LOG_FILE")"
mkdir -p "$(dirname "$COVERAGE_FILE")"

if command -v phpdbg >/dev/null 2>&1; then
  RUNTIME_CMD="XDEBUG_MODE=off phpdbg -qrr vendor/bin/phpunit"
  COVERAGE_CMD="$RUNTIME_CMD --configuration $CONFIG_FILE --colors=never --coverage-clover $COVERAGE_FILE --debug"
  echo "[CI] Running with phpdbg coverage: $COVERAGE_CMD"
  sh -lc "$COVERAGE_CMD" >"$LOG_FILE" 2>&1
  STATUS=$?
  cat "$LOG_FILE"
else
  COVERAGE_CMD="XDEBUG_MODE=coverage vendor/bin/phpunit --configuration $CONFIG_FILE --colors=never --coverage-clover $COVERAGE_FILE --exclude-group $UNSAFE_COVERAGE_GROUP --debug"
  UNSAFE_CMD="XDEBUG_MODE=off vendor/bin/phpunit --configuration $CONFIG_FILE --colors=never --group $UNSAFE_COVERAGE_GROUP --no-coverage --debug"

  echo "[CI] Running coverage pass (excluding group $UNSAFE_COVERAGE_GROUP): $COVERAGE_CMD"
  sh -lc "$COVERAGE_CMD" >"$LOG_FILE" 2>&1
  STATUS=$?
  cat "$LOG_FILE"
  if [ "$STATUS" -eq 0 ]; then
    echo ""
    echo "[CI] Running unsafe coverage group without coverage driver: $UNSAFE_CMD"
    sh -lc "$UNSAFE_CMD"
    STATUS=$?
  fi
fi

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
