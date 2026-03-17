# API Metadata Analysis (Annotations vs Attributes)

## Scope

This analysis covers API metadata currently consumed by routing and API documentation generation in PSFS.

Main files reviewed:

- `src/base/types/Api.php`
- `src/base/types/traits/Api/ManagerTrait.php`
- `src/base/types/traits/Api/MutationTrait.php`
- `src/base/types/helpers/RouterHelper.php`
- `src/base/types/helpers/AnnotationHelper.php`
- `src/base/types/helpers/MetadataReader.php`
- `src/base/types/traits/Api/DocumentorHelperTrait.php`
- `src/base/types/traits/Api/SwaggerFormaterTrait.php`

## What metadata is used today

### Routing metadata (runtime-critical)

- `@route` / `@ROUTE`
- HTTP verb tags:
  - `@GET`, `@POST`, `@PUT`, `@DELETE`, `@PATCH`, `@HEAD`
- `@label`
- `@icon`
- `@visible`
- `@cache`
- `@api`
- `@action`

These are read via `AnnotationHelper` -> `MetadataReader`.

### API documentation metadata (documentor/swagger)

- `@payload`
- `@return`
- `@header`
- `@param`
- `@deprecated`
- `@api` (class-level)

These are still parsed mostly with regex in `DocumentorHelperTrait` / `SwaggerFormaterTrait`.

## Attribute support status

### Already supported by `MetadataReader` (hybrid mode)

When `metadata.attributes.enabled=true`, attributes are read first and annotations are fallback:

- `Api` (`#[Api("...")]`)
- `Route` (`#[Route("...")]`)
- `Action` (`#[Action("...")]`)
- `Label` (`#[Label("...")]`)
- `Icon` (`#[Icon("...")]`)
- `Visible` (`#[Visible(true|false)]`)
- `Cacheable` (`#[Cacheable(true|false)]`)
- `HttpMethod` (`#[HttpMethod("GET")]`)
- `Injectable` (properties)
- `Required`, `Values`, `DefaultValue`, `VarType`

### Not yet fully migrated to attributes

- `@payload`
- `@return` contract syntax (`JsonResponse(data=...)`)
- `@header`
- `@deprecated` (native PHP attribute exists, but parser path is still doc-comment based)
- `@param` typing used by documentor fallback

## Recommended migration order for API classes

1. Keep hybrid mode enabled (`attributes -> annotations fallback`) to preserve compatibility.
2. Migrate runtime-critical metadata first in API classes/methods:
   - `@route`, HTTP verb, `@label`, `@icon`, `@visible`, `@cache`, `@api`, `@action`.
3. Add attribute equivalents for documentor-only metadata:
   - payload, headers, return schema hints.
4. Refactor `DocumentorHelperTrait` and `SwaggerFormaterTrait` to read attributes first.
5. Keep annotation fallback until full ecosystem migration is validated.

## Practical API class example (target style)

```php
#[Api("User")]
final class UserApi extends AuthApi
{
    #[Route("/{__DOMAIN__}/api/{__API__}")]
    #[HttpMethod("GET")]
    #[Label("Get list of users")]
    #[Cacheable(true)]
    public function modelList() {}
}
```

## Compatibility notes

- Current framework behavior remains backward-compatible because annotations are still accepted.
- No fallback should be removed until explicit approval and full integration validation.
