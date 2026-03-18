<?php

if (!class_exists('RedisException')) {
    class RedisException extends RuntimeException
    {
    }
}

if (!class_exists('Redis')) {
    class Redis
    {
        public function connect(/** @scrutinizer ignore-unused */ string $host, /** @scrutinizer ignore-unused */ int $port = 6379, /** @scrutinizer ignore-unused */ float $timeout = 0): bool
        {
            return false;
        }

        public function get(/** @scrutinizer ignore-unused */ string $key)
        {
            return false;
        }

        public function setex(/** @scrutinizer ignore-unused */ string $key, /** @scrutinizer ignore-unused */ int $ttl, /** @scrutinizer ignore-unused */ string $value): bool
        {
            return true;
        }

        public function set(/** @scrutinizer ignore-unused */ string $key, /** @scrutinizer ignore-unused */ string $value): bool
        {
            return true;
        }

        public function del(string ...$keys): int
        {
            return count($keys);
        }
    }
}
