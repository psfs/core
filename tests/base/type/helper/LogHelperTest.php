<?php

namespace PSFS\tests\base\type\helper;

use Monolog\Level;
use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\types\helpers\LogHelper;

class LogHelperTest extends TestCase
{
    private const ENV_KEYS = [
        'PSFS_LOG_LEVEL',
        'PSFS_LOG_LEVEL_CACHE',
        'PSFS_LOG_OUTPUT',
        'PSFS_LOG_DEPRECATION_MODE',
    ];

    private array $serverBackup = [];
    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER ?? [];
        $this->configBackup = $this->configProperty()->getValue(Config::getInstance());
        foreach (self::ENV_KEYS as $name) {
            putenv($name);
        }
        LogHelper::resetSeenDeprecations();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $this->configProperty()->setValue(Config::getInstance(), $this->configBackup);
        foreach (self::ENV_KEYS as $name) {
            putenv($name);
        }
        LogHelper::resetSeenDeprecations();
    }

    private function configProperty(): \ReflectionProperty
    {
        return new \ReflectionProperty(Config::class, 'config');
    }

    private function setConfig(array $values): void
    {
        $this->configProperty()->setValue(Config::getInstance(), array_merge($this->configBackup, $values));
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

    public function testParseLevelIsCaseInsensitiveAndDefaultsToNotice(): void
    {
        self::assertSame(Level::Debug, LogHelper::parseLevel('debug'));
        self::assertSame(Level::Warning, LogHelper::parseLevel(' WARNING '));
        self::assertSame(Level::Critical, LogHelper::parseLevel('CRITICAL'));
        self::assertSame(Level::Notice, LogHelper::parseLevel('notice'));
        self::assertSame(Level::Warning, LogHelper::parseLevel('nonsense'));
    }

    public function testDefaultLevelIsWarningWithBuiltInChannelDefaults(): void
    {
        $config = $this->configBackup;
        unset(
            $config['log.level'],
            $config['log.level.cache'],
            $config['log.level.php'],
            $config['log.level.deprecation'],
            $config['log.level.security'],
            $config['log.deprecation.mode']
        );
        $this->configProperty()->setValue(Config::getInstance(), $config);
        LogHelper::resetSeenDeprecations();

        self::assertSame('WARNING', LogHelper::resolveChannelLevel());
        self::assertSame('WARNING', LogHelper::resolveChannelLevel('cache'));
        self::assertSame('ERROR', LogHelper::resolveChannelLevel('deprecation'));
        self::assertSame('WARNING', LogHelper::resolveChannelLevel('security'));
        self::assertFalse(LogHelper::isEnabled(Level::Notice));
        self::assertTrue(LogHelper::isEnabled(Level::Warning));
        self::assertTrue(LogHelper::isEnabled(Level::Warning, 'php'));
        self::assertFalse(LogHelper::isEnabled(Level::Notice, 'deprecation'));
        self::assertFalse(LogHelper::isEnabled(Level::Notice, 'php'));
    }

    public function testSecurityChannelKeepsWarningsWhenGlobalLevelIsRaised(): void
    {
        $this->setConfig(['log.level' => 'CRITICAL']);
        unset($this->configBackup['log.level.security']);

        self::assertTrue(LogHelper::isEnabled(Level::Warning, 'security'));
        self::assertFalse(LogHelper::isEnabled(Level::Warning, 'cache'));
        self::assertFalse(LogHelper::isEnabled(Level::Warning));

        $this->setConfig(['log.level' => 'CRITICAL', 'log.level.security' => 'ERROR']);
        self::assertFalse(LogHelper::isEnabled(Level::Warning, 'security'));
    }

    public function testDeprecationModeIsOnceByDefault(): void
    {
        $config = $this->configBackup;
        unset($config['log.deprecation.mode']);
        $this->configProperty()->setValue(Config::getInstance(), array_merge($config, ['log.level.deprecation' => 'EMERGENCY']));
        LogHelper::resetSeenDeprecations();

        self::assertSame(Level::Notice, LogHelper::reportPhpError(E_DEPRECATED, 'dflt', 'a.php', 1));
        self::assertNull(LogHelper::reportPhpError(E_DEPRECATED, 'dflt', 'a.php', 1));
    }

    public function testChannelLevelFallsBackToGlobalLevel(): void
    {
        $this->setConfig(['log.level' => 'WARNING', 'log.level.cache' => 'debug']);

        self::assertSame('WARNING', LogHelper::resolveChannelLevel());
        self::assertSame('WARNING', LogHelper::resolveChannelLevel('curl'));
        self::assertSame('DEBUG', LogHelper::resolveChannelLevel('cache'));
        self::assertTrue(LogHelper::isEnabled(Level::Debug, 'cache'));
        self::assertFalse(LogHelper::isEnabled(Level::Debug, 'curl'));
        self::assertTrue(LogHelper::isEnabled(Level::Error, 'curl'));
    }

    public function testPhpErrorsAreMappedToChannelAndLevel(): void
    {
        self::assertSame(['deprecation', Level::Notice], LogHelper::mapPhpError(E_DEPRECATED));
        self::assertSame(['deprecation', Level::Notice], LogHelper::mapPhpError(E_USER_DEPRECATED));
        self::assertSame(['php', Level::Notice], LogHelper::mapPhpError(E_NOTICE));
        self::assertSame(['php', Level::Warning], LogHelper::mapPhpError(E_WARNING));
        self::assertSame(['php', Level::Warning], LogHelper::mapPhpError(E_USER_WARNING));
        self::assertSame(['php', Level::Critical], LogHelper::mapPhpError(E_ERROR));
        self::assertSame(['php', Level::Critical], LogHelper::mapPhpError(E_USER_ERROR));
    }

    public function testDeprecationModeControlsDuplicates(): void
    {
        $this->setConfig(['log.level.deprecation' => 'EMERGENCY', 'log.deprecation.mode' => 'once']);
        self::assertSame(Level::Notice, LogHelper::reportPhpError(E_DEPRECATED, 'old api', 'a.php', 10));
        self::assertNull(LogHelper::reportPhpError(E_DEPRECATED, 'old api', 'a.php', 10));
        self::assertSame(Level::Notice, LogHelper::reportPhpError(E_DEPRECATED, 'old api', 'a.php', 11));

        $this->setConfig(['log.level.deprecation' => 'EMERGENCY', 'log.deprecation.mode' => 'log']);
        self::assertSame(Level::Notice, LogHelper::reportPhpError(E_DEPRECATED, 'old api', 'a.php', 10));
        self::assertSame(Level::Notice, LogHelper::reportPhpError(E_DEPRECATED, 'old api', 'a.php', 10));

        $this->setConfig(['log.level.deprecation' => 'EMERGENCY', 'log.deprecation.mode' => 'off']);
        self::assertNull(LogHelper::reportPhpError(E_DEPRECATED, 'other', 'b.php', 1));
    }

    public function testNonDeprecationErrorsAreNeverDeduplicated(): void
    {
        $this->setConfig(['log.level.php' => 'EMERGENCY', 'log.deprecation.mode' => 'once']);
        self::assertSame(Level::Warning, LogHelper::reportPhpError(E_WARNING, 'w', 'a.php', 1));
        self::assertSame(Level::Warning, LogHelper::reportPhpError(E_WARNING, 'w', 'a.php', 1));
    }

    public function testSettingPrefersTheEnvironmentOverTheConfiguration(): void
    {
        $this->setConfig(['log.output' => 'file']);
        self::assertSame('file', LogHelper::setting('log.output', 'stdout'));

        putenv('PSFS_LOG_OUTPUT=stderr');
        self::assertSame('stderr', LogHelper::setting('log.output', 'stdout'));
    }

    public function testSettingTreatsEmptyEnvironmentValuesAsUnset(): void
    {
        $this->setConfig(['log.output' => 'file']);
        putenv('PSFS_LOG_OUTPUT=');
        self::assertSame('file', LogHelper::setting('log.output', 'stdout'));

        $config = $this->configBackup;
        unset($config['log.output']);
        $this->configProperty()->setValue(Config::getInstance(), $config);
        self::assertSame('stdout', LogHelper::setting('log.output', 'stdout'));
        self::assertNull(LogHelper::setting('log.output'));
    }

    public function testEnvironmentOverridesGlobalAndChannelLevels(): void
    {
        $this->setConfig(['log.level' => 'ERROR']);
        putenv('PSFS_LOG_LEVEL=debug');
        putenv('PSFS_LOG_LEVEL_CACHE=critical');

        self::assertSame('DEBUG', LogHelper::resolveChannelLevel());
        self::assertSame('DEBUG', LogHelper::resolveChannelLevel('curl'));
        self::assertSame('CRITICAL', LogHelper::resolveChannelLevel('cache'));
        self::assertTrue(LogHelper::isEnabled(Level::Debug, 'curl'));
        self::assertFalse(LogHelper::isEnabled(Level::Error, 'cache'));
    }

    public function testEnvironmentControlsTheDeprecationMode(): void
    {
        $this->setConfig(['log.level.deprecation' => 'EMERGENCY', 'log.deprecation.mode' => 'once']);
        putenv('PSFS_LOG_DEPRECATION_MODE=off');

        self::assertNull(LogHelper::reportPhpError(E_DEPRECATED, 'env off', 'a.php', 1));

        putenv('PSFS_LOG_DEPRECATION_MODE=log');
        self::assertSame(Level::Notice, LogHelper::reportPhpError(E_DEPRECATED, 'env log', 'a.php', 1));
        self::assertSame(Level::Notice, LogHelper::reportPhpError(E_DEPRECATED, 'env log', 'a.php', 1));
    }
}
