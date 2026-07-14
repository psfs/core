set -eu

npm ci

case "${UI_MODE:-build}" in
  watch)
    exec npm run watch
    ;;
  build)
    exec npm run build
    ;;
  *)
    echo "Unsupported UI_MODE: ${UI_MODE}. Use watch or build." >&2
    exit 64
    ;;
esac
