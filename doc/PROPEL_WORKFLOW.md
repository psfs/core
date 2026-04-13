# PSFS Propel Workflow

Status: operational guide
Scope: schema conventions, model generation context, migrations, rollback, usage patterns

## 1) Baseline assumptions

- Runtime: Docker Compose + PHP 8.3
- CLI entrypoint: `php src/bin/psfs`
- Migration command supports engines: `phinx` (default) and `propel`
- Legacy fallback behavior can route to `propel` based on migration resolver config

## 2) Required inputs and conventions

### 2.1 Schema location and shape

PSFS generator templates expect module schema under module config path as `schema.xml`.

Reference template:

- `src/templates/generator/schema.propel.twig`

Template conventions:

- `<database name="{{ namespace }}" ... namespace="{{ namespace }}\\Models" tablePrefix="{{ prefix }}_">`
- vendor defaults from config (`db.vendor`, default `mysql`)

### 2.2 Module DB config context

Reference template:

- `src/templates/generator/config.propel.template.twig`

Connection values come from module-scoped config keys:

- `db.host`
- `db.port`
- `db.name`
- `db.user`
- `db.password`

## 3) End-to-end operational sequence

### Step 1: Validate migration CLI availability

<!-- validated -->
```bash
docker exec core-php-1 php src/bin/psfs list
```

Expected marker: command list includes `psfs:migrate` and `psfs:migrate:rollback`.

### Step 2: Run migration in simulate mode

<!-- validated -->
```bash
docker exec core-php-1 php src/bin/psfs psfs:migrate --simulate=1
```

Expected marker: command ends with `Migration completed`.

### Step 3: Force Propel engine explicitly (when needed)

<!-- example-only -->
```bash
docker exec core-php-1 php src/bin/psfs psfs:migrate --module=<MODULE> --simulate=1 --engine=propel
```

Use this when validating legacy engine behavior or fallback scenarios.

### Step 4: Rollback path

<!-- validated -->
```bash
docker exec core-php-1 php src/bin/psfs psfs:migrate:rollback --simulate=1
```

<!-- example-only -->
```bash
docker exec core-php-1 php src/bin/psfs psfs:migrate:rollback --module=<MODULE> --engine=propel
```

### Step 5: Model generation context in code

Model and SQL generation are performed through generator service internals (`GeneratorService` + `PropelHelperTrait`).

<!-- example-only -->
```php
use PSFS\services\GeneratorService;

$generator = GeneratorService::getInstance();
$generator->createStructureModule('Client', force: false, skipMigration: false);
```

Notes:

- This path generates module structure, Propel model artifacts, and SQL artifacts.
- Exact invocation entrypoint is project integration dependent (service usage, custom command, or admin flow).

## 4) Usage pattern in app/service layer

Use generated model classes under module namespace convention (`<Module>\\Models`).

<!-- example-only -->
```php
use Client\Models\BookQuery;

$book = BookQuery::create()->findPk(1);
```

Treat this as integration pattern; concrete class names depend on your schema.

## 5) Rollback and recovery playbook

### 5.1 Safe rollback sequence

<!-- example-only -->
```bash
docker exec core-php-1 php src/bin/psfs psfs:migrate:rollback --module=<MODULE> --engine=phinx
docker exec core-php-1 php src/bin/psfs psfs:migrate:rollback --module=<MODULE> --engine=propel
```

### 5.2 Recovery checks

<!-- validated -->
```bash
docker exec core-php-1 php src/bin/psfs psfs:migrate --simulate=1
```

Ensure result is deterministic and does not produce unexpected pending changes.

## 6) Known failure modes

| Failure mode | Indicator | Action |
|---|---|---|
| Schema mismatch vs DB | Generated diff when no intended schema change | Re-check module `schema.xml`, datasource mapping, and compare generated SQL |
| Stale generated classes | Runtime model behavior does not match schema | Regenerate module model artifacts through generator path |
| Migration drift across engines | Different results between `phinx` and `propel` | Force explicit engine in command and review resolver fallback config |
| Missing module DB config | `Module without DB configuration, skipping process` | Ensure module `Config` and DB keys are present before migrate |

## 7) Engine references in source

- `src/services/MigrationService.php`
- `src/services/migration/MigrationEngineResolver.php`
- `src/services/migration/PhinxMigrationEngine.php`
- `src/services/migration/PropelMigrationEngine.php`
- `src/base/types/traits/Generator/PropelHelperTrait.php`

