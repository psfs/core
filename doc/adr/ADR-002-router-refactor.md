# ADR-002: Router Refactor Guardrails

## Intent

Refactor `Router` internals while preserving route determinism and request-to-handler resolution contract.

## Risks

- Route matching regressions.
- Metadata discovery order drift (annotations/attributes).
- Unexpected 404/500 mapping changes.

## Compatibility

- Route behavior must remain deterministic for same method/path input.
- Hybrid metadata compatibility must remain stable while migration is active.
- No implicit breaking changes in route naming/generation.

## Rollback

- Re-enable previous routing mode/cache behavior.
- Run route regression matrix and compare expected outputs.

