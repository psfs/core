# PR QA Checklist (Critical Refactor Track)

- Scope and impacted classes explicitly documented.
- Contract invariants listed (what must not change).
- Backward compatibility/fallback behavior verified.
- Unit regression tests added/updated for changed behavior.
- Docker validation completed (PHP 8.3 in container).
- Targeted phpunit execution attached in PR evidence.
- Coverage run executed when available (`XDEBUG_MODE=coverage`).
- Open risks and rollback steps documented.
- No automatic commit/merge without human review.

