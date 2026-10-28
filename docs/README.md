<!-- breadcrumb:start -->
Home
<!-- breadcrumb:end -->

# PSFS documentation

PSFS (Php Simple Fast & Secure) is a lightweight PHP framework for MVC/API applications built on Twig, Propel and Symfony components. This site collects the operational guides, references and design records of the core.

Every page shows its position as a breadcrumb at the top and links to the previous and next page at the bottom.

## Start here

New to the project? Follow this path:

1. [Operations playbook](./operations.md): boot the Docker runtime, run the key tests and the daily commands.
2. [DTO validation engine](./dto-validation.md): how requests are validated and CSRF-protected.
3. [Propel workflow](./propel-workflow.md): schema, model generation, migrations and rollback.
   [Schema changes and migrations](./migrations.md): what the generator writes, ordering guarantees, switches and limits.

Contributing to the core? Add [PHP compatibility](./php-compatibility.md) and the [security overview](./security/README.md) to that list, and validate every change inside Docker.

## Guides

| Page | What it covers |
|---|---|
| [Operations playbook](./operations.md) | First-day flow, daily loop, queues, Swoole, troubleshooting |
| [DTO validation engine](./dto-validation.md) | Declarative validation, unknown-field policy, CSRF in DTOs |
| [Propel workflow](./propel-workflow.md) | Schema conventions, `psfs:migrate`, engines, rollback |
| [Schema changes and migrations](./migrations.md) | Declarative Phinx migrations: guarantees, ordering, switches, limits, tests |

## Reference

| Page | What it covers |
|---|---|
| [PHP compatibility](./php-compatibility.md) | Supported PHP versions, validation baseline, Propel and PHP 8.5 |

## Security

| Page | What it covers |
|---|---|
| [Security overview](./security/README.md) | Required properties, policy, gates and contracts |
| [Admin auth cookie secret](./security/auth-cookie-secret.md) | Configure and rotate `auth.cookie.secret` |
| [Hardening and compatibility switches](./security/hardening-switches.md) | Behaviours closed by default and the deprecated switches that restore them |

## Admin UI

| Page | What it covers |
|---|---|
| [Admin UI overview](./admin-ui/README.md) | Admin 2.0 migration scope and boundaries |
| [UI development](./admin-ui/development.md) | Angular workspace, HMR, static publishing, e2e matrix |
| [Regression contract](./admin-ui/regression-contract.md) | Behaviour Admin 2.0 must preserve |
| [Design history](./superpowers/README.md) | Specs and implementation plans |

## Conventions

- Run project commands inside Docker (`docker exec psfs-php-1 ...`). The validation baseline is PHP 8.3.
- Command blocks are tagged `validated` or `example-only`.
- Page names are lowercase and hyphenated; each section has a `README.md` as its index.

<!-- nav:start -->
---

Next: [Operations playbook](./operations.md) →
<!-- nav:end -->
