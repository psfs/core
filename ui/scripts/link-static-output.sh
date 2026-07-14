#!/bin/sh
set -eu

app=${1:?application name is required}
case "$app" in
  ui) mount=ui ;;
  admin) mount=admin-v2 ;;
  *)
    echo "Unsupported static application: $app" >&2
    exit 64
  ;;
esac

source="/workspace/src/public/$mount"
target="/workspace/html/$mount"

if [ ! -f "$source/index.html" ]; then
  echo "Static build is missing: $source/index.html" >&2
  exit 66
fi

if [ -e "$target" ] && [ ! -L "$target" ]; then
  echo "Refusing to replace a non-link mount: $target" >&2
  exit 65
fi

if [ -L "$target" ]; then
  rm "$target"
fi

ln -s "../src/public/$mount" "$target"
