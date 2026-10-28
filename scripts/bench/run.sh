#!/usr/bin/env bash
# Runs the PSFS request benchmark inside the php container (project rule: never run PHP on the host).
# Usage: scripts/bench/run.sh [--quick] [--opcache=off,on] [--modules=2,20] ...   (see --help)
set -euo pipefail
cd "$(dirname "$0")/../.."
if ! up_output=$(docker compose up -d --wait php redis db 2>&1); then
  echo "$up_output" >&2
  exit 1
fi
exec docker compose exec -T php php -d xdebug.mode=off scripts/bench/bench.php "$@"
