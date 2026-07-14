<?php

namespace PSFS\runtime\swoole;

use PSFS\base\config\Config;
use PSFS\base\runtime\RuntimeMode;
use PSFS\base\Security;
use PSFS\base\types\helpers\ResponseHelper;

class SwooleRequestHandler
{
    public const RAW_BODY_SERVER_KEY = 'PSFS_RAW_BODY';

    public function __construct(
        private readonly ?SwooleRequestHydrator $hydrator = null,
        private readonly ?SwooleStaticAssetServer $staticAssetServer = null,
        private readonly ?SwooleResponseEmitter $responseEmitter = null,
        private readonly ?SwooleRequestExecutor $requestExecutor = null,
        private readonly ?SwooleRuntimeStateManager $stateManager = null,
        private readonly ?UiDevelopmentProxyResolver $uiDevelopmentProxyResolver = null,
        private readonly ?UiDevelopmentHttpProxy $uiDevelopmentHttpProxy = null
    ) {
    }

    public function handle(object $request, object $response): void
    {
        RuntimeMode::enableSwoole();
        $contextId = $this->getHydrator()->hydrate($request);
        $this->getStateManager()->resetBeforeRequest();

        $requestUri = $this->getRequestUriWithQuery();
        $resolver = $this->getUiDevelopmentProxyResolver();
        $mount = $resolver->resolveMount(Config::getParam('ui.path'));
        if ($mount !== null && $resolver->matchesMount($requestUri, $mount)) {
            if (!Security::getInstance()->checkAdmin()) {
                $this->emitUiUnauthorized($response);
                $this->getStateManager()->cleanupAfterRequest($contextId);
                return;
            }

            $target = $resolver->resolve($requestUri, $mount, getenv('UI_DEV_UPSTREAM') ?: null);
            if ($target !== null) {
                $this->proxyUiDevelopmentRequest($response, $target, false);
                $this->getStateManager()->cleanupAfterRequest($contextId);
                return;
            }
            if ($this->getStaticAssetServer()->tryServe($response, $mount)
                || $this->getStaticAssetServer()->tryServeSpaFallback($response, $mount)) {
                $this->getStateManager()->cleanupAfterRequest($contextId);
                return;
            }
        }

        if ($this->getStaticAssetServer()->tryServe($response)) {
            $this->getStateManager()->cleanupAfterRequest($contextId);
            return;
        }

        [$statusCode, $body] = $this->getRequestExecutor()->execute((string)($_SERVER['REQUEST_URI'] ?? '/'));
        $this->emitFinalResponse($response, $statusCode, $body);
        $this->getStateManager()->cleanupAfterRequest($contextId);
    }

    /**
     * Kept for backward compatibility with existing call sites/tests.
     */
    public function emitResponse(object $response, int $statusCode, array $headers, string $body): void
    {
        $this->getResponseEmitter()->emit($response, $statusCode, $headers, $body);
    }

    private function getHydrator(): SwooleRequestHydrator
    {
        return $this->hydrator ?? new SwooleRequestHydrator();
    }

    private function getStaticAssetServer(): SwooleStaticAssetServer
    {
        return $this->staticAssetServer ?? new SwooleStaticAssetServer();
    }

    private function getResponseEmitter(): SwooleResponseEmitter
    {
        return $this->responseEmitter ?? new SwooleResponseEmitter();
    }

    private function getRequestExecutor(): SwooleRequestExecutor
    {
        return $this->requestExecutor ?? new SwooleRequestExecutor();
    }

    private function getStateManager(): SwooleRuntimeStateManager
    {
        return $this->stateManager ?? new SwooleRuntimeStateManager();
    }

    private function getUiDevelopmentProxyResolver(): UiDevelopmentProxyResolver
    {
        return $this->uiDevelopmentProxyResolver ?? new UiDevelopmentProxyResolver();
    }

    private function getUiDevelopmentHttpProxy(): UiDevelopmentHttpProxy
    {
        return $this->uiDevelopmentHttpProxy ?? new UiDevelopmentHttpProxy();
    }

    private function getRequestUriWithQuery(): string
    {
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        if (str_contains($requestUri, '?')) {
            return $requestUri;
        }

        $queryString = (string)($_SERVER['QUERY_STRING'] ?? '');
        return $queryString === '' ? $requestUri : $requestUri . '?' . $queryString;
    }

    private function emitFinalResponse(object $response, int $statusCode, string $body): void
    {
        $emitter = $this->getResponseEmitter();
        $headers = $emitter->mergeHeaders(ResponseHelper::$headers_sent, headers_list());
        $emitter->ensureSessionCookieHeader($headers);
        $resolvedStatusCode = $emitter->resolveStatusCode($headers, $statusCode);
        $emitter->emit($response, $resolvedStatusCode, $headers, $body);
    }

    private function proxyUiDevelopmentRequest(object $response, UiDevelopmentProxyTarget $target, bool $authorize = true): void
    {
        if ($authorize && !Security::getInstance()->checkAdmin()) {
            $this->emitUiUnauthorized($response);
            return;
        }

        $proxied = $this->getUiDevelopmentHttpProxy()->forward($target, $this->getRequestUriWithQuery());
        if ($proxied === null) {
            $this->getResponseEmitter()->emit($response, 502, [
                'content-type' => 'text/plain; charset=utf-8',
            ], 'Bad Gateway');
            return;
        }

        $headers = $this->getResponseEmitter()->mergeHeaders(ResponseHelper::$headers_sent, headers_list());
        $headers = array_merge($headers, $proxied['headers']);
        $this->getResponseEmitter()->ensureSessionCookieHeader($headers);
        $this->getResponseEmitter()->emit($response, (int)$proxied['status'], $headers, (string)$proxied['body']);
    }

    private function emitUiUnauthorized(object $response): void
    {
        $realm = trim((string)Config::getParam('platform.name', 'PSFS'));
        $this->getResponseEmitter()->emit($response, 401, [
            'www-authenticate' => 'Basic Realm="' . ($realm === '' ? 'PSFS' : $realm) . '"',
        ], t('Restricted area'));
    }

}
