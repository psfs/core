# PHP 8.3, 8.4 and 8.5

The development environment supports PHP 8.3, 8.4 and 8.5. Compose defaults to
8.5; select another version without editing the service definitions:

```sh
PHP_VERSION=8.3 docker compose up -d php
PHP_VERSION=8.4 docker compose up -d php
PHP_VERSION=8.5 docker compose up -d php
```

The same variable selects the image for `php-swoole`. The existing `DEBUG`
variable still controls the image suffix.

Run project commands inside the container:

```sh
docker exec psfs-php-1 php -v
docker exec -e XDEBUG_MODE=off psfs-php-1 composer install
docker exec -e XDEBUG_MODE=off psfs-php-1 composer check-platform-reqs
docker exec -e XDEBUG_MODE=off psfs-php-1 php vendor/bin/phpunit --no-coverage --fail-on-deprecation --display-all-issues
```

Omit `XDEBUG_MODE=off` when debugging. Composer resolves dependencies for PHP
8.3.0 so an update on PHP 8.5 cannot silently raise their minimum PHP version.
`check-platform-reqs` verifies the actual runtime, ignoring that emulated version.

## Temporary Propel patch

Propel's schema reader calls `xml_parser_free()`, a no-op since PHP 8.0 that
emits a deprecation in PHP 8.5. `patches/propel-php85-xml-parser.patch` removes
that call; restoring the saved parser already releases the object reference.
The existing schema generation and API tests exercise this path.

The development dependency `cweagans/composer-patches` applies the local patch
on installation. `patches.json` defines it and `patches.lock.json` locks its
checksum. For an already installed Propel package, use:

```sh
docker exec -e XDEBUG_MODE=off psfs-php-1 composer patches-repatch
```

This patch covers development installations of this repository. It does not
propagate to applications consuming `psfs/core`, or fresh `--no-dev` installs.
Those installations need the equivalent fix in `psfs/propel` upstream (or a
patch configured in their root project). Remove the patch and development
plugin once the upstream fix is available.
