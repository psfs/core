# Admin 2.0 Native Areas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reemplazar los cinco paneles administrativos legacy por interfaces Angular 22 nativas que consumen contratos JSON v2 autenticados.

**Architecture:** `AdminFrontendController` se divide en adaptadores JSON específicos por área, apoyados en una capa común que transforma `ConfigForm`, `AdminForm` y `ModuleForm` en DTOs de formulario. Angular consume exclusivamente estos contratos mediante `AdminApiService`; las páginas no cargan Twig, AngularJS ni documentos embebidos.

**Tech Stack:** PHP 8.3, PSFS Router/Form/DTO, Angular 22 standalone, RxJS, Docker Compose, PHPUnit y Playwright.

## Global Constraints

- Ejecutar PHP, Composer y PHPUnit exclusivamente con `docker exec <php-container> ...` sobre PHP 8.3.
- No modificar, vaciar ni regenerar `config/config.json` durante pruebas.
- No incluir `iframe`, Twig ni AngularJS dentro de `/admin-v2/*`.
- Mantener `/admin/*` legacy y `__front=legacy|v2` como rollback.
- Cada mutación conserva la autorización PHP existente; no se delega autorización al frontend.
- No crear commits sin validación humana explícita.

---

### Task 1: Eliminar el puente iframe y definir contratos Angular comunes

**Files:**
- Modify: `ui/projects/admin/src/app/admin-shell.component.ts`
- Create: `ui/projects/admin/src/app/admin-api.service.ts`
- Create: `ui/projects/admin/src/app/admin-contracts.ts`
- Modify: `ui/projects/admin/src/styles.css`
- Test: `ui/projects/admin/src/app/admin-api.service.spec.ts`

**Interfaces:**
- Produces `AdminEnvelope<T> = { ok: boolean; message: string|null; data: T; errors: Record<string,string[]> }`.
- Produces `AdminFormSchema = { name: string; title: string; fields: AdminField[] }`.
- Produces `AdminApiService.get<T>(path)`, `put<T>(path, payload)`, `post<T>(path, payload)`, `delete<T>(path, payload?)`.

- [ ] **Step 1: Write the failing Angular service tests**

```ts
it('unwraps a successful administrative envelope', () => {
  service.get<{ value: string }>('/admin/api/v2/example').subscribe((result) => {
    expect(result.data.value).toBe('ok');
  });
  request.expectOne('/admin/api/v2/example').flush({ ok: true, message: null, data: { value: 'ok' }, errors: {} });
});

it('keeps structured backend errors', () => {
  service.put('/admin/api/v2/example', {}).subscribe({ error: (error) => {
    expect(error.errors.name).toEqual(['Required']);
  }});
  request.expectOne('/admin/api/v2/example').flush({ ok: false, message: 'Invalid form', data: null, errors: { name: ['Required'] } }, { status: 422, statusText: 'Unprocessable Entity' });
});
```

- [ ] **Step 2: Run the focused Angular test and confirm it fails because the service does not exist**

Run inside Node container: `npm run test -- --include='**/admin-api.service.spec.ts'`

Expected: compilation failure naming `AdminApiService`.

- [ ] **Step 3: Implement `admin-contracts.ts` and `AdminApiService`**

```ts
export interface AdminEnvelope<T> {
  ok: boolean;
  message: string | null;
  data: T;
  errors: Record<string, string[]>;
}

get<T>(path: string): Observable<AdminEnvelope<T>> {
  return this.http.get<AdminEnvelope<T>>(`/admin/api/v2/${path}`);
}
```

For `POST`, `PUT` and `DELETE`, call the same root with JSON payloads and preserve `HttpErrorResponse.error` when it matches `AdminEnvelope<null>`.

- [ ] **Step 4: Replace `AdminLegacyPanelComponent` and every iframe route**

Delete the component and iframe CSS. Add native route components only; each route starts with a loading, success and structured error state.

- [ ] **Step 5: Run Angular test and build**

Run inside Node container: `npm run test` and `npm run build:admin`

Expected: both complete with no iframe selector or `bypassSecurityTrustResourceUrl` in `ui/projects/admin`.

- [ ] **Step 6: Human validation checkpoint**

Do not commit. Present the new shell screenshot and `git diff` for approval.

### Task 2: Crear la serialización segura y adaptadores de formulario PHP

**Files:**
- Create: `src/base/admin/AdminFormSchemaFactory.php`
- Create: `src/base/admin/AdminApiResponse.php`
- Test: `tests/base/admin/AdminFormSchemaFactoryTest.php`

**Interfaces:**
- Consumes objects `ConfigForm`, `AdminForm` and `ModuleForm` ya construidos.
- Produces `AdminFormSchemaFactory::fromForm(object $form): array` con `name`, `title`, `fields` y sin tokens/valores secretos.
- Produces `AdminApiResponse::success(array $data, ?string $message = null): array` y `::failure(string $message, array $errors = []): array`.

- [ ] **Step 1: Write failing PHP tests for schema normalization**

```php
public function testRemovesSecurityTokensAndMasksSecretValues(): void
{
    $schema = (new AdminFormSchemaFactory())->fromForm($form);
    self::assertArrayNotHasKey('__token', $schema['fields']);
    self::assertSame('', $schema['fields']['root.api.secret']['value']);
}

public function testKeepsFieldTypeOptionsAndRequiredFlag(): void
{
    self::assertSame('select', $schema['fields']['profile']['type']);
    self::assertTrue($schema['fields']['profile']['required']);
    self::assertNotEmpty($schema['fields']['profile']['options']);
}
```

- [ ] **Step 2: Run the test inside PHP Docker and confirm it fails**

Run: `docker exec <php-container> php vendor/bin/phpunit tests/base/admin/AdminFormSchemaFactoryTest.php`

Expected: class-not-found failure.

- [ ] **Step 3: Implement the schema factory and response helper**

Use the existing form field metadata (`getFields()` and DTO serialization). Preserve only `name`, `label`, `type`, `value`, `required`, `options`, `help` and validation rules. Omit CSRF/session tokens and blank values for fields whose name contains `secret`, `password`, `token` or `hash`.

- [ ] **Step 4: Run the focused PHP tests**

Run: `docker exec <php-container> php vendor/bin/phpunit tests/base/admin/AdminFormSchemaFactoryTest.php`

Expected: PASS.

- [ ] **Step 5: Human validation checkpoint**

Do not commit. Confirm the emitted schema contains no current secret from `config.json`.

### Task 3: Migrar rutas y documentación como páginas Angular de sólo lectura

**Files:**
- Create: `src/controller/AdminFrontendRoutesController.php`
- Create: `ui/projects/admin/src/app/routes-page.component.ts`
- Create: `ui/projects/admin/src/app/documentation-page.component.ts`
- Modify: `ui/projects/admin/src/app/admin-shell.component.ts`
- Test: `tests/controller/AdminFrontendRoutesControllerTest.php`
- Test: `ui/projects/admin/src/app/routes-page.component.spec.ts`
- Test: `ui/projects/admin/src/app/documentation-page.component.spec.ts`

**Interfaces:**
- Produces `GET /admin/api/v2/routes` as `{ ok:true, data:{ routes: RouteRow[] } }`.
- Produces `POST /admin/api/v2/routes/regenerate` as a structured operation result.
- Produces `GET /admin/api/v2/docs` as `{ domains: string[] }` and `GET /admin/api/v2/docs/{domain}` as OpenAPI/PSFS document JSON.

- [ ] **Step 1: Write failing controller tests**

```php
public function testRoutesEndpointReturnsTheRouterCatalogWithoutHtml(): void
{
    $response = $controller->routes();
    self::assertStringContainsString('"ok":true', $response);
    self::assertStringNotContainsString('<html', $response);
}

public function testRegenerationKeepsTheExistingAuthorizationBoundary(): void
{
    $this->expectException(ApiException::class);
    $controller->regenerate();
}
```

- [ ] **Step 2: Run focused controller tests and confirm failure**

Run: `docker exec <php-container> php vendor/bin/phpunit tests/controller/AdminFrontendRoutesControllerTest.php`

Expected: route-controller class missing.

- [ ] **Step 3: Implement endpoints by calling Router and DocumentorService directly**

`routes()` uses `Router::getInstance()->getSlugs()`. `regenerate()` keeps the exact `hydrateRouting()` flow and returns success/failure JSON. Documentation validates `Router::domainExists($domain)` before invoking the same `DocumentorService` formatter used by `DocumentorController`.

- [ ] **Step 4: Implement Angular native pages**

`RoutesPageComponent` renders sortable route rows and an explicit regeneration button. `DocumentationPageComponent` lists domains, requests a selected document and presents its JSON or a Swagger-compatible link. Both display `AdminEnvelope` errors.

- [ ] **Step 5: Run focused tests, Angular build and browser check**

Run PHP: `docker exec <php-container> php vendor/bin/phpunit tests/controller/AdminFrontendRoutesControllerTest.php`

Run Node: `docker exec <node-container> npm run build:admin`

Browser: authenticate with Basic Auth and load `/admin-v2/routes` and `/admin-v2/api/docs`; assert no iframe element and successful JSON requests.

- [ ] **Step 6: Human validation checkpoint**

Do not commit. Validate route regeneration manually only after reviewing its action and result.

### Task 4: Migrar configuración a formulario Angular nativo

**Files:**
- Create: `src/controller/AdminFrontendConfigController.php`
- Create: `ui/projects/admin/src/app/dynamic-form.component.ts`
- Create: `ui/projects/admin/src/app/config-page.component.ts`
- Modify: `ui/projects/admin/src/app/admin-shell.component.ts`
- Test: `tests/controller/AdminFrontendConfigControllerTest.php`
- Test: `ui/projects/admin/src/app/config-page.component.spec.ts`

**Interfaces:**
- Produces `GET /admin/api/v2/config` with `AdminFormSchema`.
- Produces `PUT /admin/api/v2/config` with `AdminEnvelope<{ changed: string[] }>` or status 422 with field errors.
- Consumes payload `{ values: Record<string, unknown>; extra: Record<string, unknown> }`.

- [ ] **Step 1: Write failing tests for safe read and invalid write**

```php
public function testConfigReadDoesNotExposeConfiguredSecrets(): void
{
    $body = $controller->show();
    self::assertStringNotContainsString((string) Config::getParam('root.api.secret'), $body);
}

public function testInvalidConfigWriteReturnsFieldErrorsAndDoesNotSave(): void
{
    $response = $controller->update();
    self::assertSame(422, ResponseHelper::getStatusCode());
    self::assertStringContainsString('errors', $response);
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `docker exec <php-container> php vendor/bin/phpunit tests/controller/AdminFrontendConfigControllerTest.php`

Expected: class-not-found failure.

- [ ] **Step 3: Implement config adapter using `ConfigForm`**

Build `ConfigForm` from the same required/optional arrays and `dumpConfig()` as `ConfigController`. For update, hydrate only the supplied JSON payload, run `isValid()`, then call `Config::save()` only on valid data and return changed field names. Keep `assertSuperAdminConfigWriteAccess()` equivalent in the adapter.

- [ ] **Step 4: Implement reusable dynamic form and config page**

Render text, password, checkbox, select and textarea fields using Reactive Forms. Do not render masked secrets as existing values. On success, display returned message; on 422, attach each error to its form control.

- [ ] **Step 5: Verify without changing config.json**

Run controller tests with injected form doubles. In browser, load `/admin-v2/config`, submit an invalid payload and assert errors render. Do not submit a successful mutation against the shared config.

- [ ] **Step 6: Human validation checkpoint**

Do not commit. Request approval before a manually verified valid config write.

### Task 5: Migrar gestión de usuarios a Angular nativo

**Files:**
- Create: `src/controller/AdminFrontendUsersController.php`
- Create: `ui/projects/admin/src/app/users-page.component.ts`
- Modify: `ui/projects/admin/src/app/admin-shell.component.ts`
- Test: `tests/controller/AdminFrontendUsersControllerTest.php`
- Test: `ui/projects/admin/src/app/users-page.component.spec.ts`

**Interfaces:**
- Produces `GET /admin/api/v2/users` with users redacted and schema `AdminForm`.
- Produces `POST /admin/api/v2/users`, `PUT /admin/api/v2/users/{user}`, `DELETE /admin/api/v2/users/{user}`.
- Uses `AdminServices::getAdmins()`, `AdminForm`, `Security::save()` and `Security::deleteUser()`.

- [ ] **Step 1: Write failing tests for redaction, create validation and delete authorization**

```php
public function testUsersListDoesNotReturnHashesOrPasswords(): void
{
    $body = $controller->index();
    self::assertStringNotContainsString('hash', $body);
    self::assertStringNotContainsString('password', $body);
}

public function testDeleteUsesTheExistingSuperAdminGuard(): void
{
    $this->expectException(ApiException::class);
    $controller->delete('other-admin');
}
```

- [ ] **Step 2: Run focused tests and confirm failure**

Run: `docker exec <php-container> php vendor/bin/phpunit tests/controller/AdminFrontendUsersControllerTest.php`

- [ ] **Step 3: Implement the users adapter**

Return aliases and profiles only. Reuse `AdminForm` validation for create/update. Reuse the existing super-admin condition from `UserController` before every mutation. Preserve status 400/403/422 in structured JSON.

- [ ] **Step 4: Implement users list and form page**

Render table rows, create/edit form and a destructive action requiring an explicit confirmation. Do not expose password hashes; a password field is write-only.

- [ ] **Step 5: Verify with isolated test doubles and browser**

Run PHP and Angular focused tests. In browser, verify list rendering and invalid-create error. Do not create or delete actual local administrators without human approval.

- [ ] **Step 6: Human validation checkpoint**

Do not commit. Obtain approval before a persistent create/update/delete browser test.

### Task 6: Migrar generador de módulos a Angular nativo

**Files:**
- Create: `src/controller/AdminFrontendModulesController.php`
- Create: `ui/projects/admin/src/app/modules-page.component.ts`
- Modify: `ui/projects/admin/src/app/admin-shell.component.ts`
- Test: `tests/controller/AdminFrontendModulesControllerTest.php`
- Test: `ui/projects/admin/src/app/modules-page.component.spec.ts`

**Interfaces:**
- Produces `GET /admin/api/v2/modules/schema` with `ModuleForm` schema.
- Produces `POST /admin/api/v2/modules` with `AdminEnvelope<{ module: string }>`.
- Uses `ModuleForm`, `GeneratorService::createStructureModule()` and legacy normalization rules.

- [ ] **Step 1: Write failing controller tests**

```php
public function testModuleSchemaIsJsonAndContainsControllerTypeOptions(): void
{
    $body = $controller->schema();
    self::assertStringContainsString('controllerType', $body);
    self::assertStringNotContainsString('<form', $body);
}

public function testInvalidModuleNameReturns422BeforeGeneratorIsCalled(): void
{
    $response = $controller->create();
    self::assertSame(422, ResponseHelper::getStatusCode());
    self::assertStringContainsString('errors', $response);
}
```

- [ ] **Step 2: Run focused test and confirm failure**

Run: `docker exec <php-container> php vendor/bin/phpunit tests/controller/AdminFrontendModulesControllerTest.php`

- [ ] **Step 3: Implement schema and create endpoints**

Build and hydrate `ModuleForm`. Keep the legacy module normalization (`\\` and `/` handling) and call `GeneratorHelper::checkCustomNamespaceApi()` before `GeneratorService::createStructureModule()`. Return generator exceptions as `AdminEnvelope` failures without HTML.

- [ ] **Step 4: Implement native module generator page**

Reuse `DynamicFormComponent`; show a pre-submit warning that this operation writes a module. Display returned module name and structured failure text.

- [ ] **Step 5: Verify safely**

Run focused PHP/Angular tests. In browser, submit invalid data only. Create no module until the user supplies the intended test-module specification.

- [ ] **Step 6: Human validation checkpoint**

Do not commit. Await user instruction for the test module creation.

### Task 7: Eliminar legacy embeds, documentar y ejecutar paridad de navegación

**Files:**
- Modify: `ui/projects/admin/src/app/admin-shell.component.ts`
- Modify: `ui/README.md`
- Modify: `docs/ui-development.md`
- Create: `ui/e2e/admin-v2-core.spec.mjs`
- Test: `tests/base/AdminFrontendVersionResolverTest.php`

**Interfaces:**
- Playwright uses Basic Auth `admin/admin` and loads `/admin/*?__front=legacy` and `/admin/*?__front=v2`.
- Browser proof asserts each v2 core page has no `iframe`, no request for legacy `/admin/*` document, and calls its JSON v2 endpoint.

- [ ] **Step 1: Write failing Playwright coverage**

```js
for (const path of ['config', 'setup', 'module', 'routes', 'api/docs']) {
  await page.goto(`/admin/${path}?__front=v2`);
  await expect(page.locator('iframe')).toHaveCount(0);
  await expect(page.locator('psfs-admin-root')).toBeVisible();
}
```

- [ ] **Step 2: Run the focused E2E test and confirm it fails while embeds remain**

Run: `docker compose --profile e2e run --rm ui-e2e npx playwright test e2e/admin-v2-core.spec.mjs`

Expected: failure on iframe count or missing JSON endpoints before Tasks 3-6 complete.

- [ ] **Step 3: Remove all remaining iframe implementation and references**

Delete `AdminLegacyPanelComponent`, iframe styles and any `bypassSecurityTrustResourceUrl` import. Update routes to native page components.

- [ ] **Step 4: Update documentation**

Document endpoint contracts, the no-iframe invariant, Docker commands and the temporary HMR direct-socket limitation. Keep rollback instructions using `__front=legacy`.

- [ ] **Step 5: Run final verification**

Run PHP: `docker exec <php-container> php vendor/bin/phpunit tests/base/AdminFrontendVersionResolverTest.php tests/controller/AdminFrontend*ControllerTest.php`

Run Node: `docker exec <node-container> npm run build:admin`

Run browser E2E: `docker compose --profile e2e run --rm ui-e2e npx playwright test e2e/admin-v2-core.spec.mjs`

Expected: all pass, no config write, no iframe and no legacy document request from a v2 route.

- [ ] **Step 6: Human validation checkpoint**

Do not commit. Present test output, screenshots and the full diff for approval.

## Plan self-review

- Spec coverage: Tasks 1-7 cover removal of embeds, JSON contracts, five native areas, preserved backend checks, rollback compatibility and browser verification.
- Placeholder scan: every task has concrete files, interfaces, test cases and commands; persistent writes explicitly require human validation.
- Type consistency: all Angular requests use `AdminEnvelope<T>` and all PHP adapters return the same structured envelope.
