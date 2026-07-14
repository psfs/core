<?php

namespace PSFS\runtime\swoole;

use PSFS\base\config\Config;
use PSFS\base\runtime\RuntimeMode;
use PSFS\base\Security;

final class UiDevelopmentWebSocketBridge
{
    /** @var array<int, \Swoole\Coroutine\Http\Client> */
    private array $clients = [];

    public function __construct(
        private readonly ?SwooleRequestHydrator $hydrator = null,
        private readonly ?SwooleRuntimeStateManager $stateManager = null,
        private readonly ?UiDevelopmentProxyResolver $resolver = null
    ) {
    }

    public function open(object $server, object $request): void
    {
        $fd = (int)($request->fd ?? 0);
        if ($fd <= 0) {
            return;
        }

        RuntimeMode::enableSwoole();
        $contextId = $this->getHydrator()->hydrate($request);
        $this->getStateManager()->resetBeforeRequest();
        try {
            $target = $this->getResolver()->resolve(
                $this->requestUri(),
                Config::getParam('ui.path'),
                getenv('UI_DEV_UPSTREAM') ?: null
            );
            if ($target === null || !Security::getInstance()->checkAdmin()) {
                $this->disconnect($server, $fd);
                return;
            }
            $client = $this->connect($target);
            if ($client === null) {
                $this->disconnect($server, $fd);
                return;
            }
            $this->clients[$fd] = $client;
        } finally {
            $this->getStateManager()->cleanupAfterRequest($contextId);
        }

        \Swoole\Coroutine::create(function () use ($server, $fd, $client): void {
            while (true) {
                $frame = $client->recv(60);
                if (!$frame instanceof \Swoole\WebSocket\Frame) {
                    break;
                }
                if (!$server->push($fd, $frame->data, $frame->opcode, $frame->flags)) {
                    break;
                }
            }
            $this->discard($fd);
            $this->disconnect($server, $fd);
        });
    }

    public function message(object $server, object $frame): void
    {
        $fd = (int)($frame->fd ?? 0);
        $client = $this->clients[$fd] ?? null;
        if ($client === null) {
            return;
        }
        if (!$client->push((string)$frame->data, (int)$frame->opcode, (int)$frame->flags)) {
            $this->discard($fd);
            $this->disconnect($server, $fd);
        }
    }

    public function close(object $server, int $fd): void
    {
        $this->discard($fd);
    }

    private function connect(UiDevelopmentProxyTarget $target): ?\Swoole\Coroutine\Http\Client
    {
        $parts = parse_url($target->upstream);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $ssl = ($parts['scheme'] ?? 'http') === 'https';
        $port = (int)($parts['port'] ?? ($ssl ? 443 : 80));
        $client = new \Swoole\Coroutine\Http\Client((string)$parts['host'], $port, $ssl);
        $client->set(['timeout' => 10, 'keep_alive' => true]);
        $client->setHeaders($this->upstreamHeaders($parts));
        if (!$client->upgrade($this->requestUri())) {
            $client->close();
            return null;
        }
        return $client;
    }

    private function upstreamHeaders(array $upstream): array
    {
        $headers = [
            'Host' => (string)$upstream['host'] . (isset($upstream['port']) ? ':' . (int)$upstream['port'] : ''),
            'X-Forwarded-Host' => (string)($_SERVER['HTTP_HOST'] ?? ''),
            'X-Forwarded-Proto' => (string)($_SERVER['REQUEST_SCHEME'] ?? 'http'),
            'X-Forwarded-For' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ];
        foreach (['origin', 'sec-websocket-protocol'] as $name) {
            $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            if (isset($_SERVER[$serverKey])) {
                $headers[implode('-', array_map('ucfirst', explode('-', $name)))] = (string)$_SERVER[$serverKey];
            }
        }
        return $headers;
    }

    private function requestUri(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        if (str_contains($uri, '?')) {
            return $uri;
        }
        $query = (string)($_SERVER['QUERY_STRING'] ?? '');
        return $query === '' ? $uri : $uri . '?' . $query;
    }

    private function discard(int $fd): void
    {
        $client = $this->clients[$fd] ?? null;
        unset($this->clients[$fd]);
        if ($client !== null) {
            $client->close();
        }
    }

    private function disconnect(object $server, int $fd): void
    {
        if (method_exists($server, 'isEstablished') && $server->isEstablished($fd)) {
            $server->disconnect($fd);
        }
    }

    private function getHydrator(): SwooleRequestHydrator
    {
        return $this->hydrator ?? new SwooleRequestHydrator();
    }

    private function getStateManager(): SwooleRuntimeStateManager
    {
        return $this->stateManager ?? new SwooleRuntimeStateManager();
    }

    private function getResolver(): UiDevelopmentProxyResolver
    {
        return $this->resolver ?? new UiDevelopmentProxyResolver();
    }
}
