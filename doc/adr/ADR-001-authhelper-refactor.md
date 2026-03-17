# ADR-001: AuthHelper Refactor Guardrails

## Intent

Refactor `AuthHelper` to improve security and maintainability without changing external authentication guarantees.

## Risks

- Legacy session/token parsing regressions.
- Unexpected behavior changes in invalid-auth paths.
- Cookie/security parameter drift.

## Compatibility

- Keep v2 compatibility behavior active during transition.
- Invalid auth behavior must remain explicit and deterministic.
- Any fallback retirement requires explicit user approval.

## Rollback

- Restore previous auth helper behavior behind compatibility mode.
- Re-run targeted security regression tests before rollout retry.

