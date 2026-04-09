<?php

namespace PSFS\tests\runtime\swoole;

use PHPUnit\Framework\TestCase;
use PSFS\base\SingletonRegistry;
use PSFS\runtime\swoole\SwooleRequestHandler;
use PSFS\runtime\swoole\SwooleRequestHydrator;

class SwooleRequestHydratorTest extends TestCase
{
    private string $sessionNameBackup = 'PHPSESSID';
    private string $sessionIdBackup = '';
    private array $serverBackup = [];
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        $this->sessionNameBackup = session_name();
        $this->sessionIdBackup = session_id();
        $this->serverBackup = $_SERVER ?? [];
        $this->sessionBackup = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        if ($this->sessionNameBackup !== '') {
            @session_name($this->sessionNameBackup);
        }
        @session_id($this->sessionIdBackup);
        $_SERVER = $this->serverBackup;
        $_SESSION = $this->sessionBackup;
    }

    public function testHydratePopulatesServerAndPayloadData(): void
    {
        $hydrator = new SwooleRequestHydrator();

        $request = new class {
            public array $server = [
                'request_uri' => '/api/demo?x=1',
                'request_method' => 'POST',
                'query_string' => 'x=1',
                'server_port' => 8080,
                'remote_addr' => '127.0.0.1',
            ];
            public array $header = [
                'host' => 'localhost:8080',
                'content-type' => 'application/json',
                'content-length' => '13',
                'authorization' => 'Basic ZGVtbzpzZWNyZXQ=',
            ];
            public array $get = ['x' => '1'];
            public array $post = ['y' => '2'];
            public array $cookie = ['k' => 'v'];
            public array $files = ['f' => ['name' => 'demo.txt']];
            public function rawContent(): string
            {
                return '{"ok":true}';
            }
        };

        $contextId = $hydrator->hydrate($request);

        $this->assertIsString($contextId);
        $this->assertNotSame('', $contextId);
        $this->assertSame('/api/demo?x=1', $_SERVER['REQUEST_URI'] ?? null);
        $this->assertSame('application/json', $_SERVER['CONTENT_TYPE'] ?? null);
        $this->assertSame('13', $_SERVER['CONTENT_LENGTH'] ?? null);
        $this->assertSame('demo', $_SERVER['PHP_AUTH_USER'] ?? null);
        $this->assertSame('secret', $_SERVER['PHP_AUTH_PW'] ?? null);
        $this->assertSame(['x' => '1'], $_GET);
        $this->assertSame(['y' => '2'], $_POST);
        $this->assertSame(['k' => 'v'], $_COOKIE);
        $this->assertSame(['f' => ['name' => 'demo.txt']], $_FILES);
        $this->assertSame('{"ok":true}', $_SERVER[SwooleRequestHandler::RAW_BODY_SERVER_KEY] ?? null);
        $this->assertSame('Basic', $_SERVER['AUTH_TYPE'] ?? null);
        $this->assertArrayHasKey(SingletonRegistry::CONTEXT_SESSION, $_SERVER);
    }

    public function testHydrateHandlesInvalidAuthAndMissingRawContentMethod(): void
    {
        $hydrator = new SwooleRequestHydrator();
        $_SERVER['PHP_AUTH_USER'] = 'u';
        $_SERVER['PHP_AUTH_PW'] = 'p';
        $_SERVER['AUTH_TYPE'] = 'Basic';

        $request = new class {
            public array $server = ['request_uri' => '/x', 'request_method' => 'GET'];
            public array $header = ['authorization' => 'Basic ###'];
            public array $get = [];
            public array $post = [];
            public array $cookie = [];
            public array $files = [];
        };

        $hydrator->hydrate($request);

        $this->assertSame('', (string)($_SERVER[SwooleRequestHandler::RAW_BODY_SERVER_KEY] ?? ''));
        $this->assertArrayNotHasKey('PHP_AUTH_USER', $_SERVER);
        $this->assertArrayNotHasKey('PHP_AUTH_PW', $_SERVER);
        $this->assertArrayNotHasKey('AUTH_TYPE', $_SERVER);
    }

    public function testHydrateSessionCookieSyncBranches(): void
    {
        $hydrator = new SwooleRequestHydrator();
        session_name('PHPSESSID');

        session_id('previous-id');
        $requestWithCookie = new class {
            public array $server = ['request_uri' => '/x', 'request_method' => 'GET'];
            public array $header = [];
            public array $get = [];
            public array $post = [];
            public array $cookie = ['PHPSESSID' => 'incoming-id'];
            public array $files = [];
        };
        $hydrator->hydrate($requestWithCookie);
        $this->assertSame('incoming-id', session_id());

        session_id('sticky-worker-session');
        $requestWithoutCookie = new class {
            public array $server = ['request_uri' => '/x', 'request_method' => 'GET'];
            public array $header = [];
            public array $get = [];
            public array $post = [];
            public array $cookie = [];
            public array $files = [];
        };
        $hydrator->hydrate($requestWithoutCookie);
        $this->assertSame('', session_id());
    }
}
