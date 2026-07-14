#!/bin/sh
set -eu

target=/workspace/html/ui
source=/workspace/src/public/ui

if [ ! -f "$source/index.html" ]; then
  echo "Static UI build is missing: $source/index.html" >&2
  exit 66
fi
if [ -e "$target" ] || [ -L "$target" ]; then
  echo "Refusing to replace existing static UI mount: $target" >&2
  exit 65
fi

# The target must resolve from both bind-mounted roots: /workspace in Node and
# /var/www in PHP. An absolute /workspace target is invisible to PHP.
ln -s ../src/public/ui "$target"
trap 'rm -f "$target"' EXIT

npx playwright test e2e/ui-static-fallback.spec.mjs
