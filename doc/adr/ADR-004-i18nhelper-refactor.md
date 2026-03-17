# ADR-004: I18nHelper Refactor Guardrails

## Intent

Refactor `I18nHelper` to separate responsibilities while preserving locale/session semantics and translation resolution behavior.

## Decision

`CustomTranslateExtension::_()` remains the runtime source-of-truth wrapper for translation calls.

Provider order contract:

1. merged custom/base catalog
2. gettext fallback
3. original message

## Risks

- Locale extraction regressions.
- Session key compatibility break (`language` / `locale` keys).
- Missing translation reporting drift.

## Compatibility

- Keep locale extraction variants compatible.
- Keep session key semantics unchanged during transition.
- Keep dual-provider order stable unless explicitly approved.

## Rollback

- Revert wrapper/provider wiring to previous stable behavior.
- Re-run i18n regression suite before redeploy.

