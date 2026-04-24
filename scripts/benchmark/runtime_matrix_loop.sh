#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PROJECT="psfs"
ENV_FILE="${ROOT_DIR}/.env"
QUICK=0
LIMIT=0
START_AT=1
HOST_PORT_OVERRIDE="18008"
HOST_PORT_SWOOLE_OVERRIDE="18011"
REQUESTS_L1=""
REQUESTS_L2=""
REQUESTS_L3=""
CONCURRENCY_L1=""
CONCURRENCY_L2=""
CONCURRENCY_L3=""
WARMUP=""
TIMEOUT=""
MAX_ERROR_RATE=""

usage() {
  cat <<EOF
Usage: $0 [--env-file PATH] [--project NAME] [--quick] [--limit N] [--start-at N] [--host-port N] [--host-port-swoole N] [--requests-l1 N] [--requests-l2 N] [--requests-l3 N] [--concurrency-l1 N] [--concurrency-l2 N] [--concurrency-l3 N] [--warmup N] [--timeout N] [--max-error-rate FLOAT]

Runs matrix loop:
  docker compose -p <project> --env-file <file> up -d
  docker exec <php-container> ...
  docker compose -p <project> --env-file <file> down

Scenarios:
  runtime={php-s,swoole} x debug={0,1} x opcache={0,1} x redis={0,1}
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --env-file)
      ENV_FILE="$2"
      shift 2
      ;;
    --project)
      PROJECT="$2"
      shift 2
      ;;
    --quick)
      QUICK=1
      shift
      ;;
    --limit)
      LIMIT="$2"
      shift 2
      ;;
    --start-at)
      START_AT="$2"
      shift 2
      ;;
    --host-port)
      HOST_PORT_OVERRIDE="$2"
      shift 2
      ;;
    --host-port-swoole)
      HOST_PORT_SWOOLE_OVERRIDE="$2"
      shift 2
      ;;
    --requests-l1)
      REQUESTS_L1="$2"
      shift 2
      ;;
    --requests-l2)
      REQUESTS_L2="$2"
      shift 2
      ;;
    --requests-l3)
      REQUESTS_L3="$2"
      shift 2
      ;;
    --concurrency-l1)
      CONCURRENCY_L1="$2"
      shift 2
      ;;
    --concurrency-l2)
      CONCURRENCY_L2="$2"
      shift 2
      ;;
    --concurrency-l3)
      CONCURRENCY_L3="$2"
      shift 2
      ;;
    --warmup)
      WARMUP="$2"
      shift 2
      ;;
    --timeout)
      TIMEOUT="$2"
      shift 2
      ;;
    --max-error-rate)
      MAX_ERROR_RATE="$2"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ ! -f "$ENV_FILE" ]]; then
  echo "[runtime-loop] env file not found: $ENV_FILE" >&2
  exit 1
fi

OUT_DIR="${ROOT_DIR}/cache/benchmark/runtime-matrix-loop"
mkdir -p "$OUT_DIR"
RUN_TS="$(date +%Y%m%d_%H%M%S)"
SUMMARY_FILE="${OUT_DIR}/summary_${RUN_TS}.ndjson"
SUMMARY_MD_FILE="${OUT_DIR}/summary_${RUN_TS}.md"
TMP_ENV_DIR="$(mktemp -d "${OUT_DIR}/env_${RUN_TS}_XXXX")"

cleanup() {
  HOST_PORT="$HOST_PORT_OVERRIDE" HOST_PORT_SWOOLE="$HOST_PORT_SWOOLE_OVERRIDE" docker compose -p "$PROJECT" --env-file "$ENV_FILE" down >/dev/null 2>&1 || true
  rm -rf "$TMP_ENV_DIR" >/dev/null 2>&1 || true
}
trap cleanup EXIT

scenario_count=0

for runtime in php-s swoole; do
  for debug in 0 1; do
    for opcache in 0 1; do
      for redis in 0 1; do
        scenario_count=$((scenario_count + 1))
        if [[ "$scenario_count" -lt "$START_AT" ]]; then
          continue
        fi
        if [[ "$LIMIT" -gt 0 && "$scenario_count" -gt "$LIMIT" ]]; then
          echo "[runtime-loop] limit reached (${LIMIT}), stopping."
          exit 0
        fi

        scenario="${runtime}_d${debug}_o${opcache}_r${redis}"
        scenario_env="${TMP_ENV_DIR}/${scenario}.env"
        echo "[runtime-loop] scenario ${scenario_count}/16 => ${scenario}"

        cp "$ENV_FILE" "$scenario_env"
        {
          echo ""
          echo "PHP_OPCACHE=${opcache}"
          echo "PSFS_BENCHMARK_ENABLED=1"
          echo "HOST_PORT=${HOST_PORT_OVERRIDE}"
          echo "HOST_PORT_SWOOLE=${HOST_PORT_SWOOLE_OVERRIDE}"
        } >> "$scenario_env"

        docker compose -p "$PROJECT" --env-file "$scenario_env" --profile swoole up -d --pull never

        PHP_CID="$(docker compose -p "$PROJECT" --env-file "$scenario_env" ps -q php)"
        if [[ -z "$PHP_CID" ]]; then
          echo "[runtime-loop] php container not found" >&2
          exit 1
        fi

        docker exec "$PHP_CID" php src/bin/psfs psfs:deploy:project >/dev/null

        cache_mode="NONE"
        if [[ "$opcache" == "1" && "$redis" == "0" ]]; then
          cache_mode="OPCACHE"
        elif [[ "$opcache" == "0" && "$redis" == "1" ]]; then
          cache_mode="REDIS"
        elif [[ "$opcache" == "0" && "$redis" == "0" ]]; then
          cache_mode="MEMORY"
        fi

        docker exec "$PHP_CID" php -r '
          $file="/var/www/config/config.json";
          $raw=file_exists($file)?file_get_contents($file):"{}";
          $cfg=json_decode((string)$raw,true);
          if(!is_array($cfg)){$cfg=[];}
          $cfg["debug"]=(bool)($argv[1]??false);
          $cfg["psfs.redis"]=(bool)($argv[2]??false);
          $cfg["redis.host"]="redis";
          $cfg["redis.port"]=6379;
          $cfg["redis.timeout"]=0.2;
          $cfg["metadata.engine.redis.enabled"]=(bool)($argv[2]??false);
          $cfg["metadata.engine.opcache.enabled"]=(bool)($argv[3]??false);
          $cfg["psfs.cache.mode"]=(string)($argv[4]??"NONE");
          file_put_contents($file, json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
        ' "$debug" "$redis" "$opcache" "$cache_mode"

        BENCH_ARGS=(--runtime="$runtime" --scenario="$scenario")
        if [[ "$QUICK" == "1" ]]; then
          BENCH_ARGS+=(--quick)
        fi
        [[ -n "$REQUESTS_L1" ]] && BENCH_ARGS+=(--requests-l1="$REQUESTS_L1")
        [[ -n "$REQUESTS_L2" ]] && BENCH_ARGS+=(--requests-l2="$REQUESTS_L2")
        [[ -n "$REQUESTS_L3" ]] && BENCH_ARGS+=(--requests-l3="$REQUESTS_L3")
        [[ -n "$CONCURRENCY_L1" ]] && BENCH_ARGS+=(--concurrency-l1="$CONCURRENCY_L1")
        [[ -n "$CONCURRENCY_L2" ]] && BENCH_ARGS+=(--concurrency-l2="$CONCURRENCY_L2")
        [[ -n "$CONCURRENCY_L3" ]] && BENCH_ARGS+=(--concurrency-l3="$CONCURRENCY_L3")
        [[ -n "$WARMUP" ]] && BENCH_ARGS+=(--warmup="$WARMUP")
        [[ -n "$TIMEOUT" ]] && BENCH_ARGS+=(--timeout="$TIMEOUT")
        [[ -n "$MAX_ERROR_RATE" ]] && BENCH_ARGS+=(--max-error-rate="$MAX_ERROR_RATE")

        scenario_file="${OUT_DIR}/${RUN_TS}_${scenario}.json"
        docker exec "$PHP_CID" php scripts/benchmark/run_http_profile.php "${BENCH_ARGS[@]}" > "$scenario_file"

        jq -c --arg scenario "$scenario" '. + {scenario_id:$scenario}' "$scenario_file" >> "$SUMMARY_FILE" || cat "$scenario_file" >> "$SUMMARY_FILE"

        docker compose -p "$PROJECT" --env-file "$scenario_env" down
      done
    done
  done
done

{
  echo "# Runtime Matrix Summary (${RUN_TS})"
  echo ""
  echo "| scenario | runtime | p50 L1 | p95 L1 | p95 L2 | p95 L3 | error_rate L1/L2/L3 |"
  echo "|---|---|---:|---:|---:|---:|---|"
} > "$SUMMARY_MD_FILE"

while IFS= read -r line; do
  runtime="$(printf '%s' "$line" | jq -r '.runtime // "n/a"' 2>/dev/null || echo "n/a")"
  scenario_id="$(printf '%s' "$line" | jq -r '.scenario_id // .scenario // "n/a"' 2>/dev/null || echo "n/a")"
  p50_l1="$(printf '%s' "$line" | jq -r '.profiles[] | select(.profile=="L1") | .p50_ms' 2>/dev/null || echo "n/a")"
  p95_l1="$(printf '%s' "$line" | jq -r '.profiles[] | select(.profile=="L1") | .p95_ms' 2>/dev/null || echo "n/a")"
  p95_l2="$(printf '%s' "$line" | jq -r '.profiles[] | select(.profile=="L2") | .p95_ms' 2>/dev/null || echo "n/a")"
  p95_l3="$(printf '%s' "$line" | jq -r '.profiles[] | select(.profile=="L3") | .p95_ms' 2>/dev/null || echo "n/a")"
  e1="$(printf '%s' "$line" | jq -r '.profiles[] | select(.profile=="L1") | .error_rate' 2>/dev/null || echo "n/a")"
  e2="$(printf '%s' "$line" | jq -r '.profiles[] | select(.profile=="L2") | .error_rate' 2>/dev/null || echo "n/a")"
  e3="$(printf '%s' "$line" | jq -r '.profiles[] | select(.profile=="L3") | .error_rate' 2>/dev/null || echo "n/a")"
  echo "| ${scenario_id} | ${runtime} | ${p50_l1} | ${p95_l1} | ${p95_l2} | ${p95_l3} | ${e1}/${e2}/${e3} |" >> "$SUMMARY_MD_FILE"
done < "$SUMMARY_FILE"

echo "[runtime-loop] done. summary ndjson: ${SUMMARY_FILE}"
echo "[runtime-loop] done. summary table : ${SUMMARY_MD_FILE}"
