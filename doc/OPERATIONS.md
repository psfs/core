# PSFS Operations Playbook

Status: active
Runtime baseline: Docker Compose + PHP 8.3

## 1) First day flow

### 1.1 Boot and verify runtime

<!-- validated -->
```bash
docker compose up -d
docker compose ps
docker exec core-php-1 php -v
docker exec core-php-1 php src/bin/psfs list
```

Expected markers:

- `core-php-1` is `Up`
- `php src/bin/psfs list` prints PSFS commands (`psfs:migrate`, `psfs:queue:*`, `psfs:swoole:*`)

### 1.2 Verify security-critical tests

<!-- validated -->
```bash
docker exec core-php-1 php vendor/bin/phpunit --no-coverage --filter '/(AuthApiTest|RequestResponseSecurityContractTest)/'
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
docker exec core-php-1 composer install --no-interaction --prefer-dist
docker exec core-php-1 composer audit --no-interaction
```

### 2.3 Run security gates locally

<!-- validated -->
```bash
docker exec core-php-1 php scripts/security/hardening_gate.php
docker exec core-php-1 php scripts/security/quality_gate.php
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
docker exec core-php-1 php src/bin/psfs psfs:queue:dispatch --code=<job_code> --payload='{"k":"v"}'
docker exec core-php-1 php src/bin/psfs psfs:queue:work --queue=<queue_name> --max-jobs=10 --stop-when-empty=1
docker exec core-php-1 php src/bin/psfs psfs:queue:run-parallel --queue=<queue_name> --workers=2 --stop-when-empty=1
```

### 3.2 Swoole runtime

<!-- validated -->
```bash
docker exec core-php-1 php src/bin/psfs psfs:swoole:check
```

<!-- example-only -->
```bash
docker exec core-php-1 php src/bin/psfs psfs:swoole:start --host=0.0.0.0 --port=8080
docker exec core-php-1 php src/bin/psfs psfs:swoole:status
docker exec core-php-1 php src/bin/psfs psfs:swoole:reload
docker exec core-php-1 php src/bin/psfs psfs:swoole:stop
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
docker exec core-php-1 php src/bin/psfs psfs:deploy:project
```

Use deploy command when you need to regenerate document root + route hydration in one path.

## 5) Troubleshooting matrix

| Symptom | Likely cause | Fix command |
|---|---|---|
| `Queue job "..." is not registered` | No queue job class implementing `QueueJobInterface::code()` discovered by registry | Implement/register job, then rerun dispatch (`psfs:queue:dispatch`) |
| `Module without DB configuration, skipping process` on migrate | Target module lacks `Config/` DB setup | Ensure module config exists, then rerun `psfs:migrate` |
| `psfs:swoole:check` reports missing runtime requirement | Missing extension/config in image | Rebuild/use image variant with required runtime extension |
| Security gate status `block` | must-pass test failed or blocking finding exists | Run failing test filter + inspect `security/contracts/findings.json` |
| PHPUnit exits with xdebug coverage warning | Coverage mode expected by config | Run with `--no-coverage` for local fast run |

## 6) Related references

- [Core Contracts](./CONTRACTS.md)
- [Propel Workflow](./PROPEL_WORKFLOW.md)
- [Async Jobs and Connectors Contracts](./contracts/async-jobs-connectors-contracts.md)
- [Security Plan](./security/PLAN.md)
