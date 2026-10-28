<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\types\helpers\CompatHelper;

class CompatHelperTest extends TestCase
{
    private const ENV = ['PSFS_COMPAT_TEST_FLAG', 'PSFS_COMPAT_TEST_NUMBER'];

    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = (new \ReflectionProperty(Config::class, 'config'))->getValue(Config::getInstance());
        foreach (self::ENV as $name) {
            putenv($name);
        }
        CompatHelper::reset();
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Config::class, 'config'))->setValue(Config::getInstance(), $this->configBackup);
        foreach (self::ENV as $name) {
            putenv($name);
        }
        Logger::dropInstance();
        CompatHelper::reset();
    }

    private function setConfig(array $values): void
    {
        (new \ReflectionProperty(Config::class, 'config'))->setValue(Config::getInstance(), array_merge($this->configBackup, $values));
    }

    public function testFlagReadsConfigurationBooleansAndStrings(): void
    {
        $this->setConfig(['compat.test.flag' => true]);
        self::assertTrue(CompatHelper::flag('compat.test.flag'));

        $this->setConfig(['compat.test.flag' => false]);
        self::assertFalse(CompatHelper::flag('compat.test.flag', true), 'false is a value, not "unset"');

        foreach (['1', 'true', 'yes', 'on'] as $value) {
            $this->setConfig(['compat.test.flag' => $value]);
            self::assertTrue(CompatHelper::flag('compat.test.flag'), $value);
        }
        foreach (['0', 'false', 'no', 'off'] as $value) {
            $this->setConfig(['compat.test.flag' => $value]);
            self::assertFalse(CompatHelper::flag('compat.test.flag', true), $value);
        }
    }

    public function testFlagFallsBackToTheDefaultWhenUnsetOrInvalid(): void
    {
        $config = $this->configBackup;
        unset($config['compat.test.flag']);
        (new \ReflectionProperty(Config::class, 'config'))->setValue(Config::getInstance(), $config);
        self::assertFalse(CompatHelper::flag('compat.test.flag'));
        self::assertTrue(CompatHelper::flag('compat.test.flag', true));

        $this->setConfig(['compat.test.flag' => 'maybe']);
        self::assertTrue(CompatHelper::flag('compat.test.flag', true));
    }

    public function testEnvironmentOverridesTheConfiguration(): void
    {
        $this->setConfig(['compat.test.flag' => false, 'compat.test.number' => 5]);
        putenv('PSFS_COMPAT_TEST_FLAG=true');
        putenv('PSFS_COMPAT_TEST_NUMBER=42');

        self::assertTrue(CompatHelper::flag('compat.test.flag'));
        self::assertSame(42, CompatHelper::int('compat.test.number', 1));
    }

    public function testIntFallsBackWhenMissingOrNotNumeric(): void
    {
        $this->setConfig(['compat.test.number' => 'abc']);
        self::assertSame(7, CompatHelper::int('compat.test.number', 7));
        $this->setConfig(['compat.test.number' => '12']);
        self::assertSame(12, CompatHelper::int('compat.test.number', 7));
    }

    public function testWarnOnceLogsEachIdOnceInTheSecurityChannel(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psfs-compat-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $this->setConfig(['log.output' => 'file', 'default.log.path' => $dir]);
        Logger::dropInstance();

        try {
            CompatHelper::warnOnce('first', 'first legacy behaviour');
            CompatHelper::warnOnce('first', 'first legacy behaviour');
            CompatHelper::warnOnce('second', 'second legacy behaviour');
            Logger::dropInstance();

            $content = implode('', array_map('file_get_contents', glob($dir . '/*/*/*/*.log') ?: []));
            self::assertSame(1, substr_count($content, '[Compat] first legacy behaviour'));
            self::assertSame(1, substr_count($content, '[Compat] second legacy behaviour'));
            self::assertStringContainsString('"channel":"security"', $content);
            self::assertStringContainsString(':WARNING]', $content);
        } finally {
            Logger::dropInstance();
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }
}
