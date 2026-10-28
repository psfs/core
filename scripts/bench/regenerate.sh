#!/usr/bin/env bash
# Profiles the regeneration of a module with many tables inside the php container (project rule: never run PHP on the host).
# Usage: scripts/bench/regenerate.sh [--tables=10,50,100] [--states=initial,regen]
set -euo pipefail
cd "$(dirname "$0")/../.."
if ! up_output=$(docker compose up -d --wait php db 2>&1); then
  echo "$up_output" >&2
  exit 1
fi
exec docker compose exec -T php php -d xdebug.mode=off scripts/bench/regenerate.php "$@"
