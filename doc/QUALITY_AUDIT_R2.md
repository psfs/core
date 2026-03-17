# Structural Quality Audit (R2)

Generated with:
- [`tools/quality_audit.php`](/Users/fran.lopez/Development/psfs/core/tools/quality_audit.php)
- Before snapshot: `git archive HEAD src`
- After snapshot: working tree

Run commands:

```bash
HOST_PORT=8008 docker compose up -d
docker exec core-php-1 php /var/www/tools/quality_audit.php /var/www/.tmp_quality/head/src > .tmp_quality/before.json
docker exec core-php-1 php /var/www/tools/quality_audit.php /var/www/src > .tmp_quality/after.json
```

## Guardrails

- Cyclomatic complexity: objective `<=8`, max `<=10`
- Method length: objective `<=40`, max `<=60`
- Class length: objective `<=400`, max `<=600`
- Public methods per class: objective `<=12`, max `<=18`

## Summary (Before -> After)

- Classes total: `90 -> 90`
- Class objective violations: `4 -> 3`
- Class max violations: `1 -> 1`
- Methods total: `441 -> 444`
- Method objective violations: `32 -> 31`
- Method max violations: `15 -> 13`

## Key deltas from this round

### Class-level

- `GeneratorService` improved:
  - class lines `419 -> 314`
  - objective violation `class_lines` cleared
  - change achieved by extracting API-generation logic to trait:
    - [`src/base/types/traits/Generator/ApiGenerationTrait.php`](/Users/fran.lopez/Development/psfs/core/src/base/types/traits/Generator/ApiGenerationTrait.php)

### Method-level

- `RequestHelper::resolveAllowedOrigin`
  - lines `55 -> 27`
  - cyclomatic `18 -> 9`
  - max violation cleared (`>10` -> `9`)
  - achieved via helper extraction:
    - `resolveOriginFromArray`
    - `resolveOriginFromCsvAllowlist`
    - `entryMatchesOrigin`

## Remaining max-level hotspots

- Public methods per class still above max (`>18`) in:
  - `Request` (public methods = 19)
- Method max-level violations reduced but not fully eliminated globally (13 remain).

## Notes

- This audit is static and heuristic-based (token analysis), but reproducible and suitable as a governance baseline.
- Legacy runtime fallbacks were preserved during refactors.
