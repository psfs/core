# DTO Validation Engine (API + CSRF)

## Purpose

PSFS now supports declarative validation directly in DTOs, including optional CSRF enforcement, without depending on Twig `Form` objects.

This provides:

- Cleaner API input validation.
- Explicit, testable contracts per endpoint.
- Opt-in CSRF validation in DTOs for admin/session contexts.
- Strict unknown-field rejection by default.

## Core Components

- `PSFS\base\dto\ValidatableDtoTrait`
- `PSFS\base\dto\ValidationContext`
- `PSFS\base\dto\ValidationResult`
- `PSFS\base\dto\CsrfValidator`
- `PSFS\base\dto\Dto` (integrates trait)

## Validation Attributes

Supported attributes under `src/base/types/helpers/attributes`:

- `Required` (existing)
- `VarType` (existing)
- `Values` (existing enum semantics)
- `DefaultValue` (existing)
- `Pattern`
- `Min`
- `Max`
- `Length`
- `Nullable`
- `CsrfProtected` (DTO-level)
- `CsrfField` (DTO-level, optional custom field names)

## Validation Flow

When `validate()` is called on a DTO:

1. Input is hydrated (`fromArray()` or explicit setters).
2. Default values are applied.
3. Unknown fields are checked (`strictUnknownFields=true` by default).
4. Per-property constraints are validated:
   - required
   - type
   - enum/values
   - pattern
   - length
   - min/max
5. If DTO has `#[CsrfProtected]`, CSRF token is validated.
6. A `ValidationResult` is returned and can be queried via:
   - `isValid()`
   - `getErrors()`
   - `getValidationErrors()`

## Unknown Fields Policy

Default behavior is **fail-closed**:

- Any payload key not declared in DTO public properties is rejected.
- Error code: `unknown_field`.

This prevents accidental mass-assignment and hidden payload drift.

## CSRF in DTOs

CSRF is fully declarative:

- Add `#[CsrfProtected(formKey: '...')]` to the DTO class.
- Optionally add `#[CsrfField(tokenField: '...', tokenKeyField: '...')]`.

Resolution order:

1. Payload fields (`tokenField`, `tokenKeyField`).
2. Header fallback (`X-CSRF-Token` by default, configurable via `CsrfProtected`).

Validation enforces one-time token semantics and expiration using `csrf.expiration`.

## Example DTO

```php
<?php

namespace PSFS\base\dto;

use PSFS\base\types\helpers\attributes\CsrfField;
use PSFS\base\types\helpers\attributes\CsrfProtected;
use PSFS\base\types\helpers\attributes\Length;
use PSFS\base\types\helpers\attributes\Pattern;

#[CsrfProtected(formKey: 'admin_setup')]
#[CsrfField(tokenField: 'admin_setup_token', tokenKeyField: 'admin_setup_token_key')]
class DeleteUserRequestDto extends Dto
{
    /** @required */
    #[Length(min: 1, max: 64)]
    #[Pattern('/^[a-zA-Z0-9._-]+$/')]
    public ?string $username = null;
}
```

## API Usage Pattern

```php
$dto = DeleteUserRequestDto::fromArray($request->getRawData());
$result = $dto->validate(ValidationContext::fromRequest($request));

if (!$result->isValid()) {
    return ApiResponse::error($result->getFirstErrorMessage(), 400, $result->getErrors());
}
```

Notes:

- Prefer `Request::getRawData()` for payload fidelity in API controllers.
- Use `ValidationContext::fromRequest($request)` to include headers for CSRF fallback.

## Backward Compatibility

- Legacy Twig/Form flow remains available during migration.
- DTO validation is opt-in: only DTOs calling `validate()` are enforced.
- Existing DTOs without attributes keep current behavior.

## Migration Strategy

1. Start with mutation endpoints (create/update/delete).
2. Introduce DTO per endpoint command.
3. Add explicit constraints and optional `CsrfProtected`.
4. Replace ad-hoc checks in controller/service with `validate()`.
5. Keep legacy paths temporarily where needed.

## Testing Recommendations

Mandatory for each new validated DTO:

- Unit tests for rules:
  - required/type/enum/pattern/min-max/length/defaults/nullable
  - unknown fields rejected
- CSRF tests (when applicable):
  - valid token
  - missing token
  - expired token
  - replay token
- Integration tests:
  - success path (`200`)
  - validation error path (`4xx`)

## Security Notes

- Default strict unknown-field policy is intentional.
- CSRF should be enabled for browser/session-admin mutations.
- Machine-to-machine/JWT-only endpoints may skip CSRF by design.
