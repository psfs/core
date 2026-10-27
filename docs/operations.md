<!-- breadcrumb:start -->
[Home](./README.md) › [Guides](./README.md#guides) › Operations playbook
<!-- breadcrumb:end -->

# Operations playbook

Status: active
Runtime baseline: Docker Compose + PHP 8.3

Compose defaults to PHP 8.5. To validate against the baseline, start the stack with `PHP_VERSION=8.3 docker compose up -d`. PHP 8.3, 8.4 and 8.5 are supported targets; see [PHP compatibility](./php-compatibility.md).

## 1) First day flow

### 1.1 Boot and verify runtime

<!-- validated -->
```bash
docker compose up -d
docker compose ps
docker exec psfs-php-1 php -v
docker exec psfs-php-1 php src/bin/psfs list
```

Expected markers:

- `psfs-php-1` is `Up`
- `php src/bin/psfs list` prints PSFS commands (`psfs:migrate`, `psfs:queue:*`, `psfs:swoole:*`)

### 1.2 Verify security-critical tests

<!-- validated -->
```bash
docker exec psfs-php-1 php vendor/bin/phpunit --no-coverage --filter '/(AuthApiTest|RequestResponseSecurityContractTest)/'
```

Expected marker: `OK` with tests executed.

## 2) Daily development loop

### 2.1 Start / stop / reset

<!-- validated -->
```bash
# Start
docker compose up -d

# Stop
docker compose stop

# Full teardown (containers + networks)
docker compose down
```

### 2.2 Install/update dependencies

<!-- validated -->
```bash
docker exec psfs-php-1 composer install --no-interaction --prefer-dist
docker exec psfs-php-1 composer audit --no-interaction
```

### 2.3 Run security gates locally

<!-- validated -->
```bash
docker exec psfs-php-1 php scripts/security/hardening_gate.php
docker exec psfs-php-1 php scripts/security/quality_gate.php
```

<!-- example-only -->
```bash
# CI workflow emulation
act push --container-architecture linux/amd64
```

## 3) Common workflows

### 3.1 Queue runtime

Important: queue jobs must be implemented and discoverable by `JobRegistry` before dispatching.

<!-- example-only -->
```bash
docker exec psfs-php-1 php src/bin/psfs psfs:queue:dispatch --code=<job_code> --payload='{"k":"v"}'
docker exec psfs-php-1 php src/bin/psfs psfs:queue:work --queue=<queue_name> --max-jobs=10 --stop-when-empty=1
docker exec psfs-php-1 php src/bin/psfs psfs:queue:run-parallel --queue=<queue_name> --workers=2 --stop-when-empty=1
```

### 3.2 Swoole runtime

<!-- validated -->
```bash
docker exec psfs-php-1 php src/bin/psfs psfs:swoole:check
```

<!-- example-only -->
```bash
docker exec psfs-php-1 php src/bin/psfs psfs:swoole:start --host=0.0.0.0 --port=8080
docker exec psfs-php-1 php src/bin/psfs psfs:swoole:status
docker exec psfs-php-1 php src/bin/psfs psfs:swoole:reload
docker exec psfs-php-1 php src/bin/psfs psfs:swoole:stop
```

## 4) Optimization knobs (safe defaults)

### 4.1 Runtime and PHP container

- Keep Docker as execution baseline for consistency with project contracts.
- Use `--no-coverage` for local quick feedback.
- Prefer filtered tests during iterative work; run broader suites before merge.

### 4.2 Queue backend strategy

- Priority path is Redis when available.
- File queue is the persistent fallback.
- Sync queue remains test-focused and in-process only.

### 4.3 Cache and deployment path

<!-- example-only -->
```bash
docker exec psfs-php-1 php src/bin/psfs psfs:deploy:project
```

Use deploy command when you need to regenerate document root + route hydration in one path.

### 4.4 Log granularity

Every log record has a threshold. `log.level` is the global one (`DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY`, default `WARNING`; unknown values fall back to the default) and a channel can override it with `log.level.<channel>`:

| Key | Applies to |
| --- | --- |
| `log.level.php` | PHP errors, warnings and notices captured by the error handler |
| `log.level.deprecation` | `E_DEPRECATED` / `E_USER_DEPRECATED` (separate channel so PHP upgrades do not flood the logs). Default `ERROR`, i.e. silenced until you lower it |
| `log.level.cache` | Cache internals |
| `log.level.curl` | `CurlService` request/response traces |
| `log.level.security` | Admin session, auth cookies, token rejections and legacy auth fallbacks. Default `WARNING`, so raising `log.level` does not hide auth events |
| `log.deprecation.mode` | `once` (default, no configuration needed: one record per message/file/line per process), `log` (every occurrence), `off` |

With the default `WARNING` threshold you get errors, fatals, PHP warnings and degraded-mode signals (Redis fallbacks, auth rejections); notices, info/debug traces and PHP deprecations are not recorded. Lower `log.level` or a channel level while investigating (deprecations need `log.level.deprecation=NOTICE`). Records below the threshold are discarded before the log file is opened, so a quiet configuration costs no filesystem I/O. Only real PHP failures (fatal errors) are reported to Slack; warnings, notices and deprecations are not. `log.output` (`file|stdout|stderr`) keeps selecting the sink.

Channel names can be extended in code: `Logger::log($msg, LOG_DEBUG, $context, false, 'mychannel')` reads `log.level.mychannel`.

## 5) Troubleshooting matrix

| Symptom | Likely cause | Fix command |
|---|---|---|
| `Queue job "..." is not registered` | No queue job class implementing `QueueJobInterface::code()` discovered by registry | Implement/register job, then rerun dispatch (`psfs:queue:dispatch`) |
| `Module without DB configuration, skipping process` on migrate | Target module lacks `Config/` DB setup | Ensure module config exists, then rerun `psfs:migrate` |
| `psfs:swoole:check` reports missing runtime requirement | Missing extension/config in image | Rebuild/use image variant with required runtime extension |
| Security gate status `block` | must-pass test failed or blocking finding exists | Run failing test filter + inspect `security/contracts/findings.json` |
| PHPUnit exits with xdebug coverage warning | Coverage mode expected by config | Run with `--no-coverage` for local fast run |

## 6) Related references

- [DTO Validation Engine](./dto-validation.md)
- [Propel Workflow](./propel-workflow.md)
- [PHP compatibility](./php-compatibility.md)
- [Security policy](../SECURITY.md)
- [Admin auth cookie secret](./security/auth-cookie-secret.md)
- [Security contracts](../security/contracts)

<!-- nav:start -->
---

← Previous: [Home](./README.md) · Next: [DTO validation engine](./dto-validation.md) →
<!-- nav:end -->
