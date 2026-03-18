#!/usr/bin/env bash
set -eu

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

QUALITY_WORKDIR=".quality-artifacts"
SCRUTINIZER_FILE="$QUALITY_WORKDIR/scrutinizer.json"
SCRUTINIZER_RAW_FILE="$QUALITY_WORKDIR/scrutinizer.raw.json"

mkdir -p "$QUALITY_WORKDIR"

is_numeric_delta() {
  [[ "${1:-}" =~ ^-?[0-9]+([.][0-9]+)?$ ]]
}

write_payload() {
  local delta="$1"
  local note="$2"
  python3 - "$delta" "$note" <<'PY' > "$SCRUTINIZER_FILE"
import json
import sys

raw_delta = sys.argv[1]
note = sys.argv[2]
delta = None
if raw_delta != "null":
    try:
        delta = float(raw_delta)
    except Exception:
        delta = None

print(json.dumps({"delta": delta, "note": note}, indent=2))
PY
}

write_github_env() {
  local delta="$1"
  local note="$2"
  if [[ -z "${GITHUB_ENV:-}" ]]; then
    return
  fi
  if [[ "$delta" != "null" ]]; then
    echo "SCRUTINIZER_DELTA=$delta" >> "$GITHUB_ENV"
  fi
  echo "SCRUTINIZER_NOTE=$note" >> "$GITHUB_ENV"
}

extract_delta_from_json() {
  local json_path="$1"
  local path_hint="${2:-}"
  python3 - "$json_path" "$path_hint" <<'PY'
import json
import sys

json_path = sys.argv[1]
path_hint = sys.argv[2].strip()

def get_by_path(data, path):
    node = data
    for chunk in [p for p in path.split('.') if p]:
        if isinstance(node, list):
            try:
                idx = int(chunk)
            except ValueError:
                return None
            if idx < 0 or idx >= len(node):
                return None
            node = node[idx]
            continue
        if not isinstance(node, dict) or chunk not in node:
            return None
        node = node[chunk]
    return node

def find_delta(node):
    if isinstance(node, dict):
        for key, value in node.items():
            if key.lower() == "delta" and isinstance(value, (int, float)):
                return value
        for value in node.values():
            found = find_delta(value)
            if found is not None:
                return found
    elif isinstance(node, list):
        for value in node:
            found = find_delta(value)
            if found is not None:
                return found
    return None

try:
    with open(json_path, "r", encoding="utf-8") as fh:
        payload = json.load(fh)
except Exception:
    print("")
    sys.exit(0)

candidate = None
if path_hint:
    value = get_by_path(payload, path_hint)
    if isinstance(value, (int, float)):
        candidate = value

if candidate is None:
    candidate = find_delta(payload)

print("" if candidate is None else str(candidate))
PY
}

if [[ -n "${SCRUTINIZER_DELTA:-}" ]]; then
  if is_numeric_delta "$SCRUTINIZER_DELTA"; then
    write_payload "$SCRUTINIZER_DELTA" "Delta provided from CI environment"
    write_github_env "$SCRUTINIZER_DELTA" "Delta provided from CI environment"
  else
    write_payload "null" "Invalid SCRUTINIZER_DELTA format"
    write_github_env "null" "Invalid SCRUTINIZER_DELTA format"
  fi
  exit 0
fi

if [[ -z "${SCRUTINIZER_DELTA_URL:-}" || -z "${SCRUTINIZER_API_TOKEN:-}" ]]; then
  write_payload "null" "No Scrutinizer vars/secrets configured"
  write_github_env "null" "No Scrutinizer vars/secrets configured"
  exit 0
fi

status_code="$(curl -sS -o "$SCRUTINIZER_RAW_FILE" -w "%{http_code}" \
  -H "Authorization: Bearer ${SCRUTINIZER_API_TOKEN}" \
  -H "Accept: application/json" \
  "${SCRUTINIZER_DELTA_URL}" || true)"

if [[ "${status_code}" =~ ^2 ]]; then
  delta="$(extract_delta_from_json "$SCRUTINIZER_RAW_FILE" "${SCRUTINIZER_DELTA_JSON_PATH:-}")"
  if is_numeric_delta "$delta"; then
    write_payload "$delta" "Delta fetched from Scrutinizer API"
    write_github_env "$delta" "Delta fetched from Scrutinizer API"
  else
    write_payload "null" "Scrutinizer API reachable but delta value not found"
    write_github_env "null" "Scrutinizer API reachable but delta value not found"
  fi
else
  write_payload "null" "Scrutinizer API request failed"
  write_github_env "null" "Scrutinizer API request failed (HTTP ${status_code})"
fi
