#!/usr/bin/env bash
# Runs the Swoole benchmark inside the php container (project rule: never run PHP on the host).
# Usage: scripts/bench/swoole.sh [--workers=2] [--requests=1000] [--concurrency=1,8] [--leak=0]
set -euo pipefail
cd "$(dirname "$0")/../.."
if ! up_output=$(docker compose up -d --wait php redis db 2>&1); then
  echo "$up_output" >&2
  exit 1
fi
exec docker compose exec -T php php -d xdebug.mode=off scripts/bench/swoole.php "$@"
