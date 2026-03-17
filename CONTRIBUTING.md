# Contributing Guide

## Runtime and execution rules

- Use Docker Compose for all project execution.
- Use `docker exec <php_container> <command>` for project commands.
- Required runtime PHP version is **8.3**.
- Runtime port is configured in `.env` (`HOST_PORT=8008`).

## Documentation baseline

- English documentation is the source of truth.
- Transitional Spanish terms can exist in admin flows, but new terms must be defined in English first.
- Keep public contributor-facing references limited to stable/public docs.

## Compatibility policy (fallbacks)

- Active compatibility fallbacks are temporary.
- Fallbacks can be retired only with explicit user approval.
- Keep fallback behavior read-only where documented (`v2` auth/cookies contract).

## Validation flow (required before review)

```bash
docker compose up -d
docker compose ps
docker exec <php_container> php -v
docker exec <php_container> php vendor/bin/phpunit
```

Coverage (when Xdebug is available):

```bash
docker exec -e XDEBUG_MODE=coverage <php_container> php vendor/bin/phpunit --coverage-text
```

## Structural quality checklist (guardrails)

- Keep controller/service methods focused and short.
- Target cyclomatic complexity per method: <= 10.
- Target method length: <= 40 logical lines (except justified framework glue).
- Target class public API size: <= 12 public methods unless justified.
- Avoid introducing new global state or singleton coupling.
- Avoid cross-layer side effects (helpers should not mutate domain state).
- Prefer explicit failure handling over silent `catch` + empty fallback.
- Keep auth/security contracts explicit (`invalid auth -> null/null + stop flow`).
- Add/adjust tests when changing auth, request/response security, or routing behavior.

## Review and commit policy

- Do not auto-commit.
- Wait for explicit human validation before creating any commit.
