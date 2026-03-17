# ADR-003: ResponseHelper Refactor Guardrails

## Intent

Refactor `ResponseHelper` for clearer response policies and safer cookie/header handling.

## Risks

- Header emission inconsistencies.
- Cookie policy regressions (`Secure`, `HttpOnly`, `SameSite`, domain/path).
- Error response format drift.

## Compatibility

- Preserve response contract expected by controllers/clients.
- Keep security cookie defaults aligned with documented policy.
- Avoid introducing hidden side effects during response emission.

## Rollback

- Restore prior response helper path.
- Validate response/cookie regression matrix before retry.

