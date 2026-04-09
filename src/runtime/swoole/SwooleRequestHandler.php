<?php

namespace PSFS\runtime\swoole;

use PSFS\base\runtime\RuntimeMode;
use PSFS\base\types\helpers\ResponseHelper;

class SwooleRequestHandler
{
    public const RAW_BODY_SERVER_KEY = 'PSFS_RAW_BODY';

    public function __construct(
        private readonly ?SwooleRequestHydrator $hydrator = null,
        private readonly ?SwooleStaticAssetServer $staticAssetServer = null,
        private readonly ?SwooleResponseEmitter $responseEmitter = null,
        private readonly ?SwooleRequestExecutor $requestExecutor = null,
        private readonly ?SwooleRuntimeStateManager $stateManager = null
    ) {
    }

    public function handle(object $request, object $response): void
    {
        RuntimeMode::enableSwoole();
        $contextId = $this->getHydrator()->hydrate($request);
        $this->getStateManager()->resetBeforeRequest();

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

    private function emitFinalResponse(object $response, int $statusCode, string $body): void
    {
        $emitter = $this->getResponseEmitter();
        $headers = $emitter->mergeHeaders(ResponseHelper::$headers_sent, headers_list());
        $emitter->ensureSessionCookieHeader($headers);
        $resolvedStatusCode = $emitter->resolveStatusCode($headers, $statusCode);
        $emitter->emit($response, $resolvedStatusCode, $headers, $body);
    }

}
