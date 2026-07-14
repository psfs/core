set -eu

npm ci

case "${UI_MODE:-build}" in
  watch)
    exec npm run "watch:${UI_APP:-admin}"
  ;;
  build)
    npm run "build:${UI_APP:-admin}"
    exec sh ./scripts/link-static-output.sh "${UI_APP:-admin}"
    ;;
  *)
    echo "Unsupported UI_MODE: ${UI_MODE}. Use watch or build." >&2
    exit 64
    ;;
esac
