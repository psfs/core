<?php

// Runtime polyfills for optional extensions not guaranteed in every CI/local setup.
// If ext-redis is not available, provide minimal Redis/RedisException classes so
// type-hints and test doubles keep working while repositories still fallback to file.
if (!class_exists('Redis') || !class_exists('RedisException')) {
    require_once __DIR__ . '/testing/redis.php';
}
