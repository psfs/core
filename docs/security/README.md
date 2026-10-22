<!-- breadcrumb:start -->
[Home](../README.md) › Security overview
<!-- breadcrumb:end -->

# Security overview

PSFS treats request headers, cookies, query parameters, bodies, uploads and external responses as untrusted. This section groups the security documentation of the core.

## Pages

| Page | What it covers |
|---|---|
| [Security policy](../../SECURITY.md) | Reporting, threat model, required security properties, CI checks |
| [Admin auth cookie secret](./auth-cookie-secret.md) | Configure and rotate `auth.cookie.secret` |

## Required properties at a glance

- Invalid authentication resolves to `null/null` and stops the flow.
- Authentication and cookies use v2 with a read-only legacy fallback; removing it needs explicit owner approval.
- Admin session IDs are rotated on login; if rotation fails, authentication fails closed.
- CORS uses an explicit allowlist.
- `X-API-LANG` is validated as a locale before reaching SQL.

The full list lives in the [security policy](../../SECURITY.md#required-security-properties).

## Gates and contracts

- Run the local gates as described in [Operations playbook, security gates](../operations.md#23-run-security-gates-locally).
- Contracts and findings live in [`security/contracts`](../../security/contracts).
- Related validation rules: [DTO validation engine](../dto-validation.md).

<!-- nav:start -->
---

← Previous: [PHP compatibility](../php-compatibility.md) · Next: [Security policy](../../SECURITY.md) →
<!-- nav:end -->
