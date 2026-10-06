# Security Policy

## Reporting a vulnerability

Do not disclose credentials, personal data, or details of an unpatched
vulnerability in a public issue or pull request.

Use GitHub's **Report a vulnerability** option in this repository's
[Security tab](https://github.com/psfs/core/security) when private vulnerability
reporting is available. If that option is unavailable, ask a repository
maintainer to establish a private reporting channel before sharing details.

Include the affected commit or release, PHP version, deployment mode
(HTTP/CLI/Swoole), relevant configuration, prerequisites, expected and actual
behavior, impact, and a minimal reproduction using synthetic data. Redact
secrets and explain any dependency on application-specific code.

Response times and historical release support are not guaranteed by this
policy. Coordinate disclosure and publication of fixes with the maintainers.

## Scope and supported baseline

PSFS Core is a PHP framework. This policy covers framework code, administrative
and API endpoints, authentication, cookies, routing, generated documentation,
file and asset handling, CLI commands, Swoole request handling, dependencies,
and build and security workflows in this repository.

The current review baseline is `master`. PHP 8.3, 8.4 and 8.5 are the tested
compatibility targets; that compatibility does not establish a security
maintenance commitment for every historical release or PHP runtime.

Applications consuming PSFS Core own their deployment configuration, credentials,
business authorization rules and application code. A framework defect reachable
through an application remains reportable.

## Threat model and trust boundaries

Treat request headers, cookies, query parameters, bodies, uploaded files, route
parameters, and responses from external services as untrusted. Review transitions
from these inputs to authenticated identities, protected operations, filesystem
paths, database queries, generated HTML, and outbound requests.

Administrative access does not imply authorization for arbitrary filesystem,
command execution, or cross-user operations. In persistent Swoole workers,
request-local identity, session, configuration and response state must not leak
between requests. Development proxies and tooling require deployment-specific
exposure assessment; their names alone do not make them out of scope.

## Required security properties

These are review requirements, not a claim that every path has been audited:

- Invalid authentication must resolve to `null/null` and stop the protected flow.
- Authentication and cookie formats use v2 with read-only legacy fallback.
  Removing that fallback requires explicit owner approval.
- Token precedence is a valid header, then a valid cookie. A malformed header
  must not prevent validation of an otherwise valid cookie.
- Query-string tokens are legacy compatibility only. The strict hardening
  profile requires `api.query_token.compat=false` and a nonempty
  `api.token.cookie` setting (default `X-API-SEC-TOKEN`).
- Authentication cookies require `HttpOnly`, `Path=/`, `Secure` on HTTPS,
  `SameSite=Lax` or `Strict`, a coherent domain, and a lifetime aligned with the
  session. Security-sensitive mutations must enforce authorization and applicable
  CSRF protection.
- CORS must use an explicit allowlist and deny unapproved origins. The strict
  hardening profile forbids `cors.enabled="*"`.
- Parsing, template generation, uploads and file access must preserve their
  intended boundaries. Untrusted input must not grant arbitrary file access,
  code execution, or unauthorized outbound access.
- Treat `X-API-LANG` as untrusted. Validate it as a locale code before passing it
  to Propel or composing SQL; invalid values fall back to a valid configured
  `default.language` (or `en_US`). Preserve supported two-letter API locale codes.
- Module generation may use nested relative module names for compatibility, but
  rejects traversal, ambiguous, control-character, and drive-prefixed path
  segments. The generator service checks the resolved destination against the
  canonical CORE_DIR before creating directories or files.
- Secrets and sensitive authentication material must not be committed, emitted
  in public artifacts, or exposed through logs or error responses.

## Findings, severity and exclusions

Report authentication or authorization bypasses, session or token compromise,
cross-request data leakage, injection, unsafe file access, exploitable dependency
vulnerabilities, secret exposure, and other demonstrable security boundary
violations. Include realistic reachability, required privileges, configuration,
and impact when proposing severity. A scanner label alone is not proof of impact.

This policy grants no blanket exclusions, severity downgrades, or acceptance of
known vulnerabilities. Tests, generated code, dependencies and development
components may provide evidence of a reachable defect. Findings marked resolved
in `security/contracts/findings.json` are historical records, not suppression
rules; regressions remain reportable.

## GitHub security checks

The executable requirements are defined in
[Security Pipeline](.github/workflows/security-pipeline.yml),
[Snyk Security Scan](.github/workflows/snyk-security.yml), and
[security contracts](security/contracts). This document neither enables those
workflows nor replaces or bypasses their checks.

The Security Pipeline runs authentication/cookie/CORS regression tests, Composer
vulnerability auditing, strict hardening and quality gates, Semgrep, Gitleaks,
and SBOM generation. Reports are uploaded as workflow artifacts. The Snyk workflow
references the repository secret `SNYK_TOKEN` and uploads SARIF; credentials must
be configured through GitHub secrets, never in this file. Its current Code scan
step tolerates a nonzero exit, so a green workflow alone is not proof of a clean
Snyk scan.

Investigate the failed job and its logs when a workflow fails. An absent token,
failed tool execution, dependency advisory, or SARIF upload error is not repaired
by adding this policy. Do not relax security checks to conceal failures.

## Known verification limits

Passing tests and static checks do not establish that an application deployment
is secure. Validate the effective runtime configuration and trust boundaries.

The PHP 8.5 compatibility patch for Propel is applied by Composer's development
patch plugin in this repository. It removes an obsolete XML parser cleanup call;
it is not a vulnerability remediation or an accepted-risk exception. Fresh
`--no-dev` installs and downstream applications do not automatically receive this
patch. See [PHP compatibility](docs/php-compatibility.md).
