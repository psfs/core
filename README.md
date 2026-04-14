# Php Simple Fast and Secure
## PSFS Core

[![Build Status](https://scrutinizer-ci.com/g/psfs/core/badges/build.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/build-status/master)
[![Security Pipeline](https://github.com/psfs/core/actions/workflows/security-pipeline.yml/badge.svg)](https://github.com/psfs/core/actions/workflows/security-pipeline.yml)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/psfs/core/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/psfs/core/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/?branch=master)
[![Packagist Stable Version](https://img.shields.io/packagist/v/psfs/core)](https://packagist.org/packages/psfs/core)
[![Minimum PHP Version](http://img.shields.io/badge/php-%3E%3D%208-8892BF.svg)](https://php.net/)
[![License](https://poser.pugx.org/propel/propel/license.svg)](https://packagist.org/packages/psfs/core)

PSFS is a lightweight PHP framework for MVC/API applications (Twig + Propel + Symfony components).

## 5-minute setup

Prerequisites:

- Docker + Docker Compose
- Git

<!-- validated -->
```bash
docker compose up -d
docker compose ps
docker exec core-php-1 php -v
docker exec core-php-1 composer install --no-interaction --prefer-dist
```

If your PHP container name is not `core-php-1`:

<!-- validated -->
```bash
docker compose ps
docker ps --format '{{.Names}}'
```

## Daily command map

<!-- example-only -->
```bash
# Run key tests
docker exec core-php-1 php vendor/bin/phpunit --no-coverage --filter '/(AuthApiTest|RequestResponseSecurityContractTest)/'

# List PSFS CLI commands
docker exec core-php-1 php src/bin/psfs list

# Security local pre-check (act)
act push --container-architecture linux/amd64
```

## Choose your path

### Onboarding path

1. Read [Operations Playbook](./doc/OPERATIONS.md)
2. Execute the "First day" flow
3. Use troubleshooting matrix when blocked

### Core contributor path

1. Read [Core Contracts](./doc/CONTRACTS.md)
2. Read [Async Jobs and Connectors Contracts](./doc/contracts/async-jobs-connectors-contracts.md)
3. Read [Security Plan and Artifacts](./doc/security/PLAN.md)

## Propel models and migrations

For operational Propel flow (schema, model generation context, migration execution, rollback, failure modes), see:

- [Propel Workflow](./doc/PROPEL_WORKFLOW.md)

## Documentation quality gate

Use the docs checker before opening PRs that touch docs:

<!-- validated -->
```bash
bash scripts/docs/validate_docs.sh
```

## Documentation index

- [Operations Playbook](./doc/OPERATIONS.md)
- [Propel Workflow](./doc/PROPEL_WORKFLOW.md)
- [Core Contracts](./doc/CONTRACTS.md)
- [Versioning](./doc/VERSIONING.md)
- [Async Jobs and Connectors Contracts](./doc/contracts/async-jobs-connectors-contracts.md)

## Rules

- Run project commands inside Docker containers.
- Keep command blocks explicitly tagged as `validated` or `example-only`.
