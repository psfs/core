# Admin 2.0 Phase 1 Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the two currently supported Admin 2.0 mutations preserve the legacy configuration side effects and reject route regeneration without a session CSRF token.

**Architecture:** Keep business operations in their existing PSFS services. `AdminFrontendConfigController` gains small protected seams around the legacy post-save effects so the adapter can call the identical cache/document-root lifecycle and tests can observe it without filesystem writes. `AdminFrontendRoutesController` calls the established `AdminFrontendCsrf` guard before it invokes the Router; its existing Admin authorization remains unchanged.

**Tech Stack:** PHP 8.3, PHPUnit 11, PSFS Config/Router/Security, Docker Compose.

**Spec:** `docs/superpowers/specs/2026-09-09-admin-v2-phase-0-ui-boundary.md`

## Global Constraints

- Execute PHP and PHPUnit only with `docker exec psfs-php-1 ...`.
- Preserve `config/config.json`; tests use controller probes and no real `Config::save()`.
- Do not change Angular, manager APIs, cookies, legacy routes or the default frontend selector.
- Preserve the legacy authorization level for route regeneration: authenticated administrator, not a new super-admin-only operation.
- Do not commit without explicit human validation.

---

### Task 1: Preserve legacy configuration post-save effects

**Files:**
- Modify: `src/controller/AdminFrontendConfigController.php`
- Modify: `tests/controller/AdminFrontendConfigControllerTest.php`

**Interfaces:**
- Produces `applyPostSaveEffects(bool $previousDebug): void` after a successful `save()`.
- `applyPostSaveEffects()` refreshes cache state when current debug is false and clears the document root only when debug changed.
- Exposes protected `debugMode()`, `refreshCacheState()` and `clearDocumentRoot()` seams for controller probes; production implementations delegate to `Config`, `DeployHelper` and `GeneratorHelper`.

- [ ] **Step 1: Write failing configuration parity tests**

```php
public function testSavedProductionConfigRefreshesTheLegacyCacheState(): void
{
    $controller = new AdminFrontendConfigControllerProbe(
        ['values' => ['app.name' => 'PSFS v2'], 'extra' => []],
        [true, false]
    );
    $controller->update();

    self::assertSame(1, $controller->cacheRefreshes);
    self::assertSame(1, $controller->documentRootClears);
}

public function testSavedConfigDoesNotClearDocumentRootWhenDebugDoesNotChange(): void
{
    $controller = new AdminFrontendConfigControllerProbe(
        ['values' => ['app.name' => 'PSFS v2'], 'extra' => []],
        [false, false]
    );
    $controller->update();

    self::assertSame(1, $controller->cacheRefreshes);
    self::assertSame(0, $controller->documentRootClears);
}
```

- [ ] **Step 2: Verify the tests fail before implementation**

Run: `docker exec psfs-php-1 php vendor/bin/phpunit tests/controller/AdminFrontendConfigControllerTest.php`

Expected: assertions fail because no post-save effect is invoked.

- [ ] **Step 3: Add minimal post-save parity implementation**

```php
$previousDebug = $this->debugMode();
if (!$this->save($form->getData(), $this->normalizeExtra($extra))) { /* existing 500 */ }
$this->applyPostSaveEffects($previousDebug);

protected function applyPostSaveEffects(bool $previousDebug): void
{
    $currentDebug = $this->debugMode();
    if (!$currentDebug) $this->refreshCacheState();
    if ($previousDebug !== $currentDebug) $this->clearDocumentRoot();
}
```

- [ ] **Step 4: Verify the focused test suite passes**

Run: `docker exec psfs-php-1 php vendor/bin/phpunit tests/controller/AdminFrontendConfigControllerTest.php`

Expected: all configuration-controller tests pass without modifying `config/config.json`.

### Task 2: Require CSRF before regenerating routes

**Files:**
- Modify: `src/controller/AdminFrontendRoutesController.php`
- Modify: `tests/controller/AdminFrontendRoutesControllerTest.php`

**Interfaces:**
- `POST /admin/api/v2/routes/regenerate` invokes `AdminFrontendCsrf::assertValid()` before router regeneration.
- Existing `assertAdminAuthorization()` remains the authorization boundary after CSRF validation.

- [ ] **Step 1: Write a failing CSRF ordering test**

```php
public function testRegenerationRejectsAMissingCsrfTokenBeforeRouteWork(): void
{
    Security::setTest(false);
    Security::dropInstance();

    $this->expectException(ApiException::class);
    $this->expectExceptionMessage('Invalid CSRF token');
    (new AdminFrontendRoutesControllerProbe())->regenerate();
}
```

- [ ] **Step 2: Verify it fails for the old authorization error**

Run: `docker exec psfs-php-1 php vendor/bin/phpunit tests/controller/AdminFrontendRoutesControllerTest.php`

Expected: failure because the current implementation reports `Restricted area`, demonstrating that no CSRF guard runs first.

- [ ] **Step 3: Add the established CSRF guard**

```php
public function regenerate(): string
{
    AdminFrontendCsrf::assertValid();
    $this->assertAdminAuthorization();
    // existing Router work unchanged
}
```

- [ ] **Step 4: Verify the focused route suite passes**

Run: `docker exec psfs-php-1 php vendor/bin/phpunit tests/controller/AdminFrontendRoutesControllerTest.php`

Expected: missing token returns the established `Invalid CSRF token` API exception; other route/docs tests remain green.

### Task 3: Verify phase-level regression safety

**Files:**
- Verify: `tests/controller/AdminFrontendConfigControllerTest.php`
- Verify: `tests/controller/AdminFrontendRoutesControllerTest.php`
- Verify: `tests/base/admin/AdminFormSchemaFactoryTest.php`

- [ ] **Step 1: Run all affected PHP contracts**

Run: `docker exec psfs-php-1 php vendor/bin/phpunit tests/controller/AdminFrontendConfigControllerTest.php tests/controller/AdminFrontendRoutesControllerTest.php tests/base/admin/AdminFormSchemaFactoryTest.php`

Expected: all pass with no warnings.

- [ ] **Step 2: Check the patch boundary**

Run: `git diff --check && git diff -- src/controller/AdminFrontendConfigController.php src/controller/AdminFrontendRoutesController.php tests/controller/AdminFrontendConfigControllerTest.php tests/controller/AdminFrontendRoutesControllerTest.php`

Expected: only phase-1 parity code and tests are present; unrelated working-tree changes remain untouched.

- [ ] **Step 3: Request human validation before committing**

Present the passing test output and diff. Do not create a commit.
