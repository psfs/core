<?php

// Test runtime polyfills for optional extensions not guaranteed in every CI/local setup.
if (defined('PSFS_UNIT_TESTING_EXECUTION') && PSFS_UNIT_TESTING_EXECUTION) {
    require_once __DIR__ . '/testing/redis.php';
}
