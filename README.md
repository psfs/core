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

## Quick Install (Copy/Paste)

Requirements:
- `bash`
- `docker compose` or `php + composer`

Using `curl`:

```bash
curl -fsSL https://raw.githubusercontent.com/psfs/core/master/install.sh | bash
```

Using `wget`:

```bash
wget -qO- https://raw.githubusercontent.com/psfs/core/master/install.sh | bash
```

The installer downloads and runs the official PSFS project generator.

## Create a Project

Interactive:

```bash
./scripts/create-psfs-project.sh
```

Non-interactive:

```bash
./scripts/create-psfs-project.sh \
  --non-interactive \
  --name my-psfs-app \
  --path /tmp/my-psfs-app \
  --runtime docker \
  --package acme/my-psfs-app \
  --description "My PSFS app" \
  --author "Your Name"
```

Remote install with flags:

```bash
curl -fsSL https://raw.githubusercontent.com/psfs/core/master/install.sh | bash -s -- \
  --non-interactive \
  --name my-psfs-app \
  --runtime docker \
  --package acme/my-psfs-app
```

## What the Generator Does

- Detects `local` runtime (`php` + `composer`) and `docker` runtime (`docker compose`).
- If both are available in interactive mode, prompts for runtime selection.
- Generates PSFS base structure (`config`, `html`, `modules`, `cache`, `logs`, `locale`).
- Generates initial `composer.json`.
- For Docker runtime, generates `docker-compose.yml`, `docker/php.ini`, and `.env.example`.
- Does not auto-run dependency install or container startup.

## Documentation

- [Operations Playbook](./doc/OPERATIONS.md)
- [DTO Validation Engine](./doc/DTO_VALIDATION.md)
- [Propel Workflow](./doc/PROPEL_WORKFLOW.md)
- [Core Contracts](./doc/CONTRACTS.md)

## Notes

- Runtime baseline for this repository is Docker Compose with PHP 8.3.
- For project internals and contributor workflows, use the docs listed above.
