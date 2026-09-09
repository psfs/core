<?php

namespace PSFS\tests\base\type\helper;

use Monolog\Level;
use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\LogHelper;

class LogHelperTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER ?? [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    public function testCheckLogLevelCoversAllNamedAndDefaultLevels(): void
    {
        foreach (['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'EMERGENCY', 'ALERT', 'CRITICAL', 'UNKNOWN'] as $level) {
            self::assertIsBool(LogHelper::checkLogLevel($level, Level::Emergency));
        }
    }

    public function testLoggerNamesAndMinimalContextAreNormalized(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_X_PSFS_UID'] = 'user-42';

        self::assertSame('PSFS.controller.Admin', LogHelper::cleanLoggerName(' PSFS\\controller\\Admin '));
        self::assertSame([
            'existing' => true,
            'uri' => '/admin',
            'method' => 'GET',
            'uid' => 'user-42',
        ], LogHelper::addMinimalContext(['existing' => true]));
    }

    public function testCalculateLogLevelMapsSyslogValuesAndUnknownValues(): void
    {
        self::assertSame(Level::Debug, LogHelper::calculateLogLevel(LOG_DEBUG));
        self::assertSame(Level::Warning, LogHelper::calculateLogLevel(LOG_WARNING));
        self::assertSame(Level::Critical, LogHelper::calculateLogLevel(LOG_CRIT));
        self::assertSame(Level::Error, LogHelper::calculateLogLevel(LOG_ERR));
        self::assertSame(Level::Info, LogHelper::calculateLogLevel(LOG_INFO));
        self::assertSame(Level::Emergency, LogHelper::calculateLogLevel(LOG_EMERG));
        self::assertSame(Level::Alert, LogHelper::calculateLogLevel(LOG_ALERT));
        self::assertSame(Level::Notice, LogHelper::calculateLogLevel(123456));
    }
}
