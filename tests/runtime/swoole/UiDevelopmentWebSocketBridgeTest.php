<?php

namespace PSFS\tests\runtime\swoole;

use PHPUnit\Framework\TestCase;
use PSFS\base\Security;
use PSFS\runtime\swoole\SwooleRequestHydrator;
use PSFS\runtime\swoole\SwooleRuntimeStateManager;
use PSFS\runtime\swoole\UiDevelopmentProxyResolver;
use PSFS\runtime\swoole\UiDevelopmentProxyTarget;
use PSFS\runtime\swoole\UiDevelopmentWebSocketBridge;

class UiDevelopmentWebSocketBridgeTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        Security::setTest(true);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        putenv('ADMIN_UI_DEV_UPSTREAM');
        Security::setTest(false);
        Security::dropInstance();
    }

    public function testResolvesTheAdminMountForTheHmrWebSocket(): void
    {
        $previousUri = $_SERVER['REQUEST_URI'] ?? null;
        $previousQuery = $_SERVER['QUERY_STRING'] ?? null;
        $previousUpstream = getenv('ADMIN_UI_DEV_UPSTREAM');
        $_SERVER['REQUEST_URI'] = '/admin-v2/?token=hmr';
        $_SERVER['QUERY_STRING'] = 'token=hmr';
        putenv('ADMIN_UI_DEV_UPSTREAM=http://ui:4200');

        try {
            $bridge = new UiDevelopmentWebSocketBridge(resolver: new UiDevelopmentProxyResolver());
            $method = new \ReflectionMethod($bridge, 'resolveTarget');
            $target = $method->invoke($bridge);

            self::assertNotNull($target);
            self::assertSame('/admin-v2', $target->mount);
            self::assertSame('http://ui:4200/admin-v2/?token=hmr', $target->upstreamUri('/admin-v2/?token=hmr'));
        } finally {
            if ($previousUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previousUri;
            }
            if ($previousQuery === null) {
                unset($_SERVER['QUERY_STRING']);
            } else {
                $_SERVER['QUERY_STRING'] = $previousQuery;
            }
            putenv($previousUpstream === false ? 'ADMIN_UI_DEV_UPSTREAM' : 'ADMIN_UI_DEV_UPSTREAM=' . $previousUpstream);
        }
    }

    public function testOpenIgnoresConnectionsWithoutAFileDescriptor(): void
    {
        $server = new UiDevelopmentWebSocketServerProbe();
        $bridge = new UiDevelopmentWebSocketBridge();

        $bridge->open($server, new UiDevelopmentWebSocketRequestProbe(0));

        self::assertSame([], $server->disconnected);
    }

    public function testOpenDisconnectsWhenNoDevelopmentUpstreamIsConfigured(): void
    {
        $server = new UiDevelopmentWebSocketServerProbe();
        $state = new UiDevelopmentWebSocketStateProbe();
        $bridge = new UiDevelopmentWebSocketBridge(
            new UiDevelopmentWebSocketHydratorProbe(),
            $state,
            new UiDevelopmentProxyResolver()
        );

        $bridge->open($server, new UiDevelopmentWebSocketRequestProbe(7));

        self::assertSame([7], $server->disconnected);
        self::assertSame(['websocket-context'], $state->cleanups);
    }

    public function testOpenDisconnectsAnUnauthorizedDevelopmentWebSocket(): void
    {
        putenv('ADMIN_UI_DEV_UPSTREAM=http://ui:4200');
        Security::setTest(false);
        $server = new UiDevelopmentWebSocketServerProbe();
        $state = new UiDevelopmentWebSocketStateProbe();
        $bridge = new UiDevelopmentWebSocketBridge(
            new UiDevelopmentWebSocketHydratorProbe(),
            $state,
            new UiDevelopmentProxyResolver()
        );

        $bridge->open($server, new UiDevelopmentWebSocketRequestProbe(8));

        self::assertSame([8], $server->disconnected);
        self::assertSame(['websocket-context'], $state->cleanups);
    }

    public function testOpenBridgesAConnectedUpstreamUntilItCloses(): void
    {
        putenv('ADMIN_UI_DEV_UPSTREAM=http://ui:4200');
        $client = new UiDevelopmentWebSocketClientProbe(true);
        $server = new UiDevelopmentWebSocketServerProbe();
        $state = new UiDevelopmentWebSocketStateProbe();
        $bridge = new UiDevelopmentWebSocketBridge(
            new UiDevelopmentWebSocketHydratorProbe(),
            $state,
            new UiDevelopmentProxyResolver(),
            static fn(): UiDevelopmentWebSocketClientProbe => $client
        );

        $bridge->open($server, new UiDevelopmentWebSocketRequestProbe(11));

        self::assertTrue($client->closed);
        self::assertSame([11], $server->disconnected);
        self::assertSame(['websocket-context'], $state->cleanups);
    }

    public function testMessageAndCloseIgnoreUnknownConnections(): void
    {
        $server = new UiDevelopmentWebSocketServerProbe();
        $bridge = new UiDevelopmentWebSocketBridge();
        $frame = (object)['fd' => 99, 'data' => 'ping', 'opcode' => 1, 'flags' => 0];

        $bridge->message($server, $frame);
        $bridge->close($server, 99);

        self::assertSame([], $server->disconnected);
    }

    public function testRequestUriAndUpstreamHeadersCarryTheWebSocketForwardingContext(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin-v2/socket';
        $_SERVER['QUERY_STRING'] = 'token=hmr';
        $_SERVER['HTTP_HOST'] = 'core.test';
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_ORIGIN'] = 'https://admin.test';
        $_SERVER['HTTP_SEC_WEBSOCKET_PROTOCOL'] = 'psfs-hmr';
        $bridge = new UiDevelopmentWebSocketBridge();

        $uriMethod = new \ReflectionMethod($bridge, 'requestUri');
        $headersMethod = new \ReflectionMethod($bridge, 'upstreamHeaders');
        $headers = $headersMethod->invoke($bridge, [
            'host' => 'ui.test',
            'port' => 4200,
        ]);

        self::assertSame('/admin-v2/socket?token=hmr', $uriMethod->invoke($bridge));
        self::assertSame('ui.test:4200', $headers['Host']);
        self::assertSame('https://admin.test', $headers['Origin']);
        self::assertSame('psfs-hmr', $headers['Sec-Websocket-Protocol']);
    }

    public function testConnectConfiguresAndUpgradesTheInjectedUpstreamClient(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin-v2/socket?token=hmr';
        $client = new UiDevelopmentWebSocketClientProbe(true);
        $bridge = new UiDevelopmentWebSocketBridge(
            clientFactory: static function (string $host, int $port, bool $ssl) use ($client): UiDevelopmentWebSocketClientProbe {
                $client->host = $host;
                $client->port = $port;
                $client->ssl = $ssl;
                return $client;
            }
        );
        $method = new \ReflectionMethod($bridge, 'connect');
        $result = $method->invoke($bridge, new UiDevelopmentProxyTarget('/admin-v2', 'https://ui.test:4200'));

        self::assertSame($client, $result);
        self::assertSame(['timeout' => 10, 'keep_alive' => true], $client->settings);
        self::assertSame('ui.test', $client->host);
        self::assertSame(4200, $client->port);
        self::assertTrue($client->ssl);
        self::assertSame('/admin-v2/socket?token=hmr', $client->uri);
    }

    public function testConnectClosesTheClientWhenTheUpstreamUpgradeFails(): void
    {
        $client = new UiDevelopmentWebSocketClientProbe(false);
        $bridge = new UiDevelopmentWebSocketBridge(
            clientFactory: static fn(): UiDevelopmentWebSocketClientProbe => $client
        );
        $method = new \ReflectionMethod($bridge, 'connect');

        self::assertNull($method->invoke($bridge, new UiDevelopmentProxyTarget('/ui', 'http://ui.test')));
        self::assertTrue($client->closed);
    }

    public function testConnectRejectsAnUpstreamWithoutAHost(): void
    {
        $bridge = new UiDevelopmentWebSocketBridge();
        $method = new \ReflectionMethod($bridge, 'connect');

        self::assertNull($method->invoke($bridge, new UiDevelopmentProxyTarget('/ui', 'not-a-url')));
    }

    public function testMessageDiscardsAndDisconnectsWhenTheUpstreamRejectsAFrame(): void
    {
        $client = new UiDevelopmentWebSocketClientProbe(true, false);
        $bridge = new UiDevelopmentWebSocketBridge();
        $clients = new \ReflectionProperty($bridge, 'clients');
        $clients->setValue($bridge, [12 => $client]);
        $server = new UiDevelopmentWebSocketServerProbe();

        $bridge->message($server, (object)['fd' => 12, 'data' => 'ping', 'opcode' => 1, 'flags' => 0]);

        self::assertTrue($client->closed);
        self::assertSame([12], $server->disconnected);
    }
}

class UiDevelopmentWebSocketRequestProbe
{
    public function __construct(public int $fd)
    {
    }
}

class UiDevelopmentWebSocketServerProbe
{
    /** @var int[] */
    public array $disconnected = [];

    public function isEstablished(int $fd): bool
    {
        return true;
    }

    public function disconnect(int $fd): void
    {
        $this->disconnected[] = $fd;
    }
}

class UiDevelopmentWebSocketHydratorProbe extends SwooleRequestHydrator
{
    public function hydrate(object $request): string
    {
        $_SERVER = [
            'REQUEST_URI' => '/admin-v2/socket',
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => '',
        ];
        return 'websocket-context';
    }
}

class UiDevelopmentWebSocketStateProbe extends SwooleRuntimeStateManager
{
    /** @var string[] */
    public array $cleanups = [];

    public function resetBeforeRequest(): void
    {
    }

    public function cleanupAfterRequest(string $contextId): void
    {
        $this->cleanups[] = $contextId;
    }
}

class UiDevelopmentWebSocketClientProbe
{
    public array $settings = [];
    public array $headers = [];
    public string $uri = '';
    public bool $closed = false;
    public string $host = '';
    public int $port = 0;
    public bool $ssl = false;

    public function __construct(
        private readonly bool $upgradeResult,
        private readonly bool $pushResult = true
    ) {
    }

    public function set(array $settings): void
    {
        $this->settings = $settings;
    }

    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    public function upgrade(string $uri): bool
    {
        $this->uri = $uri;
        return $this->upgradeResult;
    }

    public function push(string $data, int $opcode, int $flags): bool
    {
        return $this->pushResult;
    }

    public function recv(int $timeout): ?object
    {
        return null;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
