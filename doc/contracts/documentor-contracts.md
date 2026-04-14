# Documentor Contracts (Swagger + OpenAPI + Postman)

## Scope
- `src/controller/DocumentorController.php`
- `src/services/DocumentorService.php`
- `src/base/types/traits/Api/SwaggerFormaterTrait.php`

## Contracts
1. `GET /{domain}/api/doc?type=swagger`
- Returns Swagger 2.0 JSON.
- Preserves existing keys: `swagger`, `paths`, `definitions`, `info`, `host`, `basePath`.

2. `GET /{domain}/api/doc?type=postman`
- Returns Postman Collection v2.1 JSON.
- Required keys: `info`, `variable`, `item`.
- `info.schema` must be `https://schema.getpostman.com/json/collection/v2.1.0/collection.json`.

3. `GET /{domain}/api/doc?type=openapi`
- Returns OpenAPI 3.1 JSON.
- Required keys: `openapi`, `info`, `servers`, `paths`, `components.schemas`.

4. Download mode
- `download=1&type=swagger` returns `swagger.json`.
- `download=1&type=postman` returns `postman.collection.json`.
- `download=1&type=openapi` returns `openapi.json`.

5. Filtering rules
- Endpoints under `/admin/*` and `/api/*` are excluded from generated docs.
- Route visibility/deprecation behavior remains unchanged.

6. Postman request shaping
- URL path params are emitted using `:param` syntax.
- Query/header metadata is projected from endpoint metadata.
- For `POST`/`PUT`, payload is exported as raw JSON with known fields.

## Compatibility
- No public route changes.
- Swagger behavior kept as-is (legacy).
- OpenAPI support is additive and recommended for new integrations.
- Postman support remains additive.

## Test Evidence
- `tests/services/DocumentorServiceTest.php` validates Swagger + OpenAPI + Postman contract skeleton.
- `tests/base/controller/CoverageControllersTest.php` validates controller paths, downloads and Swagger UI route.
