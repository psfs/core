<?php

namespace PSFS\runtime\swoole;

final class UiDevelopmentProxyTarget
{
    public function __construct(
        public readonly string $mount,
        public readonly string $upstream
    ) {
    }

    public function upstreamUri(string $requestUri): string
    {
        return $this->upstream . ($requestUri === '' ? '/' : $requestUri);
    }
}
