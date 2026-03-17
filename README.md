# PSFS
[![Build Status](https://scrutinizer-ci.com/g/psfs/core/badges/build.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/psfs/core/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/psfs/core/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/psfs/core/v/stable)](https://packagist.org/packages/psfs/core)


## Framework Php Simple Fast & Secure

Requirements:

* php 8.3 (runtime objetivo en contenedor Docker)
* ext-gettext
* ext-json
* ext-curl
* ext-gmp
* ext-fileinfo

### Components that PSFS install:

```
"propel/propel": "2.0.x-dev",
"symfony/console": "v6.x",
"symfony/finder": "v6.x",
"symfony/translation": "v6.x",
"twig/twig": "3.8.0",
"monolog/monolog": "3.x",
"matthiasmullie/minify": "1.3.71"
```

### How to install using composer:

Install composer via: [GetComposer](https://getcomposer.org/download/)
   
```
composer init
composer require psfs/core
./vendor/bin/psfs psfs:create:root
php -S 0.0.0.0:8080 -t ./html
```

### How to use with Docker
```
docker compose up -d
```
This project must be executed using Docker Compose.
Runtime PHP version for execution and validation is **8.3**.

Run project commands with:
```
docker exec <container_name> <command>
```

Examples:
```
docker exec <container_name> php -v
docker exec <container_name> php vendor/bin/phpunit
docker exec <container_name> composer install
```

To discover the PHP container name:
```
docker compose ps
docker ps --format '{{.Names}}'
```

### Validation (mandatory)
Run unit tests from the PHP container:
```
docker exec <container_name> php -v
docker exec <container_name> php vendor/bin/phpunit
```

### Security/Auth contract (v2)
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

### Review and commit policy
- Changes must be reviewed by a human before commit.
- Do not auto-commit after automated changes or agent execution.

### Runtime port
- The root `.env` defines `HOST_PORT=8008`.
- Use that value for `docker compose up -d` to avoid local port collisions.

Your could use some environment variables to manage the docker containers
```
- APP_ENVIRONMENT: (local|dev|...|prod) Define the staging for the run environment
- HOST_PORT: 8008 Define the port where you could expose the server
- DEBUG: -xdebug Loads a docker image with xdebug installed and configured, if empty it loads a default php image
```

### Documentation
- [General information and contracts](./doc/CONTRACTS.md)

RoadMap:

    * Framework documentation
        - PhpDoc for all files
    * Testing
        - 100% tests coverage
