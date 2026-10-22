# PHP 8.3, 8.4 and 8.5

The development environment supports PHP 8.3, 8.4 and 8.5. Compose defaults to
8.5, but the validation baseline for this project is PHP 8.3 (see `AGENTS.md`).
Select the version without editing the service definitions:

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
docker exec -e XDEBUG_MODE=off psfs-php-1 php vendor/bin/phpunit --no-coverage --fail-on-warning --fail-on-deprecation --display-all-issues
```

Omit `XDEBUG_MODE=off` when debugging. Composer resolves dependencies for PHP
8.3.0 so an update on PHP 8.5 cannot silently raise their minimum PHP version.
`check-platform-reqs` verifies the actual runtime, ignoring that emulated version.

## Propel and PHP 8.5

Older Propel releases called `xml_parser_free()` in the schema reader. That call
has been a no-op since PHP 8.0 and emits a deprecation on PHP 8.5. The project
no longer applies a local patch for it: `composer.json` depends on the
`psfs/propel` fork (`dev-master`), whose source does not call `xml_parser_free()`.
The schema generation and API tests exercise this path.

Because the fix lives in the dependency, downstream applications and fresh
`--no-dev` installs receive it automatically, as long as they resolve
`psfs/propel` rather than upstream `propel/propel`.
