# Consolidated Regression Matrix (Auth/Router/Response/I18n)

## Core runtime

- Dispatcher boot and request lifecycle.
- Route determinism for representative endpoints.
- Error mapping consistency (401/403/404/500).

## Security and response

- Invalid auth behavior (no action execution).
- Cookie policy contract (`HttpOnly`, `Secure` on HTTPS, `SameSite`, `Path`, domain, TTL).
- CORS policy invariants for allowed/disallowed origins.

## I18n

- Locale extraction variants (header/session/default).
- Provider order contract:
  - merged custom/base catalog
  - gettext fallback
  - original message
- Missing translation report generation and de-duplication.

## Acceptance criteria

- No blocker regressions in the matrix above.
- All targeted regression tests pass in Docker PHP 8.3.
- Any temporary fallback remains documented and approved.

