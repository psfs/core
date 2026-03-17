# Security Policy

## Operational baseline

- Run the project only with Docker Compose.
- Use `docker exec <php_container> <command>` for runtime and checks.
- Required runtime PHP version is **8.3**.
- English documentation is the source of truth for security contracts.

## Mandatory validation flow

Use the PHP container for validation:

```bash
docker compose up -d
docker compose ps
docker exec <php_container> php -v
docker exec <php_container> php vendor/bin/phpunit
```

Project runtime port is managed from `.env` (`HOST_PORT=8008`).

Do not run `php`, `composer`, or `phpunit` directly on host for project validation.

## Auth and cookie contract (v2)

- Auth/cookies are versioned in `v2`.
- Legacy fallback is allowed only in read-only mode.
- Fallback retirement rule: remove fallbacks only after explicit user approval.
- Invalid auth must result in `null/null` and request flow must stop.

Cookie target policy:

- `HttpOnly=true`
- `Secure=true` when HTTPS
- `SameSite=Lax` or `SameSite=Strict`
- `Path=/`
- coherent `Domain`
- TTL aligned with auth/session policy

## CORS and request hardening

- Validate `Origin` against an explicit allowlist.
- Do not rely on `Referer` for security decisions.
- Avoid reflecting arbitrary origins when credentials are enabled.

## Transition notes

- Metadata migration is hybrid (annotations -> attributes) during transition.
- Translation migration is dual-provider (custom + gettext) without gettext shutdown in this phase.
- Compatibility fallbacks stay active until explicit user approval to retire them.

## Commit and review policy

- No automatic commits from agents.
- Human review is mandatory before committing security-related changes.
