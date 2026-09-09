# Admin v2 — Phase 5: activation readiness

**Status:** implemented; the default selector remains `legacy`.

## Objective

Make the Admin 2.0 static bundle self-contained so that activation does not
depend on a public CDN. Keep runtime authentication and version selection
unchanged: the existing administrative guard continues to stop unauthenticated
requests, and the `admin.front.version` resolver retains `legacy` as its
default and its query-string rollback controls.

## Delivered boundary

- Package `swagger-ui-dist` at the exact version `5.32.15`.
- Copy its distribution into the Admin build at
  `/admin-v2/assets/swagger-ui/`.
- Load Swagger's CSS, bundle and standalone preset through the deployed base
  URL, never from `cdn.jsdelivr.net`.
- Exercise the result with the real static-server Playwright suite, including
  an assertion that the bundle was fetched locally and no CDN request occurred.

## Activation gate

Changing `admin.front.version` to `v2` remains an explicit deployment decision.
Before doing it, run the static UI suite and the focused router/authentication
tests in the PHP container, deploy the generated assets with
`psfs:assets:install`, and retain the `__front=legacy` rollback override.
