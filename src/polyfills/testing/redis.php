<?php

if (!class_exists('RedisException')) {
    class RedisException extends RuntimeException
    {
    }
}

if (!class_exists('Redis')) {
    class Redis
    {
        public function connect(string $host, int $port = 6379, float $timeout = 0): bool
        {
            return false;
        }

        public function get(string $key)
        {
            return false;
        }

        public function setex(string $key, int $ttl, string $value): bool
        {
            return true;
        }

        public function set(string $key, string $value): bool
        {
            return true;
        }

        public function del(string ...$keys): int
        {
            return count($keys);
        }
    }
}
