# Async Jobs, Queue and Connector Contracts

> Status: verified (P0)  
> Runtime: Docker Compose + PHP 8.3

This document describes the async queue baseline available in PSFS core, including queue backends, the queue job contract, CLI commands, and connector hardening.

## 1) What is available now

### Queue transports

- `PSFS\base\queue\JobQueueInterface`
- `PSFS\base\queue\SyncJobQueue`
- `PSFS\base\queue\RedisJobQueue`
- `PSFS\base\queue\FileJobQueue`

Transport contract methods:

- `enqueue(string $queue, array $payload): bool`
- `dequeue(string $queue): ?array`
- `isAvailable(): bool`

### Queue runtime components

- `PSFS\base\queue\QueueJobInterface`
- `PSFS\base\queue\JobRegistry`
- `PSFS\base\queue\QueueDispatcher`
- `PSFS\base\queue\QueueWorker`
- `PSFS\base\queue\ParallelQueueRunner`
- `PSFS\base\queue\QueueBackendFactory`
- `PSFS\Queue\NotificationJob`

Queue job contract methods:

- `public static function code(): string`
- `public static function fromPayload(array $payload): self`
- `public function handle(): void`

Queue messages are stored as envelopes, not raw business objects. The runtime envelope uses this shape:

```php
[
    'code' => 'notifications',
    'queue' => 'notifications',
    'payload' => ['message' => 'Deploy completed'],
    'queued_at' => 1710000000,
    'attempts' => 0,
]
```

### Connector hardening

- `PSFS\base\types\CurlService`
  - default connect timeout: `3s`
  - default request timeout: `10s`
- `PSFS\base\types\helpers\SensitiveDataHelper`
  - redacts sensitive payload fields before external logging or alerts
- `PSFS\base\types\helpers\SlackHelper`
  - sends redacted payload and extra info

## 2) Configuration

Optional keys in `config.json`:

```json
{
  "curl.connectTimeout": 3,
  "curl.timeout": 10,
  "job.queue.file.path": "cache/queue",
  "job.queue.redis.prefix": "psfs:queue",
  "redis.host": "core-redis-1",
  "redis.port": 6379,
  "redis.timeout": 0.2
}
```

Environment overrides:

- `PSFS_REDIS_HOST`
- `PSFS_REDIS_PORT`
- `PSFS_REDIS_TIMEOUT`

## 3) Backend selection and fallback

PSFS resolves the persistent queue backend through `QueueBackendFactory::createPersistent()`.

Selection order:

1. `RedisJobQueue` when Redis is reachable.
2. `FileJobQueue` as the persistent fallback.

`SyncJobQueue` remains useful for isolated unit tests or fully in-process execution, but it is not a cross-process transport.

## 4) Queue job flow

The queue runtime follows this path:

1. Application code or CLI dispatches a job by `code`.
2. `JobRegistry` resolves `code -> class`.
3. `QueueDispatcher` wraps the payload into an envelope and enqueues it.
4. `QueueWorker` consumes a queue, rebuilds the job with `fromPayload()`, and executes `handle()`.
5. `ParallelQueueRunner` starts multiple worker processes for the same queue.

## 5) CLI commands

All commands must run through Docker:

```bash
docker compose up -d
docker exec core-php-1 php /var/www/src/bin/psfs psfs:queue:dispatch --code=notifications --payload='{"message":"Deploy completed"}'
docker exec core-php-1 php /var/www/src/bin/psfs psfs:queue:work --queue=notifications --max-jobs=1 --stop-when-empty=1
docker exec core-php-1 php /var/www/src/bin/psfs psfs:queue:run-parallel --queue=notifications --workers=2 --max-jobs=10 --stop-when-empty=1
```

Command responsibilities:

- `psfs:queue:dispatch`
  - validates `code`
  - parses the JSON payload
  - enqueues the message through the persistent backend
- `psfs:queue:work`
  - consumes one queue
  - executes `QueueJobInterface::fromPayload(...)->handle()`
  - can stop on empty queues for batch-style execution
- `psfs:queue:run-parallel`
  - spawns multiple worker processes for the same queue
  - is suitable for batch or catch-up execution

## 6) Queue job implementation example

Minimal job contract:

```php
use PSFS\base\queue\QueueJobInterface;

final class NotificationJob implements QueueJobInterface
{
    public static function code(): string
    {
        return 'notifications';
    }

    public static function fromPayload(array $payload): self
    {
        return new self($payload);
    }

    public function handle(): void
    {
        // perform the work
    }
}
```

The queue name defaults to the job code. If needed, `psfs:queue:dispatch` can override the queue name explicitly with `--queue=...`.

## 7) Usage examples

### 7.1 Sync queue (test-friendly mode)

Use when you need deterministic behavior without external dependencies.

```php
use PSFS\base\queue\SyncJobQueue;

$queue = new SyncJobQueue();
$queue->enqueue('notifications', [
    'type' => 'slack.error',
    'message' => 'Job failed',
]);

$job = $queue->dequeue('notifications');
if (null !== $job) {
    // process job synchronously
}
```

### 7.2 Persistent queue backend (recommended runtime path)

Use the backend factory when you want Redis first and a file-based fallback automatically.

```php
use PSFS\base\queue\JobRegistry;
use PSFS\base\queue\QueueBackendFactory;
use PSFS\base\queue\QueueDispatcher;

$dispatcher = new QueueDispatcher(
    QueueBackendFactory::createPersistent(),
    new JobRegistry()
);

$dispatcher->dispatch('notifications', [
    'message' => 'Deploy completed',
]);
```

### 7.3 Redis queue (optional direct usage)

Use when Redis is available and you want decoupled request/processing flow.

```php
use PSFS\base\queue\FileJobQueue;
use PSFS\base\queue\RedisJobQueue;

$redisQueue = new RedisJobQueue();
$queue = $redisQueue->isAvailable() ? $redisQueue : new FileJobQueue();

$ok = $queue->enqueue('notifications', [
    'type' => 'slack.error',
    'uid' => 'abc-123',
]);

if (!$ok) {
    // fallback or retry strategy
}
```

### 7.4 Recommended fallback strategy

```php
use PSFS\base\queue\FileJobQueue;
use PSFS\base\queue\RedisJobQueue;

$redisQueue = new RedisJobQueue();
$fileQueue = new FileJobQueue();
$payload = ['type' => 'audit.log', 'event' => 'user.login'];

if (!$redisQueue->enqueue('audit', $payload)) {
    $fileQueue->enqueue('audit', $payload);
}
```

## 8) Sensitive data redaction

Before sending payloads to external systems, redact secrets.

```php
use PSFS\base\types\helpers\SensitiveDataHelper;

$payload = [
    'username' => 'demo',
    'password' => 'super-secret',
    'Authorization' => 'Bearer x.y.z',
];

$safePayload = SensitiveDataHelper::redact($payload);
// password + authorization are masked with "***"
```

Masked key patterns include:

- `password`, `passwd`, `secret`, `token`
- `authorization`, `cookie`
- `api_key`, `apikey`, `access_token`, `refresh_token`

## 9) Connector timeouts

`CurlService` applies defaults even if not explicitly configured.

- connect timeout: `3` seconds
- request timeout: `10` seconds

Override with config when required:

```php
[
  'curl.connectTimeout' => 2,
  'curl.timeout' => 6,
]
```

## 10) Operational recommendations

- Use `QueueBackendFactory::createPersistent()` unless you have a strong reason to choose the backend manually.
- Start with `FileJobQueue` or the backend factory for local/dev predictability.
- Enable `RedisJobQueue` in non-local environments with monitoring.
- Keep payloads small and serializable (`array` only).
- Use idempotent handlers to avoid duplicate side effects on retries.
- Keep `code()` stable. Queue consumers depend on that identifier.
- Reserve `SyncJobQueue` for tests or in-process flows. It is not a worker transport.
- Always redact sensitive data before external delivery.

## 11) Validation commands (Docker only)

```bash
docker compose up -d
docker exec core-php-1 sh -lc 'cd /var/www && vendor/bin/phpunit tests/base/queue tests/base/helpers/SensitiveDataHelperTest.php tests/base/ServiceTest.php'
```
