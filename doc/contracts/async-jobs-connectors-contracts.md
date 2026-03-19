# Async Jobs, Queue and Connector Contracts

> Status: verified (P0)  
> Runtime: Docker Compose + PHP 8.3

This document describes how to use the async queue and connector-hardening baseline added in PSFS core.

## 1) What is available now

### Queue contracts

- `PSFS\base\queue\JobQueueInterface`
- `PSFS\base\queue\SyncJobQueue`
- `PSFS\base\queue\RedisJobQueue`

Contract methods:

- `enqueue(string $queue, array $payload): bool`
- `dequeue(string $queue): ?array`
- `isAvailable(): bool`

### Connector hardening

- `PSFS\base\types\CurlService`
  - default connect timeout: `3s`
  - default request timeout: `10s`
- `PSFS\base\types\helpers\SensitiveDataHelper`
  - redacts sensitive payload fields before external logging/alerts
- `PSFS\base\types\helpers\SlackHelper`
  - sends redacted payload and extra info

## 2) Configuration

Optional keys in `config.json`:

```json
{
  "curl.connectTimeout": 3,
  "curl.timeout": 10,
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

## 3) Usage examples

### 3.1 Sync queue (default-safe mode)

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

### 3.2 Redis queue (optional)

Use when Redis is available and you want decoupled request/processing flow.

```php
use PSFS\base\queue\RedisJobQueue;
use PSFS\base\queue\SyncJobQueue;

$redisQueue = new RedisJobQueue();
$queue = $redisQueue->isAvailable() ? $redisQueue : new SyncJobQueue();

$ok = $queue->enqueue('notifications', [
    'type' => 'slack.error',
    'uid' => 'abc-123',
]);

if (!$ok) {
    // fallback/retry strategy
}
```

### 3.3 Recommended fallback strategy

```php
use PSFS\base\queue\RedisJobQueue;
use PSFS\base\queue\SyncJobQueue;

$redisQueue = new RedisJobQueue();
$syncQueue = new SyncJobQueue();
$payload = ['type' => 'audit.log', 'event' => 'user.login'];

if (!$redisQueue->enqueue('audit', $payload)) {
    $syncQueue->enqueue('audit', $payload);
}
```

## 4) Sensitive data redaction

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

## 5) Connector timeouts

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

## 6) Operational recommendations

- Start with `SyncJobQueue` for local/dev predictability.
- Enable `RedisJobQueue` in non-local environments with monitoring.
- Keep payloads small and serializable (`array` only).
- Use idempotent handlers to avoid duplicate side effects on retries.
- Always redact sensitive data before external delivery.

## 7) Validation commands (Docker only)

```bash
docker compose up -d
docker exec core-php-1 sh -lc 'cd /var/www && vendor/bin/phpunit tests/base/queue tests/base/helpers/SensitiveDataHelperTest.php tests/base/ServiceTest.php'
```
