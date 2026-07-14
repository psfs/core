# UI HMR, E2E and Static Fallback Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete same-origin Angular development with HMR, Docker-native browser regression tests and static SPA fallback for production.

**Architecture:** Swoole runs `WebSocket\\Server`, a subtype of its HTTP server, so ordinary requests keep their current handler. A bridge authenticates and upgrades only a configured UI mount to the Vite upstream and relays frames in both directions. Production never proxies: static UI assets are served from `src/public/ui`, with an internal SPA fallback only for the configured mount.

**Tech Stack:** PHP 8.3, Swoole 6.2.1, Angular 22, Node 24.15.0, Playwright container.

## Global Constraints

- Execute PHP, Node and test commands through Docker containers.
- Do not write, reset or regenerate `config/config.json`; PHPUnit uses `config.json.phpunit.bak` only.
- Protect UI mounts with PSFS admin authentication before any HTTP or WebSocket upstream connection.
- Do not forward PSFS cookies or Basic credentials to Node.
- Preserve the configured `ui.path`; `/ui` is the POC value.

### Task 1: Same-origin HMR WebSocket bridge

**Files:** `src/runtime/swoole/SwooleCommandService.php`, `src/runtime/swoole/UiDevelopmentWebSocketBridge.php`, `tests/runtime/swoole/SwooleCommandServiceTest.php`, `tests/runtime/swoole/UiDevelopmentWebSocketBridgeTest.php`, `ui/package.json`.

- [ ] Add failing unit tests for WebSocket callback registration and mount-only bridge selection.
- [ ] Make Swoole use `WebSocket\\Server` while retaining the HTTP request callback.
- [ ] Implement a bridge which validates the mount/upstream, authorizes with PSFS, forwards WebSocket headers and relays browser/upstream frames.
- [ ] Enable Angular HMR and prove a source edit changes the DOM under `localhost:8011` without console errors.
- [ ] Commit the independently verified HMR unit.

### Task 2: Docker Playwright regression suite

**Files:** `docker-compose.yml`, `ui/package.json`, `ui/playwright.config.mjs`, `ui/e2e/ui-development.spec.mjs`.

- [ ] Add the official Playwright image as a non-default Compose profile.
- [ ] Test authenticated same-origin load, unauthenticated `401`, unavailable upstream `502`, static SPA fallback and HMR DOM update.
- [ ] Run the suite from Docker and retain no host browser dependency.
- [ ] Commit the independently verified E2E unit.

### Task 3: Static SPA fallback

**Files:** `src/runtime/swoole/SwooleStaticAssetServer.php`, `src/runtime/swoole/SwooleRequestHandler.php`, relevant tests and `docs/ui-development.md`.

- [ ] Add failing tests for serving `/ui/<client-route>` from `src/public/ui/index.html` after admin authentication.
- [ ] Preserve normal assets, non-UI routes and path traversal protections.
- [ ] Build Angular, stop the development upstream and prove browser load through the static fallback.
- [ ] Run PHPUnit, Docker Compose validation and the E2E suite; commit the independently verified fallback.
