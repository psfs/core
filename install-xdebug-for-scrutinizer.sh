#!/bin/sh
set -eu

xdebug_version="3.5.3"
source_dir="$(mktemp -d)"
trap 'rm -rf "$source_dir"' EXIT HUP INT TERM

php_ini="$(php -r '$file = php_ini_loaded_file(); if ($file === false) { exit(1); } echo $file;')"
if [ ! -w "$php_ini" ]; then
    echo "PHP ini file is not writable: $php_ini" >&2
    exit 1
fi

if ! php -r 'exit(extension_loaded("xdebug") ? 0 : 1);'; then
    curl --fail --silent --show-error --location \
        "https://pecl.php.net/get/xdebug-${xdebug_version}.tgz" \
        --output "$source_dir/xdebug.tgz"
    tar -xzf "$source_dir/xdebug.tgz" -C "$source_dir"
    cd "$source_dir/xdebug-${xdebug_version}"
    phpize
    ./configure --enable-xdebug --with-php-config="$(command -v php-config)"
    make -j2
    make install
    printf '\nzend_extension=%s/xdebug.so\n' "$(php-config --extension-dir)" >> "$php_ini"
fi

printf '\nxdebug.mode=coverage\n' >> "$php_ini"
php -r 'if (!extension_loaded("xdebug") || ini_get("xdebug.mode") !== "coverage") { fwrite(STDERR, "Xdebug coverage is not enabled\n"); exit(1); } echo "Xdebug ", phpversion("xdebug"), " with coverage enabled\n";'
