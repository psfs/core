<?php

namespace PSFS\tests\base\type\trait;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\types\helpers\LogHelper;
use PSFS\base\types\traits\SystemTrait;

class SystemTraitTest extends TestCase
{
    private Config $config;
    private array $configBackup = [];
    private bool $shutdownFlagBackup = false;
    private string $logDir;
    private object $host;
    private bool $handlerBound = false;
    private int $errorReportingBackup = E_ALL;

    protected function setUp(): void
    {
        // PHPUnit narrows error_reporting during tests; the handlers under test depend on it
        $this->errorReportingBackup = error_reporting(E_ALL);
        $this->config = Config::getInstance();
        $this->configBackup = $this->configProperty()->getValue($this->config);
        $this->logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psfs-system-trait-' . uniqid('', true);
        mkdir($this->logDir, 0777, true);
        $this->useConfig([]);

        // The shutdown function is registered once per process: keep the test hosts from registering it
        $flag = new \ReflectionProperty(self::hostClass(), 'shutdownHandlerBound');
        $this->shutdownFlagBackup = (bool)$flag->getValue();
        $flag->setValue(null, true);

        $this->host = new (self::hostClass())();
        $this->handlerBound = false;
        LogHelper::resetSeenDeprecations();
        $this->resetErrorState();
    }

    protected function tearDown(): void
    {
        if ($this->handlerBound) {
            restore_error_handler();
        }
        $this->resetErrorState();
        error_reporting($this->errorReportingBackup);
        (new \ReflectionProperty(self::hostClass(), 'shutdownHandlerBound'))->setValue(null, $this->shutdownFlagBackup);
        Logger::dropInstance();
        $this->configProperty()->setValue($this->config, $this->configBackup);
        exec('rm -rf ' . escapeshellarg($this->logDir));
        LogHelper::resetSeenDeprecations();
    }

    private static function hostClass(): string
    {
        return SystemTraitHost::class;
    }

    private function bindHandler(): void
    {
        $this->host->bind();
        $this->handlerBound = true;
    }

    private function configProperty(): \ReflectionProperty
    {
        return new \ReflectionProperty(Config::class, 'config');
    }

    private function useConfig(array $values, array $remove = []): void
    {
        $config = array_merge($this->configBackup, [
            'log.output' => 'file',
            'default.log.path' => $this->logDir,
        ], $values);
        foreach (['log.level', 'log.level.php', 'log.level.deprecation', 'log.deprecation.mode', 'log.slack.hook'] as $key) {
            if (!array_key_exists($key, $values)) {
                unset($config[$key]);
            }
        }
        foreach ($remove as $key) {
            unset($config[$key]);
        }
        $this->configProperty()->setValue($this->config, $config);
        Logger::dropInstance();
    }

    /**
     * @return string[]
     */
    private function logFiles(): array
    {
        return glob($this->logDir . '/*/*/*/*.log') ?: [];
    }

    private function logContent(): string
    {
        Logger::dropInstance();
        return implode('', array_map('file_get_contents', $this->logFiles()));
    }

    /**
     * Cleans every error source read by handleShutdown
     */
    private function resetErrorState(): void
    {
        error_clear_last();
        json_decode('1');
        preg_match('/a/', 'a');
        date_create('now');
    }

    /**
     * Leaves a PHP warning in error_get_last() without reaching PHPUnit's handler
     */
    private function leaveLastError(int $type, string $message): void
    {
        set_error_handler(static fn(): bool => false);
        try {
            @trigger_error($message, $type);
        } finally {
            restore_error_handler();
        }
    }

    public function testHandleErrorIgnoresWhatErrorReportingExcludesAndLogsTheRest(): void
    {
        $subject = $this->host;
        $previous = error_reporting(E_ALL & ~E_USER_NOTICE);
        try {
            $this->assertTrue($subject->handleError(E_USER_NOTICE, 'silenced notice', 'file.php', 3));
            $this->assertTrue($subject->handleError(E_USER_WARNING, 'reported warning', 'file.php', 4));
        } finally {
            error_reporting($previous);
        }

        $content = $this->logContent();
        self::assertStringNotContainsString('silenced notice', $content);
        self::assertStringContainsString('reported warning', $content);
    }

    public function testBindingTheHandlerAgainDoesNotStackIt(): void
    {
        SystemTraitSentinel::$calls = 0;
        set_error_handler(SystemTraitSentinel::handle(...));
        try {
            $this->bindHandler();
            $this->bindHandler();
            $this->bindHandler();
            restore_error_handler();
            $this->handlerBound = false;

            trigger_error('after one restore', E_USER_WARNING);
        } finally {
            restore_error_handler();
        }

        $this->assertSame(1, SystemTraitSentinel::$calls, 'one restore undoes every binding and the earlier handler is back');
    }

    public function testErrorHandlerLogsWarningsInThePhpChannel(): void
    {
        $this->bindHandler();

        trigger_error('handler warning', E_USER_WARNING);

        $content = $this->logContent();
        self::assertStringContainsString('handler warning', $content);
        self::assertStringContainsString(':WARNING]', $content);
        self::assertStringContainsString('"channel":"php"', $content);
        self::assertStringContainsString('"errno":' . E_USER_WARNING, $content);
    }

    public function testErrorHandlerSkipsNoticesUnderTheDefaultLevel(): void
    {
        $this->bindHandler();

        trigger_error('handler notice', E_USER_NOTICE);

        self::assertSame([], $this->logFiles());
    }

    public function testErrorHandlerLogsNoticesWhenTheChannelIsLowered(): void
    {
        $this->useConfig(['log.level.php' => 'NOTICE']);
        $this->bindHandler();

        trigger_error('lowered notice', E_USER_NOTICE);

        self::assertStringContainsString('lowered notice', $this->logContent());
    }

    public function testErrorHandlerKeepsDeprecationsSilencedByDefault(): void
    {
        $this->bindHandler();

        trigger_error('silenced deprecation', E_USER_DEPRECATED);

        self::assertSame([], $this->logFiles());
    }

    public function testErrorHandlerLogsEachDeprecationOnceByDefault(): void
    {
        $this->useConfig(['log.level.deprecation' => 'NOTICE']);
        $this->bindHandler();

        for ($i = 0; $i < 3; $i++) {
            trigger_error('repeated deprecation', E_USER_DEPRECATED);
        }

        $content = $this->logContent();
        self::assertSame(1, substr_count($content, 'repeated deprecation'));
        self::assertStringContainsString('"channel":"deprecation"', $content);
    }

    public function testErrorHandlerLogsEveryDeprecationInLogMode(): void
    {
        $this->useConfig(['log.level.deprecation' => 'NOTICE', 'log.deprecation.mode' => 'log']);
        $this->bindHandler();

        for ($i = 0; $i < 3; $i++) {
            trigger_error('noisy deprecation', E_USER_DEPRECATED);
        }

        self::assertSame(3, substr_count($this->logContent(), 'noisy deprecation'));
    }

    public function testErrorHandlerRespectsTheErrorSuppressionOperator(): void
    {
        $this->useConfig(['log.level' => 'DEBUG']);
        $this->bindHandler();

        @trigger_error('suppressed warning', E_USER_WARNING);

        self::assertSame([], $this->logFiles());
    }

    public function testErrorHandlerSwallowsTheErrorSoPhpDoesNotReportItTwice(): void
    {
        $this->bindHandler();
        $previous = error_get_last();

        trigger_error('swallowed warning', E_USER_WARNING);

        self::assertSame($previous, error_get_last());
    }

    public function testShutdownDoesNothingWhenThereAreNoErrors(): void
    {
        $this->useConfig(['log.level' => 'DEBUG']);

        $this->host->handleShutdown();

        self::assertSame([], $this->logFiles());
    }

    public function testShutdownLogsTheLastPhpWarning(): void
    {
        $this->leaveLastError(E_USER_WARNING, 'shutdown warning');

        $this->host->handleShutdown();

        $content = $this->logContent();
        self::assertStringContainsString('shutdown warning', $content);
        self::assertStringContainsString(':WARNING]', $content);
        self::assertStringContainsString('"channel":"php"', $content);
    }

    public function testShutdownRoutesDeprecationsToTheirOwnChannel(): void
    {
        $this->useConfig(['log.level.deprecation' => 'NOTICE']);
        $this->leaveLastError(E_USER_DEPRECATED, 'shutdown deprecation');

        $this->host->handleShutdown();

        self::assertStringContainsString('"channel":"deprecation"', $this->logContent());
    }

    public function testShutdownIgnoresErrorsOutsideTheErrorReportingMask(): void
    {
        $this->useConfig(['log.level' => 'DEBUG']);
        $this->leaveLastError(E_USER_WARNING, 'masked warning');
        $previousLevel = error_reporting(E_ALL & ~E_USER_WARNING);

        try {
            $this->host->handleShutdown();
        } finally {
            error_reporting($previousLevel);
        }

        self::assertSame([], $this->logFiles());
    }

    public function testShutdownReportsJsonErrorsWhenPhpHasNoLastError(): void
    {
        json_decode('{bad json');
        $expected = json_last_error_msg();
        self::assertNotSame(JSON_ERROR_NONE, json_last_error());

        $this->host->handleShutdown();

        self::assertStringContainsString($expected, $this->logContent());
    }

    public function testShutdownReportsRegexErrorsWhenPhpHasNoLastError(): void
    {
        $limit = ini_set('pcre.backtrack_limit', '1');
        try {
            preg_match('/(?:\D+|<\d+>)*[!?]/', 'foobar foobar foobar');
        } finally {
            ini_set('pcre.backtrack_limit', (string)$limit);
        }
        self::assertNotSame(PREG_NO_ERROR, preg_last_error());
        $expected = preg_last_error_msg();

        $this->host->handleShutdown();

        self::assertStringContainsString($expected, $this->logContent());
    }

    public function testShutdownReportsDateTimeErrorsWhenPhpHasNoLastError(): void
    {
        date_create('definitely not a date');

        $this->host->handleShutdown();

        self::assertStringContainsString('DateTime errors detected', $this->logContent());
    }
}

/**
 * Minimal class that uses SystemTrait, to exercise its handlers
 */
final class SystemTraitHost
{
    use SystemTrait;

    public function __construct()
    {
        $this->ts = microtime(true);
        $this->mem = 0;
    }

    public function bind(): void
    {
        $this->bindWarningAsExceptions();
    }
}

/**
 * Stands for the handler that was there before PSFS bound its own
 */
final class SystemTraitSentinel
{
    public static int $calls = 0;

    public static function handle(): bool
    {
        self::$calls++;
        return true;
    }
}
