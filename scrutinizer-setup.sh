#!/bin/sh
set -eu

export API_DB_HOST="${SERVICE_MYSQL_IP:-127.0.0.1}"
export DB_HOST="${SERVICE_MYSQL_IP:-127.0.0.1}"

php tests/bootstrap/prepare_api_mysql.php

php -r '
$file = "config/config.json";
$config = file_exists($file) ? json_decode((string) file_get_contents($file), true) : [];
if (!is_array($config)) {
    $config = [];
}
$config["psfs.redis"] = true;
$config["redis.host"] = "127.0.0.1";
$config["redis.port"] = 6379;
$config["redis.timeout"] = 1.5;
$config["cache.config.ttl"] = $config["cache.config.ttl"] ?? 60;
$config["cache.reflections.ttl"] = $config["cache.reflections.ttl"] ?? 300;
file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
'
