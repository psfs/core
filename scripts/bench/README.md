# PSFS request benchmark

Measures real HTTP requests against an isolated copy of the framework for every combination of the knobs
that matter for latency: opcache, `debug`, number of modules, Redis and table size.

```bash
scripts/bench/run.sh --quick                       # smoke test, about 10 seconds
scripts/bench/run.sh                               # default matrix, a few minutes
scripts/bench/run.sh --scenarios=api-list --rows=1000,100000 --opcache=on --debug=false
scripts/bench/run.sh --opcache=off,on,jit --modules=2,20,50 --iterations=300
scripts/bench/run.sh --repeat=3 --scenarios=page --opcache=on --redis=off,sessions,on
scripts/bench/run.sh --help
```

`run.sh` starts the compose services (`php`, `redis`, `db`) and runs `bench.php` inside the `php` container,
as the rest of the project does. The results are a Markdown table on stdout; progress goes to stderr. Add
`--json=PATH` (a path inside the container) for the raw numbers.

## What it does

1. Copies `src/`, `vendor/` and the API test support files to `/tmp/psfs-bench-*` inside the container.
2. Generates its own modules with the framework generator: a public page module (`BENCHPAGE`), the `CLIENT`
   API module with its MySQL database (reusing `tests_api/support/ClientModuleHarness.php`) and as many dummy
   modules as the largest `--modules` value needs. `modules/` is git-ignored, so nothing depends on the
   modules you have locally.
3. For each cell of the matrix it sets the config (`debug`, `psfs.redis`), empties the cache, starts a fresh
   `php -S` with the requested opcache settings, sends one cold request, discards `--warmup` requests and
   measures `--iterations` requests.
4. Removes the sandbox, the MySQL database and the Redis keys created during the run (see limitations).

The repository is never modified.

## Layout

`bench.php` is only the entry point. The code lives in `lib/`, one small class per responsibility
(namespace `Psfs\Bench`, loaded by the autoloader in `bench.php`):

| Class | Responsibility |
|---|---|
| `Options`, `Scenarios`, `Matrix` | Command line options, the requests to send, and the cells (the product of every dimension) |
| `Sandbox`, `SandboxScripts`, `Dataset` | The isolated copy of the framework, the scripts that run inside it, and the rows of the API table |
| `Server`, `Probe`, `Services` | The `php -S` process, the HTTP requests, and the Redis and MySQL counters |
| `CellRunner`, `Stats`, `Reporter` | Measuring a cell, the statistics, and the Markdown/JSON report |
| `Application` | The run itself and its cleanup |

The pure logic (options, matrix, statistics, report) is covered by `tests/scripts/bench`.

## Scenarios

| Scenario | Request | What it exercises |
|---|---|---|
| `page` | `GET /BENCHPAGE` | Bootstrap, routing, controller injection and a Twig page, no database |
| `api-get` | `GET /client/api/test/1` | The above plus the API stack and Propel for one row |
| `api-list` | `GET /client/api/test?__limit=20&__page=1` | Pagination, `COUNT(*)` and the i18n join; this is the one that depends on `--rows` |

## Columns

| Column | Meaning |
|---|---|
| `cold ms` | First request after emptying `cache/`, with a fresh server (route, Twig and reflection caches rebuilt) |
| `p50`, `p95` | Wall clock of the HTTP request, after warm-up |
| `php p50` | Time inside PHP, from request start to shutdown |
| `peak MB` | `memory_get_peak_usage()`; it does not include the opcache shared memory |
| `files` | PHP files loaded by the request |
| `redis conn / cmd`, `db conn / q` | Connections opened and commands or queries issued per request, read from the server counters |
| `est@N` | `p50 + (db q + redis cmd + 2 x (db conn + redis conn)) x N ms`: the same request with Redis and MySQL N ms away |

`redis` values: `off` (file sessions, `psfs.redis=false`); `sessions` (Redis sessions only, as in `docker/php.ini`);
`on` (Redis sessions and `psfs.redis=true`).

`opcache` values: `off`; `on` (`validate_timestamps=0`, JIT off); `jit` (tracing, like `docker/php.ini`). The
server reports its real opcache state for each request. A `!` after the value in the table means it did not
match what was requested; do not trust that row.

## Limitations

- It runs on PHP-CLI's built-in server: one process, no keep-alive, no PHP-FPM. Compare cells with each other
  rather than with production numbers. The php container runs the PHP version of the compose image
  (`PHP_VERSION`, 8.5 by default); use `PHP_VERSION=8.3 docker compose up -d` to measure another one.
- MySQL and Redis are on the same Docker network, so their latency is close to zero. The `est@N` columns are a
  model, not a measurement: they count round trips, they do not simulate packet loss or bandwidth.
- Counters come from the servers' global statistics (`Questions`, `Connections`, `total_commands_processed`).
  Run it on an idle stack: the compose health checks add about 0.01 to 0.05 connections per request.
- Redis cleanup deletes every key that did not exist when the run started, so do not use the development
  application against the same Redis while the benchmark is running.
- `/tmp` in the container is much faster than the bind-mounted project. Expect file system heavy cells to be
  slower on a bind mount or a network volume.
- Run-to-run noise is large on Docker Desktop: the same 4 to 6 ms cell gave p50 values between 3.8 and 6.5 ms in
  six runs. Effects of 2x or more (opcache, remote Redis or MySQL, many modules) are reliable; differences under
  about 30 % between two cells are not. Use `--repeat=3` (or more) to get the median and the p50 range of every
  cell, and compare cells from the same run.

## Module regeneration

`scripts/bench/regenerate.sh` measures something different: how long it takes to regenerate a module with many
tables and where the time and the peak memory go, phase by phase (see `docs/operations.md`, section 4.7).

```bash
scripts/bench/regenerate.sh                                 # 10 and 50 tables, every state
scripts/bench/regenerate.sh --tables=30,100 --states=regen,repeat
```

Each measure runs in a fresh PHP process against the sandbox and a temporary MySQL database, both removed
afterwards. `tree` is a checksum of the generated files (except the migrations): compare it between two runs of
different code to prove that a change does not alter the output (`Config/propel.php` varies on its own).
