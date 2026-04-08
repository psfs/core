<?php

namespace PSFS\tests\runtime\swoole;

require_once __DIR__ . '/../../../src/runtime/swoole/SwooleCommandService.php';

use PHPUnit\Framework\TestCase;
use PSFS\base\runtime\RuntimeMode;
use PSFS\runtime\swoole\NativeSwooleHttpServerAdapter;
use PSFS\runtime\swoole\NativeSwoolePidStore;
use PSFS\runtime\swoole\NativeSwooleRuntimeInspector;
use PSFS\runtime\swoole\NativeSwooleSignalSender;

class SwooleCommandNativeAdaptersTest extends TestCase
{
    private string $runtimeBackup = '';

    protected function setUp(): void
    {
        $this->runtimeBackup = RuntimeMode::getCurrentMode();
    }

    protected function tearDown(): void
    {
        if ($this->runtimeBackup !== '') {
            putenv(RuntimeMode::ENV_KEY . '=' . $this->runtimeBackup);
            $_SERVER[RuntimeMode::ENV_KEY] = $this->runtimeBackup;
        } else {
            putenv(RuntimeMode::ENV_KEY);
            unset($_SERVER[RuntimeMode::ENV_KEY]);
        }
    }

    public function testNativeRuntimeInspectorCanEnableSwooleMode(): void
    {
        $inspector = new NativeSwooleRuntimeInspector();
        $inspector->enableSwooleMode();
        $this->assertSame('swoole', $inspector->getRuntimeMode());
        $this->assertIsBool($inspector->hasSwooleSupport());
    }

    public function testNativePidStoreWrapsPidManagerMethods(): void
    {
        $pidStore = new NativeSwoolePidStore();
        $pidFile = '/tmp/psfs-native-pid-' . uniqid('', true) . '.pid';
        @unlink($pidFile);
        $pidStore->writePid($pidFile, getmypid());
        $this->assertSame(getmypid(), $pidStore->readRunningPid($pidFile));
        $pidStore->removePid($pidFile);
        $this->assertFalse(is_file($pidFile));
    }

    public function testNativeSignalSenderRejectsInvalidPid(): void
    {
        $sender = new NativeSwooleSignalSender();
        $this->assertFalse($sender->send(-1, 15));
        $this->assertFalse($sender->send(0, 10));
    }

    public function testNativeSignalSenderCanProbeCurrentPidWithSignalZero(): void
    {
        if (!function_exists('posix_kill')) {
            $this->markTestSkipped('posix_kill not available in this runtime.');
        }
        $sender = new NativeSwooleSignalSender();
        $this->assertTrue($sender->send(getmypid(), 0));
    }

    public function testNativeHttpServerAdapterDelegatesMethods(): void
    {
        $server = new class {
            public array $settings = [];
            public array $events = [];
            public bool $started = false;

            public function set(array $settings): void
            {
                $this->settings = $settings;
            }

            public function on(string $event, callable $handler): void
            {
                $this->events[$event] = $handler;
            }

            public function start(): void
            {
                $this->started = true;
            }
        };

        $adapter = new NativeSwooleHttpServerAdapter($server);
        $adapter->set(['worker_num' => 2]);
        $adapter->on('request', static function (): void {
        });
        $adapter->start();

        $this->assertSame(['worker_num' => 2], $server->settings);
        $this->assertArrayHasKey('request', $server->events);
        $this->assertTrue($server->started);
    }
}
