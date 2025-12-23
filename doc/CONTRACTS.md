# PSFS Core Contracts

> Status: verified
> Version: 1.1  
> Audience: framework users and contributors

This document defines the **behavioural contracts** of the core PSFS runtime.
The goal is to make it explicit which behaviours are stable and safe to rely on,
and which areas are considered internal implementation details.

Anything explicitly documented here is treated as **public API**.  
Breaking these contracts requires a **major version bump**.

---

## 1. Core runtime

### 1.1 Dispatcher

**Responsibility**

The `Dispatcher` is the main entry point of PSFS:

- It bootstraps the framework.
- It resolves the incoming HTTP request into a route.
- It delegates to the matching controller / action.
- It returns a `Response` instance to the client.

**Contract**

- Access:
  - `Dispatcher::getInstance()` returns a singleton instance.
  - `Dispatcher::run()` is the main entrypoint and is meant to be called **once per HTTP request**.
- Behaviour:
  - `run()` must:
    - Create or obtain the current `Request`.
    - Resolve the route using `Router`.
    - Invoke the matched controller/service.
    - Produce a `Response` and send it (or return it, depending on integration).
  - Framework errors must surface as PSFS-specific exceptions (see Exception section) or as an HTTP 500.
- Stability:
  - The existence of `getInstance()` and `run()` is stable.
  - The dispatcher class must remain the canonical way to start PSFS for HTTP requests.

**Non-contractual / internal**

- How configuration is loaded internally.
- The exact sequence of internal bootstrap steps, as long as the external behaviour above holds.

---

### 1.2 Request

**Responsibility**

`Request` is a representation of the incoming HTTP request PSFS is handling:

- HTTP method, URI, query string.
- Route parameters.
- Body and parsed payload (e.g. JSON, form data).
- Headers and cookies.

**Contract**

- Instances are created and owned by the framework.
  - User code should obtain the current `Request` via PSFS facilities (DI, helpers, etc.), not by `new`-ing it directly.
- A `Request` instance must be **consistent** during the lifecycle of a single HTTP request:
  - Basic properties (method, URI, headers, query params, route params) must not change once initialised.
- Access to request data must be **idempotent**:
  - Multiple reads of the same field (e.g. query param, header) within a single request must return the same value.
- If the body has been parsed (JSON, form data, etc.), subsequent accesses should re-use the parsed representation without re-parsing the raw stream.

**Non-contractual / internal**

- Exact method names and internal storage details.
- Whether the class is strictly immutable or just “effectively immutable” from userland perspective.

---

### 1.3 Response

**Responsibility**

`Response` encapsulates everything PSFS will send back to the client:

- HTTP status code.
- Headers.
- Body (plain text, JSON, HTML, file/download, etc.).

**Contract**

- Controllers and services may:
  - Create new responses.
  - Modify an existing response passed to them.
- Once a `Response` has been sent (flushed to the client), PSFS must not modify it further.
- Standard behaviour guarantees:
  - Setting status, headers and body will result in the corresponding HTTP response being sent by PSFS.
  - Response must be capable of representing:
    - Regular HTML / text responses.
    - JSON API responses.
    - Redirects (by setting appropriate status + `Location` header).
- Error handling:
  - When a PSFS exception bubbles up without being caught, PSFS will either:
    - Map it to an appropriate HTTP response (if configured), or
    - Produce an HTTP 500 with minimal debug information in production.

**Non-contractual / internal**

- Output buffering strategy.
- Whether the response is sent implicitly in `run()` or explicitly by another layer.

---

### 1.4 Router

**Responsibility**

`Router` is responsible for:

- Discovering routes (from annotations, configuration, cache, etc.).
- Matching the current `Request` to a controller/action.
- Generating URLs for named routes (usually consumed via `RouterHelper`).

**Contract**

- Route resolution:
  - For a given HTTP method + path, `Router` must either:
    - Resolve exactly one matching route, or
    - Signal “no route” via a well-defined exception or 404 behaviour.
  - Route matching must be deterministic for the same inputs.
- Routing cache:
  - In production, route discovery may be cached for performance.
  - Invalidating the routing cache must not change the set of available routes, only performance.
- URL generation:
  - Generating a URL from a valid route name + parameters must produce a stable URL structure, unless a breaking change is explicitly introduced.

**Non-contractual / internal**

- Exact format and location of route cache files.
- Implementation details of annotation scanning and route compilation.

---

### 1.5 Security

**Responsibility**

`Security` centralises security-related behaviour for PSFS:

- Authentication hooks / helpers.
- Authorization checks (roles, permissions, etc.).
- CSRF and other standard web security concerns when enabled.

**Contract**

- There must be a single, canonical way in PSFS to:
  - Check whether a user is authenticated.
  - Inspect identity / roles / permissions.
  - Perform authorization decisions for routes / controllers.
- When an authorization decision fails:
  - PSFS will either:
    - Throw a specific security-related exception, or
    - Return an appropriate HTTP status (401/403) according to configuration.
- Security checks must be **side-effect free** in terms of domain state:
  - Checking permissions must not modify domain entities.

**Non-contractual / internal**

- The storage mechanism for users/roles.
- Exact mapping of security failures to HTTP responses (this can be configured).

---

### 1.6 Cache

**Responsibility**

`Cache` provides a framework-level cache abstraction used by PSFS itself and optionally by user code:

- File-based or other storage for expensive computations.
- Route reflection and annotation caches.
- Twig / template caches.

**Contract**

- Cache API must provide at least:
  - Storing a value by key (with optional TTL or invalidation semantics).
  - Retrieving a value by key.
  - Deleting a key.
- Cache keys and namespaces used by PSFS itself are considered internal, but:
  - PSFS will not unexpectedly purge user-space cache namespaces.
- When caching is disabled for development:
  - PSFS must still function correctly, at the cost of performance.
- When cache is cleared:
  - PSFS must be able to regenerate all required internal caches from source code and configuration.

**Non-contractual / internal**

- Exact file layout of cache directories.
- How keys are encoded (hash functions, prefixes, etc.).

---

## 2. Helpers

Helpers are thin, convenient facades over the core runtime. They are intended for **application code**, but they should not introduce additional global state.

### 2.1 I18nHelper

**Responsibility**

- Provide translation and localisation utilities backed by PSFS' locale system.
- Hide the underlying translation engine and file formats from userland.

**Contract**

- Must expose functions to:
  - Translate a message key with optional placeholders.
  - Switch or inspect the current locale (within the boundaries allowed by PSFS configuration).
- Helper functions must be safe to call in:
  - Controllers.
  - Templates.
  - Services (where localisation is required).

---

### 2.2 SecurityHelper

**Responsibility**

- Provide easier access to `Security` functionality from controllers and templates.

**Contract**

- Convenience methods must be direct proxies to `Security` contracts:
  - E.g. “is user authenticated?”, “does user have role X?”, etc.
- No additional security state may be maintained in the helper itself.

---

### 2.3 AuthHelper

**Responsibility**

- Focused on authentication concerns (login, logout, current user access).

**Contract**

- Must provide:
  - A way to retrieve the current authenticated identity.
  - A way to trigger login / logout flows (where supported by the framework).
- Authentication failures must surface using the same exception / HTTP behaviour as defined in the Security contract.

---

### 2.4 RouterHelper

**Responsibility**

- User-facing URL generation and route inspection.

**Contract**

- Must provide:
  - URL generation from a route name + parameters, using `Router` under the hood.
  - Stability of generated URLs for the same route name and param set, unless a breaking change is explicitly introduced.
- It must never bypass the router’s own cache/logic.

---

## 3. Service layer

PSFS encourages encapsulating business logic into “services”. The framework provides base classes that define how services interact with the runtime.

### 3.1 Base Service

**Responsibility**

- Common behaviours shared by all PSFS services:
  - Access to configuration.
  - Logging.
  - Access to the DI container, if available.
  - Common error-handling utilities.

**Contract**

- Extending the base service must give access to:
  - Core framework utilities (logger, config, cache, etc.) in a consistent way.
- The base service should not enforce HTTP concerns directly (those belong to controllers), but:
  - It may throw PSFS exceptions that get translated by higher layers.

---

### 3.2 CurlService

**Responsibility**

- Provide a standard way for PSFS services to perform HTTP client calls using cURL.

**Contract**

- Must:
  - Expose a clear way to perform HTTP calls (GET/POST/PUT/DELETE, etc.).
  - Normalise errors into PSFS exceptions (e.g. timeouts, network errors).
  - Provide a consistent response representation (status code, headers, body) to callers.
- Implementations extending `CurlService` may override how requests are built, but:
  - They must not bypass the framework’s error-handling conventions.

---

### 3.3 SimpleService

**Responsibility**

- Lightweight service base class for simple domain services that:
  - Do not need HTTP client capabilities.
  - Still benefit from PSFS base behaviours (logging, configuration, etc.).

**Contract**

- Extending `SimpleService` vs `CurlService` must be a safe choice:
  - Both must present a compatible surface for common utilities.
- `SimpleService` must not introduce strong coupling to HTTP or curl-specific dependencies.

---

## 4. Exceptions

PSFS defines a set of framework-specific exceptions under `src/base/exception`.

**Responsibility**

- Provide precise error signalling for framework-level concerns:
  - Routing errors.
  - Security / authorization failures.
  - Configuration problems.
  - Internal framework invariants being violated.

**Contract**

- All PSFS exceptions:
  - Extend PHP’s base `\Exception` (directly or indirectly).
  - Have a stable semantic meaning (e.g. “access denied”, “route not found”).
- When a PSFS exception is thrown:
  - It must not be silently swallowed by the framework unless there is a documented mapping to an HTTP response.
- Exception classes under `src/base/exception` are considered part of the public API:
  - Removing or renaming them is a breaking change.
  - Changing the meaning of an existing exception is also a breaking change.

**(TBD) Exception catalogue**

> This section will be filled with a table of all exception classes, including:
>
> - Exception class name
> - Category (routing / security / config / internal error / …)
> - Typical throwers (Dispatcher, Router, Security, etc.)
> - Expected HTTP behaviour (status code) when unhandled

---

## 5. API layer

The API layer is built on top of the components described above.

**Responsibility**

- Bind HTTP routes to controller / service methods.
- Serialise and deserialise request/response payloads.
- Apply security and validation policies.

**High-level contract**

- An API endpoint in PSFS is defined by:
  - A route configuration (often via annotations) pointing to a specific controller/service method.
  - An input contract (parameters, body, validation rules).
  - An output contract (response type, status codes, error shapes).
- For a given API route:
  - The HTTP method and path are stable and treated as public API.
  - The “happy path” response shape is stable once documented.
  - Error responses will follow the exception mapping rules described in the Exception section.

**(TBD) API contract catalogue**

> To be completed progressively:
>
> - List important API endpoints (especially ones exposed to external integrators).
> - Document their request/response contracts.
> - Explicitly tag which ones are stable and versioned.

---

## 6. Compatibility guidelines

When evolving PSFS, changes should be evaluated according to this document:

- **Safe / non-breaking (minor or patch releases):**
  - Internal refactors that keep the contracts intact.
  - Adding new helper methods, as long as they are additive and do not change behaviour of existing ones.
  - Performance optimisations with no semantic changes.

- **Breaking (require a major version bump):**
  - Changing the observable behaviour defined for:
    - Dispatcher, Request, Response, Router, Security, Cache.
  - Removing or renaming public helper classes or methods relied upon by applications.
  - Changing exception types thrown for a given error condition without providing a compatibility layer.
  - Changing API routes, methods, or response shapes that are documented as public.

Contributors are expected to update this `CONTRACTS.md` file when making changes that affect these contracts.