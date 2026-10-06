<?php

namespace PSFS\base\types\helpers\metadata;

use PSFS\base\Logger;
use ReflectionClass;

/**
 * Stale-while-revalidate queues and per-entry regeneration locks.
 *
 * Requires MetadataEngine bundle, cache, and backend traits plus queue and shutdown state.
 */
trait MetadataEngineRegenerationTrait
{
    protected function queueBackgroundRegeneration(string $cacheKey, string $className): void
    {
        if (!$this->swrEnabled()) {
            return;
        }
        if (!$this->acquireLock($cacheKey)) {
            self::$stats['metadata.lock_contention']++;
            return;
        }
        self::$regenQueue[] = [$cacheKey, $className];
        if ($this->shutdownRegistered) {
            return;
        }
        $this->shutdownRegistered = true;
        register_shutdown_function(function (): void {
            $this->drainBackgroundRegeneration();
        });
    }

    protected function drainBackgroundRegeneration(): void
    {
        while ($pair = array_shift(self::$regenQueue)) {
            [$key, $className] = $pair;
            try {
                if (!class_exists($className)) {
                    continue;
                }
                $reflection = new ReflectionClass($className);
                $signature = $this->sourceSignature($reflection);
                $payload = $this->buildClassBundle($reflection);
                $entry = $this->buildEntryEnvelope($payload, $signature, time());
                $this->writeEntry($key, $entry);
                self::$stats['metadata.regen']++;
            } catch (\Throwable $exception) {
                Logger::log('[MetadataEngine][SWR] ' . $exception->getMessage(), LOG_WARNING);
            } finally {
                $this->releaseLock($key);
            }
        }
    }

    protected function acquireLock(string $cacheKey): bool
    {
        $redis = $this->redisClient();
        if (null === $redis) {
            return true;
        }
        $lockKey = self::LOCK_PREFIX . $cacheKey;
        try {
            $ok = $redis->set($lockKey, (string)getmypid(), ['nx', 'ex' => $this->regenLockTtl()]);
            return $ok === true || $ok === 'OK';
        } catch (\RedisException $exception) {
            Logger::log('[MetadataEngine][Lock] ' . $exception->getMessage(), LOG_WARNING);
            return true;
        }
    }

    protected function releaseLock(string $cacheKey): void
    {
        $redis = $this->redisClient();
        if (null === $redis) {
            return;
        }
        try {
            $redis->del(self::LOCK_PREFIX . $cacheKey);
        } catch (\RedisException $exception) {
            Logger::log('[MetadataEngine][Lock] ' . $exception->getMessage(), LOG_WARNING);
        }
    }

}
