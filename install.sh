#!/usr/bin/env bash
set -euo pipefail

REPO_RAW_BASE="${REPO_RAW_BASE:-https://raw.githubusercontent.com/psfs/core/master}"
GENERATOR_PATH="${GENERATOR_PATH:-scripts/create-psfs-project.sh}"
TMP_ROOT="$(mktemp -d -t psfs-installer.XXXXXX)"
WORK_DIR="${TMP_ROOT}/core"

cleanup() {
  rm -rf "${TMP_ROOT}"
}
trap cleanup EXIT

download_file() {
  local source="$1"
  local destination="$2"
  mkdir -p "$(dirname "${destination}")"

  if command -v curl >/dev/null 2>&1; then
    curl -fsSL "${source}" -o "${destination}"
    return
  fi
  if command -v wget >/dev/null 2>&1; then
    wget -qO "${destination}" "${source}"
    return
  fi

  echo "ERROR: curl or wget is required." >&2
  exit 1
}

download_file "${REPO_RAW_BASE}/${GENERATOR_PATH}" "${WORK_DIR}/${GENERATOR_PATH}"
download_file "${REPO_RAW_BASE}/templates/project/composer.json.tpl" "${WORK_DIR}/templates/project/composer.json.tpl"
download_file "${REPO_RAW_BASE}/templates/project/docker-compose.yml.tpl" "${WORK_DIR}/templates/project/docker-compose.yml.tpl"
download_file "${REPO_RAW_BASE}/templates/project/.env.example.tpl" "${WORK_DIR}/templates/project/.env.example.tpl"
download_file "${REPO_RAW_BASE}/templates/project/docker/php.ini.tpl" "${WORK_DIR}/templates/project/docker/php.ini.tpl"

chmod +x "${WORK_DIR}/${GENERATOR_PATH}"
bash "${WORK_DIR}/${GENERATOR_PATH}" "$@"
