<!-- breadcrumb:start -->
[Home](../README.md) › Admin UI overview
<!-- breadcrumb:end -->

# Admin UI overview

Admin 2.0 replaces the Twig and AngularJS administration screens with Angular 22 under `/admin-v2/`. It is a presentation migration: the PSFS domain, persistence and generated module APIs are not rewritten, and the legacy `/admin/*` routes remain as fallback and rollback.

Most pages in this section are written in Spanish.

## Pages

| Page | What it covers |
|---|---|
| [UI development](./development.md) | Docker workflow, HMR through Swoole, static publishing, verification matrix |
| [Regression contract](./regression-contract.md) | Behaviour Admin 2.0 must preserve (config, users, forms, docs) |
| [Design history](../superpowers/README.md) | Design specs and implementation plans, phase by phase |

## Responsibility boundary

| Layer | Allowed | Not allowed |
|---|---|---|
| Angular 22 | Navigation, presentation, forms, errors, confirmations | Authorizing, persisting or reinterpreting domain rules |
| `AdminFrontend*` controllers | Bootstrap, safe form schema, JSON envelope | Duplicating business services or a parallel CRUD manager |
| PSFS forms and services | Validation, normalization, current effects | Being replaced by browser-only validation |
| `/admin/*` legacy | Fallback and rollback | Removal without explicit human approval |

The authoritative version is the [phase 0 specification](../superpowers/specs/2026-09-09-admin-v2-phase-0-ui-boundary.md).

<!-- nav:start -->
---

← Previous: [Admin auth cookie secret](../security/auth-cookie-secret.md) · Next: [UI development](./development.md) →
<!-- nav:end -->
