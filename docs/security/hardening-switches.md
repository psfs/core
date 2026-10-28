<!-- breadcrumb:start -->
[Home](../README.md) › [Security](./README.md) › Hardening and compatibility switches
<!-- breadcrumb:end -->

# Hardening and compatibility switches

Some insecure behaviours of earlier versions are now closed by default. Where closing one can break an existing
project, a switch restores the previous behaviour, and using it is reported as deprecated. The switches will be
removed in a future major version, so treat them as a way to migrate, not as a setting to keep.

Every key can be set in `config/config.json` or through the environment variable `PSFS_` followed by the key in
upper case with dots as underscores (`api.max.limit` is `PSFS_API_MAX_LIMIT`). A non-empty environment variable
wins over the configuration.

## Find out whether your project depends on a legacy behaviour

Using a switch writes a `[Compat]` warning, once per process, to the `security` log channel. That channel logs
warnings by default (`log.level.security`, see [Log granularity](../operations.md#44-log-granularity)), so with
the default configuration the warnings are already in your logs:

```sh
docker compose logs php | grep '\[Compat\]'
```

## Changes with a switch

| Behaviour | Now | Switch to restore the old one | Deprecated warning |
|---|---|---|---|
| `AuthApi` module without `api.secret` (or `<module>.api.secret`) | Access is denied, except for an authenticated admin | `api.auth.allow_unconfigured=true` | Yes |
| Outbound HTTPS calls (`CurlService`) | Certificate and host name are verified | `curl.sslVerifyPeer=false`, `curl.sslVerifyHost=0`, or the same `CURLOPT_SSL_VERIFY*` options on one service | Yes, for HTTPS URLs |
| `__limit` of the generic API lists | Capped to `api.max.limit` (1000) and `__limit=-1` returns that many rows; the response carries `X-PSFS-Limit-Capped` | `api.limit.unbounded=true` | Yes |
| `/{module}/api/doc` resources | `memory_limit` raised to `api.doc.memory_limit` (512M) and `max_execution_time` to `api.doc.max_execution_time` (120 s) | `api.doc.memory_limit=-1`, `api.doc.max_execution_time=0` | Yes |
| PHP session cookie | `HttpOnly`, `Path=/`, `SameSite=Lax` and `Secure` on HTTPS requests | `session.cookie.samesite=legacy` (no `SameSite`), `session.cookie.secure=false` | `legacy` only |

Notes:

- `curl.sslVerifyPeer=false` also needs a trusted CA to go away: prefer adding the CA of your internal services
  to the system store over disabling the check.
- A client that exports everything with `__limit=-1` now gets at most `api.max.limit` rows without an error. Check
  `total` and `pages` in the response, or the `X-PSFS-Limit-Capped` header, and paginate. Raise `api.max.limit`
  if the real page size is larger.
- `session.cookie.samesite` accepts `Lax` (default), `Strict`, `None` (needs a `Secure` cookie, otherwise `Lax` is
  used) and `legacy`. `session.cookie.secure` accepts `auto` (default), `true` and `false`.
- Unknown `type` values of `/api/doc` fall back to `psfs`, and the in-process cache keeps at most 16 documents.

## Changes without a switch

These are bug fixes: no project should depend on the previous behaviour.

- **Admin area check.** `restricted` and the `/admin` gate now look at the same normalised path the router
  matches. A request such as `//x/admin/config` used to skip them.
- **Production JavaScript bundle.** When the sources of a bundle change and an older bundle exists, the bundle is
  rebuilt from the sources. It used to be overwritten with an empty file.
- **`config/admins.json` permissions.** The file is written owner-only (`0600`), like `config.json`. A file
  written by an older version with wider permissions is restricted the first time the administrators are read,
  and a `[Compat]` warning is logged. If the PHP user cannot change it, the warning asks you to run
  `chmod 600 config/admins.json`.
  Like `config.json`, the file has to belong to the user PHP runs as: if a command run by another user (for
  example `root`) rewrites it, the web user will no longer be able to read it.

<!-- nav:start -->
---

← Previous: [Admin auth cookie secret](./auth-cookie-secret.md) · Next: [Admin UI overview](../admin-ui/README.md) →
<!-- nav:end -->
