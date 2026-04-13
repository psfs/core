# PSFS Core

[![Packagist Stable Version](https://img.shields.io/packagist/v/psfs/core)](https://packagist.org/packages/psfs/core)
[![Development Line](https://img.shields.io/badge/dev--master-2.2.x--dev-0A7BBB)](https://github.com/psfs/core/tree/master)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/releases/8.3/en.php)

PSFS is a lightweight PHP framework for MVC/API applications (Twig + Propel + Symfony components).

## Runtime baseline

- Execution and validation use Docker Compose.
- Target PHP runtime: **8.3**.
- Main services: `php`, `redis`, `db`.
- Host port is configured via `.env` (`HOST_PORT=8008` by default).

## Quick start

```bash
docker compose up -d
docker compose ps
```

Run project commands inside the PHP container:

```bash
docker exec core-php-1 php -v
docker exec core-php-1 composer install
docker exec core-php-1 php vendor/bin/phpunit --no-coverage
```

If your PHP container name differs:

```bash
docker compose ps
docker ps --format '{{.Names}}'
```

## Swoole runtime

Check and run Swoole commands through `src/bin/psfs`:

```bash
docker exec core-php-1 php /var/www/src/bin/psfs psfs:swoole:check
docker exec core-php-1 php /var/www/src/bin/psfs psfs:swoole:start --host=0.0.0.0 --port=8080
docker exec core-php-1 php /var/www/src/bin/psfs psfs:swoole:status
docker exec core-php-1 php /var/www/src/bin/psfs psfs:swoole:reload
docker exec core-php-1 php /var/www/src/bin/psfs psfs:swoole:stop
```

Optional compose profile:

```bash
docker compose --profile swoole up -d php-swoole
docker compose --profile swoole ps
```

## Security baseline (v2)

- Auth/cookies are versioned as `v2`.
- Legacy fallback remains read-only until explicit removal approval.
- Invalid auth must result in `null/null` and stop request flow.
- Cookie policy target:
  - `HttpOnly=true`
  - `Secure=true` on HTTPS
  - `SameSite=Lax|Strict`
  - `Path=/`
  - coherent `Domain`
  - TTL aligned with session/auth policy

## CI/CD security gates

Security pipeline blocks merge/release when:

- a `must_pass` security control test fails,
- any high/critical finding is unresolved,
- hardening or quality gate returns non-pass.

Local pre-check:

```bash
act push --container-architecture linux/amd64
```

## Install as dependency

```bash
composer require psfs/core
./vendor/bin/psfs psfs:create:root
```

## Documentation

- [Contracts](./doc/CONTRACTS.md)
- [Versioning policy](./doc/VERSIONING.md)
- [Async jobs and connectors contracts](./doc/contracts/async-jobs-connectors-contracts.md)

## Notes

- Do not run `php`, `composer`, or `phpunit` directly on host for project validation.
- Human review is required before committing automated/agent-driven changes.
