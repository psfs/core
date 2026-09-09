<?php

namespace PSFS\runtime\swoole;

interface UiDevelopmentHttpProxyInterface
{
    /**
     * @return array{status:int,headers:array<string,mixed>,body:string}|null
     */
    public function forward(UiDevelopmentProxyTarget $target, string $requestUri): ?array;
}
