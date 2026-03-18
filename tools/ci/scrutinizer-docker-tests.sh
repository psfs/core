#!/usr/bin/env bash
set -euo pipefail

if command -v docker-compose >/dev/null 2>&1; then
  DC="docker-compose"
elif docker compose version >/dev/null 2>&1; then
  DC="docker compose"
else
  echo "No Docker Compose command found (docker-compose or docker compose)"
  exit 1
fi

cleanup() {
  $DC down -v || true
}

on_error() {
  echo "Docker services status:"
  $DC ps || true
  echo "Last docker compose logs:"
  $DC logs --no-color --tail=200 || true
  cleanup
}

trap on_error ERR
trap cleanup EXIT

cat > .env <<'EOF'
HOST_PORT=8008
DEBUG=-xdebug
PHP_OPCACHE=0
EOF

$DC down -v || true
$DC up -d db redis

for i in $(seq 1 60); do
  echo "Waiting for MySQL readiness... attempt $i/60"
  if $DC exec -T db mysqladmin ping -h localhost --silent >/dev/null 2>&1; then
    echo "MySQL is ready"
    break
  fi
  sleep 2
done

echo "Running composer install inside php container..."
$DC run --rm -T php sh -lc 'cd /var/www && composer install --no-interaction --no-progress --prefer-dist'
echo "Preparing coverage directory..."
$DC run --rm -T php sh -lc 'cd /var/www && mkdir -p cache/coverage'
echo "Running PHPUnit with coverage..."
$DC run --rm -T php sh -lc 'cd /var/www && XDEBUG_MODE=coverage vendor/bin/phpunit --configuration phpunit.xml.dist --coverage-clover cache/coverage/coverage.xml --colors=never'
