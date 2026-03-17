# QA Note PR-3 (non-blocking)

Date: 2026-03-17  
Reviewed scope: `ResponseHelper`, `AuthHelper`, `Router`  
Type: security/quality/performance review, non-blocking for delivery

## Findings and minimum recommended adjustments

1. `Router::executeCachedRoute` mixes params with query-string taking final priority.
Recommendation: prevent `Request::getQueryParams()` from overwriting critical route params (`id`, `slug`, etc.) in sensitive actions.  
Minimum fix: merge with route-priority (`array_merge(query, actionDefaults, routeParams)`) or use an override allowlist.

2. `AuthHelper::checkComplexAuth` validates Basic token using `str_contains`.
Recommendation: use strict prefix validation (`preg_match('/^Basic\\s+/i', ...)`) to avoid ambiguous partial matches.  
Impact: stronger parsing without breaking the expected functional contract.

3. `AuthHelper` keeps legacy crypto/hash fallback.
Status: correct for controlled legacy compatibility.  
Recommendation: add aggregated counter/telemetry by context (not per request) to plan progressive deprecation without noisy logs.

## General status

- No blocking regressions were detected in the reviewed changes.
- The recommendations above are incremental and low risk.
