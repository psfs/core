#!/usr/bin/env bash
# Basic comparison of three ways to serve the same PSFS application: php -S, Swoole (psfs:swoole:start) and
# nginx + php-fpm with the configuration of a real project.
#
# Usage: scripts/bench/runtimes.sh --docker-config=DIR [--requests=2000] [--concurrency=1,8,32] [--workers=2]
#                                  [--max-request=1000000] [--php-cpus=1] [--nginx-cpus=0.5]
#
# DIR is the `docker` directory of the project (it holds nginx/*.conf and php/php.ini + php/www.conf). Nothing of it is
# copied into the repository: modified copies go under cache/ and are removed at the end.
#
# The servers run in the php container (limited to --php-cpus, as in production); nginx has its own container and the
# load generator a third one, so the generator does not take CPU from the server.
set -euo pipefail
cd "$(dirname "$0")/../.."

CONFIG=""; REQUESTS=2000; CONCURRENCY="1,8,32"; WORKERS=2; MAX_REQUEST=1000000; PHP_CPUS=1; NGINX_CPUS=0.5
for argument in "$@"; do
  case "$argument" in
    --docker-config=*) CONFIG="${argument#*=}" ;;
    --requests=*) REQUESTS="${argument#*=}" ;;
    --concurrency=*) CONCURRENCY="${argument#*=}" ;;
    --workers=*) WORKERS="${argument#*=}" ;;
    --max-request=*) MAX_REQUEST="${argument#*=}" ;;
    --php-cpus=*) PHP_CPUS="${argument#*=}" ;;
    --nginx-cpus=*) NGINX_CPUS="${argument#*=}" ;;
    *) echo "Unknown option $argument" >&2; exit 2 ;;
  esac
done
if [[ ! -f "$CONFIG/nginx/default.conf" || ! -f "$CONFIG/php/php.ini" || ! -f "$CONFIG/php/www.conf" ]]; then
  echo "--docker-config must point to a directory with nginx/default.conf, php/php.ini and php/www.conf" >&2
  exit 2
fi

docker compose up -d --wait php redis db >/dev/null
PHP_CONTAINER=$(docker compose ps -q php | head -1)
PHP_NAME=$(docker inspect --format '{{.Name}}' "$PHP_CONTAINER" | tr -d /)
NETWORK=$(docker inspect --format '{{range $name, $_ := .NetworkSettings.Networks}}{{$name}}{{end}}' "$PHP_CONTAINER")
GENERATOR_IMAGE=$(docker inspect --format '{{.Config.Image}}' "$PHP_CONTAINER")
SANDBOX_REL=cache/bench-runtimes
SANDBOX=/var/www/$SANDBOX_REL
WORK_REL=cache/bench-runtimes-conf
WORK="$PWD/$WORK_REL"
NGINX_NAME=psfs-bench-nginx
RESULTS=$(mktemp)
DATABASE=""

php_exec() { docker exec "$PHP_CONTAINER" "$@"; }
generator() {
  docker run --rm -i --network "$NETWORK" -v "$PWD:/var/www" -w /var/www --entrypoint php "$GENERATOR_IMAGE" -d xdebug.mode=off scripts/bench/runtimes.php "$@"
}

cleanup() {
  docker rm -f "$NGINX_NAME" >/dev/null 2>&1 || true
  php_exec pkill -f "php-fpm: master" >/dev/null 2>&1 || true
  php_exec pkill -f "psfs:swoole:start" >/dev/null 2>&1 || true
  php_exec pkill -f "S 0.0.0.0:81" >/dev/null 2>&1 || true
  # --cpus=0 does not remove a limit that was set before: give the container all the CPUs of the VM
  docker update --cpus="$(docker info --format '{{.NCPU}}')" "$PHP_CONTAINER" >/dev/null 2>&1 || true
  php_exec php -d xdebug.mode=off scripts/bench/runtimes.php clean --path="$SANDBOX" --db="$DATABASE" >/dev/null 2>&1 || true
  rm -rf "$WORK" "$RESULTS"
}
trap cleanup EXIT

echo "preparing the sandbox ($SANDBOX_REL) ..." >&2
DATABASE=$(php_exec php -d xdebug.mode=off scripts/bench/runtimes.php prepare --path="$SANDBOX" | tail -1 | sed -E 's/.*"db":"([^"]*)".*/\1/')

# --- configuration of the project, adapted to the sandbox: same settings, other paths and the benchmark switch
rm -rf "$WORK" && mkdir -p "$WORK/nginx"
cp "$CONFIG"/nginx/*.conf "$WORK/nginx/"
cp "$CONFIG/php/php.ini" "$WORK/php.ini"
sed -i.bak -E "s#root +/var/www/html;#root $SANDBOX/html;#" "$WORK/nginx/default.conf"
sed -i.bak -E "s#fastcgi_pass +[A-Za-z0-9_.-]+:9000;#fastcgi_pass $PHP_NAME:9000;#" "$WORK/nginx/fastcgi.conf"
cp "$CONFIG/php/www.conf" "$WORK/www.conf"
{
  echo 'clear_env = no'
  echo 'env[PSFS_API_AUTH_ALLOW_UNCONFIGURED] = true'
  echo 'env[PSFS_LOG_OUTPUT] = file'
  echo 'env[PSFS_LOG_FORMAT] = line'
} >> "$WORK/www.conf"
cat > "$WORK/php-fpm.conf" <<FPM
[global]
daemonize = no
error_log = $SANDBOX/fpm-master.log
include = $SANDBOX/../bench-runtimes-conf/www.conf
FPM
INI="$SANDBOX/../bench-runtimes-conf/php.ini"

# the environment the project gives its php container (docker-compose defaults)
PHP_ENV=(-e PHP_OPCACHE=1 -e PHP_TIMEZONE=Europe/Madrid -e PHP_SHOWERRORS=Off -e PHP_COOKIE_SECURE=1 -e PHP_MEMORY_LIMIT=128M
         -e PHP_ERROR_REPORTING=E_ALL -e PSFS_API_AUTH_ALLOW_UNCONFIGURED=true -e PSFS_LOG_OUTPUT=file -e PSFS_LOG_FORMAT=line
         -e PSFS_LOG_LEVEL= -e UI_DEV_UPSTREAM= -e ADMIN_UI_DEV_UPSTREAM=)

docker update --cpus="$PHP_CPUS" "$PHP_CONTAINER" >/dev/null

measure() { # runtime base-url
  local runtime=$1 base=$2 scenario path marker level info
  generator wait --url="$base/BENCHPAGE" --marker=BENCH-PAGE-OK
  info=$(generator info --url="$base/bench-info.php" | head -1)
  [[ $info == \{* ]] || info="not reported (the runtime does not execute PHP files from html/)"
  echo "  settings: $info" >&2
  for scenario in page api-get; do
    if [[ $scenario == page ]]; then path=/BENCHPAGE; marker=BENCH-PAGE-OK; else path=/client/api/test/1; marker='"success":true'; fi
    generator wait --url="$base$path" --marker="$marker"
    IFS=, read -ra levels <<< "$CONCURRENCY"
    for level in "${levels[@]}"; do
      echo "  $scenario concurrency=$level ..." >&2
      generator load --url="$base$path" --marker="$marker" --requests="$REQUESTS" --concurrency="$level" \
        --scenario="$scenario" --runtime="$runtime" >> "$RESULTS"
    done
  done
}

fresh_sandbox() { php_exec sh -c "rm -rf $SANDBOX/cache/* $SANDBOX/sessions/* && chmod -R a+rwX $SANDBOX"; }

echo "[php -S] ..." >&2
fresh_sandbox
docker exec -d -w "$SANDBOX" "${PHP_ENV[@]}" "$PHP_CONTAINER" php -c "$INI" -d xdebug.mode=off -d opcache.enable_cli=1 -S 0.0.0.0:8101 -t html
measure "php -S" "http://$PHP_NAME:8101"
php_exec pkill -f "S 0.0.0.0:8101" || true

echo "[swoole x$WORKERS] ..." >&2
fresh_sandbox
docker exec -d -w "$SANDBOX" "${PHP_ENV[@]}" "$PHP_CONTAINER" php -c "$INI" -d xdebug.mode=off -d opcache.enable_cli=1 \
  src/bin/psfs psfs:swoole:start --host=0.0.0.0 --port=8102 --workers="$WORKERS" --max-request="$MAX_REQUEST" \
  --pid-file="$SANDBOX/swoole.pid" --log-file="$SANDBOX/swoole.log"
measure "swoole x$WORKERS" "http://$PHP_NAME:8102"
php_exec pkill -f "psfs:swoole:start" || true

echo "[nginx + php-fpm] ..." >&2
fresh_sandbox
docker exec -d -w "$SANDBOX" "${PHP_ENV[@]}" "$PHP_CONTAINER" php-fpm -F -c "$INI" -d xdebug.mode=off -y "$SANDBOX/../bench-runtimes-conf/php-fpm.conf"
docker run -d --rm --name "$NGINX_NAME" --network "$NETWORK" --cpus="$NGINX_CPUS" -v "$PWD:/var/www:ro" \
  -v "$WORK/nginx/default.conf:/etc/nginx/nginx.conf:ro" -v "$WORK/nginx/headers.conf:/etc/nginx/headers.conf:ro" \
  -v "$WORK/nginx/gzip.conf:/etc/nginx/gzip.conf:ro" -v "$WORK/nginx/cache.conf:/etc/nginx/cache.conf:ro" \
  -v "$WORK/nginx/proxy.conf:/etc/nginx/proxy.conf:ro" -v "$WORK/nginx/fastcgi.conf:/etc/nginx/fastcgi.conf:ro" \
  nginx:alpine >/dev/null
measure "nginx + php-fpm" "http://$NGINX_NAME"

generator report --title="## php -S, Swoole and nginx + php-fpm on the same application (PHP $(php_exec php -r 'echo PHP_VERSION;'), php container limited to $PHP_CPUS CPU, nginx to $NGINX_CPUS, $REQUESTS requests per cell)" < "$RESULTS"
