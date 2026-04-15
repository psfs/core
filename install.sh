#!/usr/bin/env bash
set -euo pipefail

REPO_RAW_BASE="${REPO_RAW_BASE:-https://raw.githubusercontent.com/psfs/core/master}"
GENERATOR_PATH="${GENERATOR_PATH:-scripts/create-psfs-project.sh}"
GENERATOR_URL="${REPO_RAW_BASE}/${GENERATOR_PATH}"

tmp_file="$(mktemp -t psfs-generator.XXXXXX.sh)"
cleanup() {
  rm -f "${tmp_file}"
}
trap cleanup EXIT

download_with_curl() {
  curl -fsSL "${GENERATOR_URL}" -o "${tmp_file}"
}

download_with_wget() {
  wget -qO "${tmp_file}" "${GENERATOR_URL}"
}

if command -v curl >/dev/null 2>&1; then
  download_with_curl
elif command -v wget >/dev/null 2>&1; then
  download_with_wget
else
  echo "ERROR: curl or wget is required." >&2
  exit 1
fi

bash "${tmp_file}" "$@"
