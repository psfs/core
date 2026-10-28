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

Redis is the primary queue backend and a file queue (`job.queue.file.path`, default `cache/queue`) is the fallback. If Redis is down when a job is dispatched, or fails later, the job is written to the file queue instead of being lost. `psfs:queue:work` reads Redis first and then the file queue, so jobs parked during an outage are processed once it is over. Redis connections are retried every 5 seconds, and delivery is at-least-once with ordering guaranteed only inside each backend.


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

A Swoole worker serves many requests in the same PHP process (`--max-request`, 1000 by default, recycles it), and the
runtime resets the singletons, headers, session and request globals around each one. So whatever a request leaves
behind accumulates, and the code that runs in a request must follow three rules:

- Nothing process-wide may keep a reference to the request: `set_error_handler`, `register_shutdown_function` and
  static caches take static methods or plain data, never `$this` of an object built per request (the Dispatcher is
  built again for each request, and its error handler used to stack up and retain it: about 115 KB per request).
- Static caches are bounded (the documentation cache keeps 16 entries, the deprecation log 1000).
- Work that a shutdown function does in php-fpm is done after the response (`SwooleRuntimeStateManager::cleanupAfterRequest`):
  the metadata stale-while-revalidate queue is drained there.

`scripts/bench/swoole.sh` compares the runtime with `php -S` on the same isolated copy (opcache on, debug off) and reads
the resident memory of every worker over a second window of requests, so a leak shows as a growth per 1000 requests
that is not 0 (`--workers=2 --requests=2000 --concurrency=1,4,8`). On a 4 core Docker host it gives about 1.0 ms p50
against 1.8 to 2.7 ms for `php -S` and 700 to 900 requests per second against 450 to 550; the numbers vary between runs and
the generator, MySQL and the workers share the cores, so compare the cells of one run and not with php-fpm.

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

### 4.5 Log destination in containers

Writing log files inside a container filesystem is slow and grows without bound. In Docker, send the logs to stdout/stderr and let the Docker log driver collect and rotate them:

| Key | Env override | Values |
| --- | --- | --- |
| `log.output` | `PSFS_LOG_OUTPUT` | `file` (default outside compose), `stdout`, `stderr` |
| `log.format` | `PSFS_LOG_FORMAT` | `line` (default, human readable) or `json` (one JSON object per line, with `message`, `level_name`, `channel`, `datetime`, `context` and `extra.uid`) |

Every `log.*` key can be overridden by an environment variable named `PSFS_` + the key in upper case with dots as underscores (`log.level.cache` → `PSFS_LOG_LEVEL_CACHE`, `log.deprecation.mode` → `PSFS_LOG_DEPRECATION_MODE`). A non-empty variable wins over the PSFS configuration; an empty one is ignored.

`docker-compose.yml` defaults the `php` and `php-swoole` services to `PSFS_LOG_OUTPUT=stdout` and `PSFS_LOG_FORMAT=json`, and rotates the output of every service with the `json-file` driver (`max-size` 10m, `max-file` 3). Read the logs with `docker compose logs -f php`. To go back to files in `logs/`, set `PSFS_LOG_OUTPUT=file` and `PSFS_LOG_FORMAT=line` in `.env`. `docker/php.ini` sends native PHP errors (the ones PSFS does not capture) to `/dev/stderr`, so they follow the same path.

The phpunit configuration empties the `PSFS_LOG_*` variables, so the test suite does not depend on the container environment.

To ship the logs to a central service, change the log driver instead of the code, for example CloudWatch:

<!-- example-only -->
```yaml
logging:
  driver: awslogs
  options:
    awslogs-region: eu-west-1
    awslogs-group: psfs
    awslogs-stream: php
```

With `log.format=json` CloudWatch Logs Insights can filter by `level_name`, `channel` or any `context` field. A sidecar collector (Fluent Bit, Vector) is only worth adding when you need to filter or enrich records before they leave the host.

### 4.6 Measuring request performance

`scripts/bench/run.sh` measures real HTTP requests against an isolated copy of the framework for every combination of opcache, `debug`, number of modules, Redis and table size, and reports latency, memory, loaded files and the Redis and MySQL round trips per request. Use it to check which knob matters for your setup before changing code (see `scripts/bench/README.md`):

<!-- example-only -->
```bash
scripts/bench/run.sh --quick
scripts/bench/run.sh --scenarios=api-list --rows=1000,100000 --opcache=on --debug=false
```

### 4.8 php -S, Swoole and nginx + php-fpm

`scripts/bench/runtimes.sh --docker-config=<docker dir of a project>` serves the same isolated copy of the application
with the three runtimes and measures `/BENCHPAGE` (Twig page) and an API read at several concurrency levels. It uses the
`nginx/*.conf` and `php/php.ini` + `php/www.conf` of a real project (copied to `cache/`, never into the repository), with
that project's limits: the php container at 1 CPU, nginx at 0.5, php-fpm `pm.max_children=12` with `pm.max_requests=300`,
opcache without timestamp validation and JIT, and Redis sessions. The load generator runs in a third container.
Xdebug is switched off in the three runtimes (the image has it, and with the project's `xdebug.mode` it disables the JIT
and slows php-fpm down by half).

Reference run (PHP 8.5, 1 CPU for PHP, 1500 requests per cell, requests per second and p50 in ms):

| | c=1 | c=8 | c=32 |
|---|---|---|---|
| page, `php -S` | 174 / 5.3 | 176 / 44 | 178 / 176 |
| page, Swoole x2 | 244 / 3.8 | 237 / 30 | 249 / 124 |
| page, nginx + php-fpm | 147 / 6.7 | 107 / 89 | 101 / 305 |
| api-get, `php -S` | 159 / 6.0 | 160 / 47 | 164 / 191 |
| api-get, Swoole x2 | 254 / 3.7 | 264 / 29 | 293 / 85 |
| api-get, nginx + php-fpm | 126 / 7.9 | 107 / 89 | 104 / 301 |

With one CPU every runtime saturates it, so the table compares how much work each does per request: Swoole serves about
1.7x the requests of php-fpm at c=1 and 2.3x at c=8, because it does not start the framework again and does not pay the
process churn of 12 children on one core. The numbers are a reference, not a verdict: the host is a Docker Desktop VM, the
generator sends no `Accept-Encoding` (so nginx does not run its `gzip_comp_level 8`), nginx and Redis are on top of the
1 CPU, every request opens a Redis session in all runtimes, and a serious comparison needs a dedicated Linux host, an
open-loop generator (wrk or k6) and repetitions.

Swoole drops requests when a worker is recycled under concurrency: with `--max-request=300` and 8 connections, 4 of 3000
requests failed (none with 1 connection or without recycling). Run it with a large `--max-request` (the benchmark uses
1000000) now that the per-request leak is fixed, or put a proxy that retries in front of it.

### 4.7 Regenerating modules

Generating or regenerating a module builds the Propel models and SQL, writes the module files, generates the API classes and compares the database with `schema.xml` to produce a migration. Set `generator.profile=true` (or `PSFS_GENERATOR_PROFILE=true`) to log the time and the extra peak memory of each phase (`propel-models`, `propel-sql`, `base-files`, `api-files`, `migration-reverse`, `migration-diff`) in the `[GeneratorProfile]` record. `base-files` includes the Propel build, so phases overlap.

`scripts/bench/regenerate.sh --tables=30,100` measures it on an isolated copy with a synthetic module and a temporary MySQL database (`initial`: no tables yet; `regen`: database up to date; `repeat`: `regen` with the switch below). On a local database, with 100 tables (200 with the i18n tables): about 0.9 s the first time and 1.5 s to regenerate, 45 % of it reading the database (`migration-reverse`: about 9 queries per table, so it grows with the latency to MySQL and with the size of a shared database). Peak memory stays near 50 MB.

| Key | Environment variable | Effect |
|---|---|---|
| `generator.profile` | `PSFS_GENERATOR_PROFILE` | `true` logs the time and memory of every generation phase (default `false`) |
| `generator.migration.skip_unchanged` | `PSFS_GENERATOR_MIGRATION_SKIP_UNCHANGED` | `true` skips the database comparison when neither `schema.xml` nor the migrations changed since the last comparison that found no differences (default `false`). The same 100 tables take 0.8 s instead of 1.5 s. A database changed by hand is not noticed until the schema or the migrations change; delete `cache/generator-sync-<module>.json` to force a comparison |

### 4.9 Sessions

The PHP session is created when something has to be remembered, not for every visitor (`session.lazy`, `true` by default).
A request that carries the session cookie resumes its session as before; one that does not gets no session, no
`Set-Cookie` header and no round trip to the session store (Redis) until the code sets something that has to survive the
request: a login (which also rotates the session id), a CSRF token, a flash message, an explicit language choice. The
bookkeeping the framework writes on every request (`lastRequest`, the route cache descriptor, the cached translations,
the locale it derived) lives in memory for the request and never creates a session by itself.

An anonymous page or API read went from 1 Redis connection and 2 commands per request to none. With a Redis that is not on
the same host that is two round trips less on every request, and Redis is no longer needed for visitors that never log in.
`session.lazy=false` (or `PSFS_SESSION_LAZY=false`) restores one session per request.

Two things to know: the session cookie is a response header, so a session has to be started before the response is sent
(code that sets a persistent value after the output has been flushed gets no session), and an anonymous request no longer
keeps the translation catalogue in the session, so it reads it from the catalogue files.

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
