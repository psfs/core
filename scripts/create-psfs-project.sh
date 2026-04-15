#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
TEMPLATE_DIR="${REPO_ROOT}/templates/project"
REPO_RAW_BASE="${REPO_RAW_BASE:-https://raw.githubusercontent.com/psfs/core/master}"

PROJECT_NAME=""
TARGET_PATH=""
RUNTIME="auto"
NON_INTERACTIVE=0
FORCE=0
PACKAGE_NAME=""
DESCRIPTION="PSFS application"
AUTHOR=""
LICENSE="MIT"

print_usage() {
  cat <<'USAGE'
Usage:
  ./scripts/create-psfs-project.sh [options]

Options:
  --name <project_name>         Project directory name.
  --path <target_path>          Absolute or relative target path.
  --runtime <auto|local|docker> Runtime selection. Default: auto.
  --package <vendor/package>    Composer package name.
  --description <text>          Project description.
  --author <name>               Author name.
  --license <spdx>              License. Default: MIT.
  --non-interactive             Disable prompts; requires enough flags.
  --force                       Allow generation into a non-empty existing directory.
  --help                        Show this help.
USAGE
}

die() {
  echo "ERROR: $*" >&2
  exit 1
}

trim() {
  local value="$1"
  value="${value#"${value%%[![:space:]]*}"}"
  value="${value%"${value##*[![:space:]]}"}"
  printf '%s' "${value}"
}

require_template() {
  local file="$1"
  [[ -f "${TEMPLATE_DIR}/${file}" ]] || die "Template not found: ${TEMPLATE_DIR}/${file}"
}

download_file() {
  local source="$1"
  local destination="$2"
  mkdir -p "$(dirname "${destination}")"

  if command_exists curl; then
    curl -fsSL "${source}" -o "${destination}"
    return
  fi
  if command_exists wget; then
    wget -qO "${destination}" "${source}"
    return
  fi

  die "Unable to download templates: neither curl nor wget is available."
}

ensure_templates_available() {
  if [[ -f "${TEMPLATE_DIR}/composer.json.tpl" ]]; then
    return
  fi

  # Fallback for standalone execution (e.g., script piped/downloaded without repo layout).
  local fallback_dir="${SCRIPT_DIR}/.psfs-templates/project"
  download_file "${REPO_RAW_BASE}/templates/project/composer.json.tpl" "${fallback_dir}/composer.json.tpl"
  download_file "${REPO_RAW_BASE}/templates/project/docker-compose.yml.tpl" "${fallback_dir}/docker-compose.yml.tpl"
  download_file "${REPO_RAW_BASE}/templates/project/.env.example.tpl" "${fallback_dir}/.env.example.tpl"
  download_file "${REPO_RAW_BASE}/templates/project/docker/php.ini.tpl" "${fallback_dir}/docker/php.ini.tpl"
  TEMPLATE_DIR="${fallback_dir}"
}

escape_sed() {
  printf '%s' "$1" | sed -e 's/[\/&]/\\&/g'
}

is_valid_composer_package() {
  local package="$1"
  [[ "${package}" =~ ^[a-z0-9_.-]+/[a-z0-9_.-]+$ ]]
}

parse_args() {
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --name)
        [[ $# -gt 1 ]] || die "Missing value for --name"
        PROJECT_NAME="$(trim "$2")"
        shift 2
        ;;
      --path)
        [[ $# -gt 1 ]] || die "Missing value for --path"
        TARGET_PATH="$(trim "$2")"
        shift 2
        ;;
      --runtime)
        [[ $# -gt 1 ]] || die "Missing value for --runtime"
        RUNTIME="$(trim "$2")"
        shift 2
        ;;
      --package)
        [[ $# -gt 1 ]] || die "Missing value for --package"
        PACKAGE_NAME="$(trim "$2")"
        shift 2
        ;;
      --description)
        [[ $# -gt 1 ]] || die "Missing value for --description"
        DESCRIPTION="$(trim "$2")"
        shift 2
        ;;
      --author)
        [[ $# -gt 1 ]] || die "Missing value for --author"
        AUTHOR="$(trim "$2")"
        shift 2
        ;;
      --license)
        [[ $# -gt 1 ]] || die "Missing value for --license"
        LICENSE="$(trim "$2")"
        shift 2
        ;;
      --non-interactive)
        NON_INTERACTIVE=1
        shift
        ;;
      --force)
        FORCE=1
        shift
        ;;
      --help|-h)
        print_usage
        exit 0
        ;;
      *)
        die "Unknown argument: $1"
        ;;
    esac
  done
}

command_exists() {
  command -v "$1" >/dev/null 2>&1
}

has_local_runtime() {
  command_exists php && command_exists composer
}

has_docker_runtime() {
  command_exists docker && docker compose version >/dev/null 2>&1
}

prompt_value() {
  local prompt="$1"
  local default="$2"
  local value=""
  read -r -p "${prompt} [${default}]: " value
  value="$(trim "${value}")"
  if [[ -z "${value}" ]]; then
    printf '%s' "${default}"
  else
    printf '%s' "${value}"
  fi
}

detect_runtime() {
  local local_available=0
  local docker_available=0

  if has_local_runtime; then
    local_available=1
  fi
  if has_docker_runtime; then
    docker_available=1
  fi

  case "${RUNTIME}" in
    local)
      [[ ${local_available} -eq 1 ]] || die "Runtime local requested but php/composer were not detected."
      ;;
    docker)
      [[ ${docker_available} -eq 1 ]] || die "Runtime docker requested but docker compose was not detected."
      ;;
    auto)
      if [[ ${local_available} -eq 1 && ${docker_available} -eq 1 ]]; then
        if [[ ${NON_INTERACTIVE} -eq 1 ]]; then
          die "Both runtimes detected. In --non-interactive mode, pass --runtime local|docker."
        fi
        echo "Both runtimes were detected:"
        echo "  1) local (php + composer)"
        echo "  2) docker (docker compose)"
        while true; do
          local choice
          read -r -p "Select runtime [1/2]: " choice
          case "${choice}" in
            1) RUNTIME="local"; break ;;
            2) RUNTIME="docker"; break ;;
            *) echo "Invalid choice. Please choose 1 or 2." ;;
          esac
        done
      elif [[ ${docker_available} -eq 1 ]]; then
        RUNTIME="docker"
      elif [[ ${local_available} -eq 1 ]]; then
        RUNTIME="local"
      else
        die "No supported runtime detected. Install php+composer or docker compose."
      fi
      ;;
    *)
      die "Invalid --runtime value: ${RUNTIME}. Allowed: auto, local, docker."
      ;;
  esac
}

derive_defaults() {
  if [[ -z "${PROJECT_NAME}" ]]; then
    PROJECT_NAME="psfs-app"
  fi
  if [[ -z "${TARGET_PATH}" ]]; then
    TARGET_PATH="${PWD}/${PROJECT_NAME}"
  fi
  if [[ -z "${PACKAGE_NAME}" ]]; then
    local sanitized
    sanitized="$(printf '%s' "${PROJECT_NAME}" | tr '[:upper:]' '[:lower:]' | tr ' ' '-' | tr -cd 'a-z0-9_.-')"
    [[ -n "${sanitized}" ]] || sanitized="psfs-app"
    PACKAGE_NAME="acme/${sanitized}"
  fi
  if [[ -z "${AUTHOR}" ]]; then
    AUTHOR="${USER:-PSFS Developer}"
  fi
}

prompt_project_meta() {
  if [[ ${NON_INTERACTIVE} -eq 1 ]]; then
    return
  fi

  PROJECT_NAME="$(prompt_value "Project name" "${PROJECT_NAME}")"
  TARGET_PATH="$(prompt_value "Target path" "${TARGET_PATH}")"
  PACKAGE_NAME="$(prompt_value "Composer package (vendor/package)" "${PACKAGE_NAME}")"
  DESCRIPTION="$(prompt_value "Description" "${DESCRIPTION}")"
  AUTHOR="$(prompt_value "Author" "${AUTHOR}")"
  LICENSE="$(prompt_value "License" "${LICENSE}")"
}

validate_inputs() {
  [[ -n "${PROJECT_NAME}" ]] || die "Project name cannot be empty."
  [[ -n "${TARGET_PATH}" ]] || die "Target path cannot be empty."
  [[ -n "${PACKAGE_NAME}" ]] || die "Composer package cannot be empty."
  [[ -n "${AUTHOR}" ]] || die "Author cannot be empty."
  [[ -n "${LICENSE}" ]] || die "License cannot be empty."

  is_valid_composer_package "${PACKAGE_NAME}" || die "Invalid composer package: ${PACKAGE_NAME}. Expected vendor/package in lowercase."

  if [[ -e "${TARGET_PATH}" ]]; then
    if [[ -n "$(find "${TARGET_PATH}" -mindepth 1 -maxdepth 1 2>/dev/null)" ]]; then
      [[ ${FORCE} -eq 1 ]] || die "Target path exists and is not empty: ${TARGET_PATH}. Use --force to continue."
    fi
  fi
}

init_project_tree() {
  mkdir -p "${TARGET_PATH}"/{config,html,src,cache,logs,locale}
  if [[ "${RUNTIME}" == "docker" ]]; then
    mkdir -p "${TARGET_PATH}/docker"
  fi
}

render_template() {
  local template_file="$1"
  local output_file="$2"

  require_template "${template_file}"
  mkdir -p "$(dirname "${output_file}")"

  sed \
    -e "s/__PROJECT_NAME__/$(escape_sed "${PROJECT_NAME}")/g" \
    -e "s/__PACKAGE_NAME__/$(escape_sed "${PACKAGE_NAME}")/g" \
    -e "s/__DESCRIPTION__/$(escape_sed "${DESCRIPTION}")/g" \
    -e "s/__AUTHOR__/$(escape_sed "${AUTHOR}")/g" \
    -e "s/__LICENSE__/$(escape_sed "${LICENSE}")/g" \
    < "${TEMPLATE_DIR}/${template_file}" > "${output_file}"
}

render_composer_json() {
  render_template "composer.json.tpl" "${TARGET_PATH}/composer.json"
}

render_docker_bundle() {
  if [[ "${RUNTIME}" != "docker" ]]; then
    return
  fi

  render_template "docker-compose.yml.tpl" "${TARGET_PATH}/docker-compose.yml"
  render_template "docker/php.ini.tpl" "${TARGET_PATH}/docker/php.ini"
  render_template ".env.example.tpl" "${TARGET_PATH}/.env.example"
}

render_bootstrap_readme() {
  cat > "${TARGET_PATH}/README.bootstrap.md" <<EOF
# ${PROJECT_NAME} Bootstrap

Runtime selected: ${RUNTIME}

## Next steps

1. Enter project directory:
   \`\`\`bash
   cd ${TARGET_PATH}
   \`\`\`

2. Install dependencies:
EOF

  if [[ "${RUNTIME}" == "docker" ]]; then
    cat >> "${TARGET_PATH}/README.bootstrap.md" <<'EOF'
   ```bash
   docker compose up -d
   docker compose ps
   docker exec $(docker compose ps -q php | xargs docker inspect --format '{{.Name}}' | sed 's#^/##') composer install --no-interaction --prefer-dist
   ```
EOF
  else
    cat >> "${TARGET_PATH}/README.bootstrap.md" <<'EOF'
   ```bash
   composer install --no-interaction --prefer-dist
   ```
EOF
  fi

  cat >> "${TARGET_PATH}/README.bootstrap.md" <<'EOF'

3. Run PSFS setup command:
   ```bash
   # Use your preferred runtime command style
   ```
EOF
}

print_next_steps() {
  echo
  echo "Project generated successfully:"
  echo "  Path: ${TARGET_PATH}"
  echo "  Runtime: ${RUNTIME}"
  echo
  echo "Next steps:"
  echo "  1) cd ${TARGET_PATH}"
  if [[ "${RUNTIME}" == "docker" ]]; then
    echo "  2) cp .env.example .env"
    echo "  3) docker compose up -d"
    echo "  4) docker compose ps"
  else
    echo "  2) composer install --no-interaction --prefer-dist"
  fi
  echo "  5) Review README.bootstrap.md"
}

main() {
  parse_args "$@"
  detect_runtime
  derive_defaults
  prompt_project_meta
  validate_inputs
  ensure_templates_available
  init_project_tree
  render_composer_json
  render_docker_bundle
  render_bootstrap_readme
  print_next_steps
}

main "$@"
