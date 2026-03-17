# AGENTS Instructions

## Project execution rule

This project must always run with Docker Compose.
The PHP version for execution and validation is **8.3**.

- Start services: `docker compose up -d`
- To run any project command, always use `docker exec <container_name> <command>`

## Examples

- `docker exec <container_name> php -v`
- `docker exec <container_name> php vendor/bin/phpunit`
- `docker exec <container_name> composer test`

## Get the PHP container name

If you do not know the container name:

- `docker compose ps`
- or `docker ps --format '{{.Names}}'`

Avoid running PHP/Composer/Test commands directly on the local host.

## Security and authentication contract (coordination)

- Versioned auth/cookies in **v2** with read-only legacy fallback.
- Fallback removal is only allowed with explicit user approval.
- Expected result for invalid auth: `null/null` and the flow must stop.
- Target cookie settings:
  - `HttpOnly=true`
  - `Secure=true` on HTTPS
  - `SameSite=Lax` or `SameSite=Strict`
  - `Path=/`
  - Coherent `Domain`
  - TTL aligned with the session

## Commit policy

- Do not automatically commit agent changes.
- Wait for explicit human validation before any commit.
