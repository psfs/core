# Runbook: Validation

## Scope

Validation checklist for local changes before human review.

## Preconditions

- `.env` exists at repository root.
- `HOST_PORT=8008` is configured in `.env`.
- Docker is running.

## 1) Start stack

```bash
docker compose up -d
docker compose ps
```

Identify the PHP container from `docker compose ps` (usually `core-php-1`).

## 2) Verify runtime

```bash
docker exec <php_container> php -v
```

Expected: PHP 8.3.x.

## 3) Run unit tests

```bash
docker exec <php_container> php vendor/bin/phpunit
```

Expected: tests pass.  
Note: a coverage-driver warning can appear when no coverage extension is enabled.

## 4) Run coverage (optional/when available)

```bash
docker exec -e XDEBUG_MODE=coverage <php_container> php vendor/bin/phpunit --coverage-text
```

If Xdebug coverage is not available, keep the unit test run as mandatory baseline.

## 5) Security contract spot-check

- Invalid auth returns `null/null` and flow stops.
- v2 compatibility fallback remains read-only.
- Cookie policy target:
  - `HttpOnly=true`
  - `Secure=true` on HTTPS
  - `SameSite=Lax` or `SameSite=Strict`
  - `Path=/`
  - coherent `Domain`
  - TTL aligned

## 6) Commit policy

- No automatic commit.
- Wait for explicit human approval before committing.
