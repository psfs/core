#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

BASE_URL="https://scrutinizer-ci.com/api"
PROVIDER="g"
OWNER=""
REPO=""
INDEX_ID=""
ISSUES_FILE=""
PER_PAGE="100"
OUT_DIR=".tmp_quality/scrutinizer"
TOKEN="${SCRUTINIZER_TOKEN:-}"

usage() {
  cat <<'EOF'
Usage:
  scripts/docs/scrutinizer_plan.sh --owner <owner> --repo <repo> [options]
  scripts/docs/scrutinizer_plan.sh --issues-file <path> [options]

Required:
  Mode API:
    --owner <owner>           Repository owner/login (GitHub provider: user/org)
    --repo <repo>             Repository name
  Mode offline:
    --issues-file <path>      Existing issues JSON array (skip Scrutinizer API calls)

Options:
  --token <token>           Scrutinizer access token (or use SCRUTINIZER_TOKEN env)
  --provider <g|b|gl|gp>    Repository provider type (default: g)
  --index <id>              Use a specific index id/source reference (skip inspection lookup)
  --issues-file <path>      Re-generate plan from local JSON issues file
  --per-page <n>            Pagination size for issues (default: 100, max 100)
  --base-url <url>          API base URL (default: https://scrutinizer-ci.com/api)
  --out-dir <path>          Output directory (default: .tmp_quality/scrutinizer)
  -h, --help                Show this help

Outputs:
  <out-dir>/scrutinizer-issues.json
  <out-dir>/scrutinizer-plan.md
  <out-dir>/scrutinizer-meta.json

Examples:
  SCRUTINIZER_TOKEN=xxx scripts/docs/scrutinizer_plan.sh --owner psfs --repo core
  scripts/docs/scrutinizer_plan.sh --owner psfs --repo core --token xxx --index 12345
  scripts/docs/scrutinizer_plan.sh --issues-file .tmp_quality/scrutinizer/scrutinizer-issues.json
EOF
}

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "[SCRUTINIZER][ERROR] Missing required command: $1" >&2
    exit 1
  fi
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --owner)
      OWNER="${2:-}"; shift 2 ;;
    --repo)
      REPO="${2:-}"; shift 2 ;;
    --token)
      TOKEN="${2:-}"; shift 2 ;;
    --provider)
      PROVIDER="${2:-}"; shift 2 ;;
    --index)
      INDEX_ID="${2:-}"; shift 2 ;;
    --issues-file)
      ISSUES_FILE="${2:-}"; shift 2 ;;
    --per-page)
      PER_PAGE="${2:-}"; shift 2 ;;
    --base-url)
      BASE_URL="${2:-}"; shift 2 ;;
    --out-dir)
      OUT_DIR="${2:-}"; shift 2 ;;
    -h|--help)
      usage; exit 0 ;;
    *)
      echo "[SCRUTINIZER][ERROR] Unknown argument: $1" >&2
      usage
      exit 1 ;;
  esac
done

require_cmd jq
if [[ -z "$ISSUES_FILE" ]]; then
  require_cmd curl
  if [[ -z "$OWNER" || -z "$REPO" ]]; then
    echo "[SCRUTINIZER][ERROR] --owner and --repo are required in API mode." >&2
    usage
    exit 1
  fi
  if [[ -z "$TOKEN" ]]; then
    echo "[SCRUTINIZER][ERROR] Missing token. Use --token or SCRUTINIZER_TOKEN env." >&2
    exit 1
  fi
fi

if [[ "$PER_PAGE" =~ ^[0-9]+$ ]]; then
  if (( PER_PAGE < 1 || PER_PAGE > 100 )); then
    echo "[SCRUTINIZER][ERROR] --per-page must be between 1 and 100." >&2
    exit 1
  fi
else
  echo "[SCRUTINIZER][ERROR] --per-page must be numeric." >&2
  exit 1
fi

case "$PROVIDER" in
  g|b|gl|gp) ;;
  *)
    echo "[SCRUTINIZER][ERROR] --provider must be one of: g, b, gl, gp." >&2
    exit 1 ;;
esac

mkdir -p "$OUT_DIR"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

api_get() {
  local path="$1"
  local url="${BASE_URL%/}/${path}"
  local body_file
  local status
  body_file="$(mktemp "${TMP_DIR}/api-body.XXXXXX")" || {
    echo "[SCRUTINIZER][ERROR] Unable to create temporary file in ${TMP_DIR}" >&2
    exit 1
  }
  if [[ "$url" == *\?* ]]; then
    url="${url}&access_token=${TOKEN}"
  else
    url="${url}?access_token=${TOKEN}"
  fi

  status="$(curl -sS -o "$body_file" -w '%{http_code}' -H "Accept: application/json" "$url" || true)"
  if [[ -z "$status" || "$status" -lt 200 || "$status" -ge 300 ]]; then
    echo "[SCRUTINIZER][ERROR] API request failed: ${url}" >&2
    echo "[SCRUTINIZER][ERROR] HTTP status: ${status:-unknown}" >&2
    if [[ -s "$body_file" ]]; then
      echo "[SCRUTINIZER][ERROR] Response body:" >&2
      cat "$body_file" >&2
      echo >&2
    fi
    echo "[SCRUTINIZER][HINT] Revisa token, provider (--provider), owner/repo o permisos del repo en Scrutinizer." >&2
    exit 1
  fi
  cat "$body_file"
}

INSPECTION_ID=""
pages=0
total_issues=0

if [[ -n "$ISSUES_FILE" ]]; then
  if [[ ! -f "$ISSUES_FILE" ]]; then
    echo "[SCRUTINIZER][ERROR] --issues-file not found: $ISSUES_FILE" >&2
    exit 1
  fi
  jq -e 'type == "array"' "$ISSUES_FILE" >/dev/null
  issues_abs="$(cd "$(dirname "$ISSUES_FILE")" && pwd)/$(basename "$ISSUES_FILE")"
  out_issues_abs="$(cd "$OUT_DIR" && pwd)/scrutinizer-issues.json"
  if [[ "$issues_abs" != "$out_issues_abs" ]]; then
    cp "$ISSUES_FILE" "${OUT_DIR}/scrutinizer-issues.json"
  fi
  total_issues="$(jq 'length' "${OUT_DIR}/scrutinizer-issues.json")"
  INDEX_ID="${INDEX_ID:-offline}"
  OWNER="${OWNER:-unknown}"
  REPO="${REPO:-unknown}"
  echo "[SCRUTINIZER] Offline mode: using issues from ${ISSUES_FILE}"
else
  REPO_PATH="repositories/${PROVIDER}/${OWNER}/${REPO}"
  if [[ -z "$INDEX_ID" ]]; then
    echo "[SCRUTINIZER] Resolviendo última inspección para ${PROVIDER}/${OWNER}/${REPO}..."
    INSPECTIONS_JSON="$(api_get "${REPO_PATH}/inspections?per_page=1")"
    printf '%s' "$INSPECTIONS_JSON" > "${TMP_DIR}/inspections.json"

    INSPECTION_ID="$(jq -r '._embedded.inspections[0].uuid // ._embedded.inspections[0].id // empty' "${TMP_DIR}/inspections.json")"
    if [[ -z "$INSPECTION_ID" ]]; then
      echo "[SCRUTINIZER][ERROR] No se encontró inspección reciente. Verifica repo/token." >&2
      exit 1
    fi

    INSPECTION_JSON="$(api_get "${REPO_PATH}/inspections/${INSPECTION_ID}")"
    printf '%s' "$INSPECTION_JSON" > "${TMP_DIR}/inspection.json"
    INDEX_ID="$(jq -r '.head_index.id // .head_index // ._embedded.head_index.id // ._embedded.indices[0].id // .index.id // empty' "${TMP_DIR}/inspection.json")"

    if [[ -z "$INDEX_ID" ]]; then
      echo "[SCRUTINIZER][ERROR] No se pudo resolver head_index en inspección ${INSPECTION_ID}. Usa --index." >&2
      exit 1
    fi
  fi

  echo "[SCRUTINIZER] Descargando issues del índice: ${INDEX_ID}"
  page=1
  while :; do
    PAGE_JSON="${TMP_DIR}/issues-page-${page}.json"
    api_get "${REPO_PATH}/indices/${INDEX_ID}/issues?per_page=${PER_PAGE}&page=${page}" > "$PAGE_JSON"

    count="$(jq '._embedded.issues | length' "$PAGE_JSON")"
    if [[ "$count" == "0" ]]; then
      break
    fi

    pages=$((pages + 1))
    total_issues=$((total_issues + count))
    page=$((page + 1))
  done

  if (( pages == 0 )); then
    echo "[]" > "${OUT_DIR}/scrutinizer-issues.json"
  else
    jq -s '[ .[] | ._embedded.issues[] ]' "${TMP_DIR}"/issues-page-*.json > "${OUT_DIR}/scrutinizer-issues.json"
  fi
fi

cat > "${OUT_DIR}/scrutinizer-meta.json" <<EOF
{
  "generated_at": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "base_url": "${BASE_URL}",
  "provider": "${PROVIDER}",
  "owner": "${OWNER}",
  "repo": "${REPO}",
  "inspection_id": "${INSPECTION_ID}",
  "index_id": "${INDEX_ID}",
  "pages": ${pages},
  "issues": ${total_issues}
}
EOF

echo "[SCRUTINIZER] Generando plan priorizado..."

jq -r '
  def sev_num: (if type == "number" then . else (tonumber? // -1) end);
  def priority:
    if (sev_num) == 10 then "P0"
    elif (sev_num) == 5 then "P1"
    elif (sev_num) == 0 then "P2"
    else "PX" end;
  def weight:
    if (sev_num) == 10 then 0
    elif (sev_num) == 5 then 1
    elif (sev_num) == 0 then 2
    else 3 end;

  . as $issues
  | [
      "# Scrutinizer Action Plan",
      "",
      "Generated at: " + (now | strftime("%Y-%m-%d %H:%M:%S UTC")),
      "",
      "## Summary",
      "",
      "- Total issues: " + (($issues | length) | tostring),
      "- P0 (severity 10): " + (($issues | map(select((.severity|sev_num) == 10)) | length) | tostring),
      "- P1 (severity 5): " + (($issues | map(select((.severity|sev_num) == 5)) | length) | tostring),
      "- P2 (severity 0): " + (($issues | map(select((.severity|sev_num) == 0)) | length) | tostring),
      "- Other severities: " + (($issues | map(select(((.severity|sev_num) != 10) and ((.severity|sev_num) != 5) and ((.severity|sev_num) != 0))) | length) | tostring),
      "",
      "## Top Hotspots (by issue count)",
      ""
    ]
    + (
      $issues
      | group_by((.path // "unknown") | tostring)
      | map({path: ((.[0].path // "unknown") | tostring), count: length})
      | sort_by(-.count)
      | .[:15]
      | if length == 0 then ["- No hotspots (empty issue set)."]
        else map("- `" + .path + "`: " + (.count|tostring) + " issues")
        end
    )
    + [
      "",
      "## Prioritized Backlog",
      ""
    ]
    + (
      $issues
      | sort_by((.severity // "unknown" | weight), ((.path // "unknown")|tostring), ((.line // 0)|tonumber? // 0))
      | .[:200]
      | if length == 0 then ["- No issues found."]
        else map(
          "- [" + ((.severity|sev_num|priority)) + "|S" + (((.severity // -1)|sev_num)|tostring) + "] "
          + "`" + ((.path // "unknown") | tostring) + ":" + (((.line // 0)|tonumber? // 0)|tostring) + "` "
          + ((.message // .message_id // "No message") | tostring)
          + (
              if (.labels // [] | length) > 0 then
                " _(labels: " + ((.labels | map(tostring) | join(", "))) + ")_"
              else "" end
            )
        )
        end
    )
    + [
      "",
      "## Suggested Execution Order",
      "",
      "1. Fix all `P0 (severity 10)` issues first.",
      "2. Tackle files with highest hotspot counts to reduce repeated defects.",
      "3. Address `P1 (severity 5)` issues in changed modules.",
      "4. Batch `P2 (severity 0)` items into cleanup/refactor passes.",
      "",
      "## Notes",
      "",
      "- This plan is auto-generated from Scrutinizer issues endpoint.",
      "- Re-run after fixes to compare trend and close loop."
    ]
  | .[]
' "${OUT_DIR}/scrutinizer-issues.json" > "${OUT_DIR}/scrutinizer-plan.md"

echo "[SCRUTINIZER] Done."
echo "  - Issues JSON: ${OUT_DIR}/scrutinizer-issues.json"
echo "  - Plan MD:     ${OUT_DIR}/scrutinizer-plan.md"
echo "  - Meta JSON:   ${OUT_DIR}/scrutinizer-meta.json"
