# Documentor Contracts (Swagger + Postman)

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

3. Download mode
- `download=1&type=swagger` returns `swagger.json`.
- `download=1&type=postman` returns `postman.collection.json`.

4. Filtering rules
- Endpoints under `/admin/*` and `/api/*` are excluded from generated docs.
- Route visibility/deprecation behavior remains unchanged.

5. Postman request shaping
- URL path params are emitted using `:param` syntax.
- Query/header metadata is projected from endpoint metadata.
- For `POST`/`PUT`, payload is exported as raw JSON with known fields.

## Compatibility
- No public route changes.
- Swagger behavior kept as-is.
- Postman support is additive (replaces previous `Pending...` placeholder only).

## Test Evidence
- `tests/services/DocumentorServiceTest.php` validates Swagger + Postman contract skeleton.

