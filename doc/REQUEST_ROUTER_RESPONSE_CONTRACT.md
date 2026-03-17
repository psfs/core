# Request -> Router -> Response Contract (R1)

## Scope

This contract defines the minimum runtime guarantees across request intake, route resolution and response emission, while keeping legacy fallback behavior intact.

## 1) Request Stage

- Input source: `$_SERVER`, `$_GET`, `$_REQUEST`, `php://input`.
- Required normalized values:
  - HTTP method from `REQUEST_METHOD` (default `GET`).
  - Request URI from `REQUEST_URI` (default empty string).
  - Origin from `HTTP_ORIGIN` (for CORS checks).
- Legacy compatibility retained:
  - Header fallback lookup from query params (`h_<header>`) is preserved.

## 2) Router Stage

- Router matches route pattern + HTTP method and validates requirements.
- On unmet route/requirements, router throws or maps to not-found/denied flow.
- Legacy compatibility retained:
  - Existing route generation, slug lookup and precondition behavior are unchanged.

## 3) Response Stage

### CORS contract

- Validation source: `Origin` (not `Referer`).
- Allowed origin resolution supports:
  - wildcard `*`,
  - regex legacy patterns (`/.../`),
  - CSV allowlist of explicit origins,
  - wildcard host entry (`https://*.example.com`).
- Headers on allowed origin:
  - `Access-Control-Allow-Origin`
  - `Access-Control-Allow-Methods`
  - `Access-Control-Allow-Headers`
  - `Vary: Origin` (non-wildcard case)
  - `Access-Control-Allow-Credentials: true` only when origin is explicit (non-`*`)
- Preflight behavior:
  - `OPTIONS` returns `204 No Content`.

### Cookie contract

- Cookie defaults:
  - `HttpOnly=true`
  - `SameSite=Lax` (supports `Strict` and `None`)
  - `Path=/`
  - `expires=0` when not provided
- Secure behavior:
  - `Secure=true` in HTTPS contexts (`HTTPS`, `REQUEST_SCHEME=https`, `X-Forwarded-Proto=https`, or `force.https`).
  - If `SameSite=None`, `Secure` is enforced to `true`.
- Domain behavior:
  - Domain is normalized (no scheme/port).
  - `localhost` and IP domains are not forced.
- Legacy compatibility retained:
  - `http` cookie key remains accepted as alias of `httpOnly`.

## Validation

- Contract unit checks:
  - [`tests/base/helpers/RequestResponseSecurityContractTest.php`](/Users/fran.lopez/Development/psfs/core/tests/base/helpers/RequestResponseSecurityContractTest.php)
- Run:
  - `HOST_PORT=8008 docker compose up -d`
  - `docker exec core-php-1 php vendor/bin/phpunit --no-coverage`
