# PSFS
[![Build Status](https://scrutinizer-ci.com/g/psfs/core/badges/build.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/psfs/core/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/psfs/core/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/?branch=master)
[![Packagist Stable Version](https://img.shields.io/packagist/v/psfs/core)](https://packagist.org/packages/psfs/core)
[![Development Line](https://img.shields.io/badge/dev--master-2.2.x--dev-0A7BBB)](https://github.com/psfs/core/tree/master)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/releases/8.3/en.php)

## PHP Simple Fast & Secure Framework

PSFS is a lightweight PHP framework focused on MVC/API applications with Twig, Propel, and Symfony components.

## Runtime baseline

- Project execution and validation run with Docker Compose.
- Runtime PHP version is **8.3**.
- Default host port is defined in the root `.env` via `HOST_PORT=8008`.
- Main local services in `docker-compose.yml` are `php`, `redis`, and `db`.

Direct PHP requirements declared in `composer.json`:

- `php >=8`
- `ext-json`
- `ext-curl`
- `ext-gmp`

Core package constraints currently used by the framework:

```text
psfs/propel: dev-master
symfony/console: ^7.4
symfony/finder: ^7.4
symfony/translation: ^7.4
twig/twig: ^3.24
monolog/monolog: ^3.10
matthiasmullie/minify: ^1.3
firebase/php-jwt: ^7.0
```

## Local development

Start the stack:

```bash
docker compose up -d
docker compose ps
```

The PHP container is usually named `core-php-1`. If needed, discover it with:

```bash
docker compose ps
docker ps --format '{{.Names}}'
```

Run project commands from the PHP container:

```bash
docker exec <php_container> php -v
docker exec <php_container> composer install
docker exec <php_container> php vendor/bin/phpunit
```

The application server exposed by Docker runs:

```bash
php -S 0.0.0.0:8080 -t ./html
```

and is published on the host as `${HOST_PORT}:8080`.

## Consumer install

If you want to use PSFS as a Composer dependency in another project:

```bash
composer init
composer require psfs/core
./vendor/bin/psfs psfs:create:root
```

The `psfs:create:root` command generates the document root structure.

Published package note:

- Packagist stable release is currently `2.0.1`.
- Repository head is on the `2.2.x-dev` development line.

## Validation

Mandatory baseline before review:

```bash
docker compose up -d
docker compose ps
docker exec <php_container> php -v
docker exec <php_container> php vendor/bin/phpunit
```

Optional coverage when Xdebug is available:

```bash
docker exec -e XDEBUG_MODE=coverage <php_container> php vendor/bin/phpunit --coverage-text
```

Do not run `php`, `composer`, or `phpunit` directly on the host for project validation.

## Security/Auth contract (v2)

- Auth/cookies are versioned as **v2** with **legacy fallback in read-only mode**.
- Expected result for invalid auth is `null/null` and the request flow must be stopped.
- Compatibility policy: active fallbacks are temporary and can be removed only with explicit user approval.
- Target cookie policy:
  - `HttpOnly=true`
  - `Secure=true` when running on HTTPS
  - `SameSite=Lax` or `SameSite=Strict`
  - `Path=/`
  - coherent `Domain`
  - TTL aligned with auth/session policy

## Review and commit policy

- Changes must be reviewed by a human before commit.
- Do not auto-commit after automated changes or agent execution.

## Environment variables

Relevant Docker/runtime variables:

```text
APP_ENVIRONMENT=(local|dev|...|prod)
HOST_PORT=8008
DEBUG=-xdebug
PHP_TIMEZONE=Europe/Madrid
PHP_OPCACHE=0
MYSQL_USER=psfs
MYSQL_PASSWORD=psfs
MYSQL_ROOT_PASSWORD=psfs
MYSQL_DATABASE=psfs
```

`DEBUG=-xdebug` loads the Xdebug image variant. An empty value uses the default PHP image.

## Versioning

- Packagist stable release: `2.0.1`
- Active development line: `dev-master -> 2.2.x-dev`
- Release/tag policy is documented in `doc/VERSIONING.md`

## Documentation

- [General information and contracts](./doc/CONTRACTS.md)
- [Versioning policy](./doc/VERSIONING.md)
- [Async queue and connector contracts](./doc/contracts/async-jobs-connectors-contracts.md)
- [Security policy](./SECURITY.md)

## Roadmap

- Framework documentation
  - PhpDoc for all files
- Testing
  - 100% tests coverage
