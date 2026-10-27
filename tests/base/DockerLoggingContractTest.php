<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;

/**
 * Keeps the container logging setup (stdout/stderr + rotated driver) from regressing
 */
class DockerLoggingContractTest extends TestCase
{
    private static function read(string $file): string
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $file);
        self::assertIsString($content, $file . ' is not readable');
        return $content;
    }

    public function testNativePhpErrorsAreSentToStderr(): void
    {
        $this->assertMatchesRegularExpression('/^log_errors\s*=\s*On$/m', self::read('docker/php.ini'));
        $this->assertMatchesRegularExpression('#^error_log\s*=\s*/dev/stderr$#m', self::read('docker/php.ini'));
    }

    public function testComposeRotatesEveryServiceLogAndDefaultsPsfsToStdoutJson(): void
    {
        $compose = self::read('docker-compose.yml');

        $this->assertMatchesRegularExpression('/x-logging: &default-logging\s+driver: "json-file"\s+options:\s+max-size: "10m"\s+max-file: "3"/', $compose);
        // php, php-swoole, redis and db
        $this->assertSame(4, substr_count($compose, 'logging: *default-logging'));
        $this->assertSame(2, substr_count($compose, '<<: *psfs-logging-env'));
        $this->assertStringContainsString('PSFS_LOG_OUTPUT: "${PSFS_LOG_OUTPUT:-stdout}"', $compose);
        $this->assertStringContainsString('PSFS_LOG_FORMAT: "${PSFS_LOG_FORMAT:-json}"', $compose);
    }

    public function testPhpunitIsolatesTestsFromTheContainerLogEnvironment(): void
    {
        foreach (['phpunit.xml.dist', 'phpunit.api.xml.dist'] as $file) {
            $config = self::read($file);
            foreach (['PSFS_LOG_OUTPUT', 'PSFS_LOG_FORMAT', 'PSFS_LOG_LEVEL', 'PSFS_LOG_DEPRECATION_MODE'] as $name) {
                $this->assertStringContainsString('<env name="' . $name . '" value="" force="true"/>', $config, $file);
            }
        }
    }
}
