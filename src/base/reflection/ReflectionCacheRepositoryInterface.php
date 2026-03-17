<?php

namespace PSFS\base\reflection;

interface ReflectionCacheRepositoryInterface
{
    public function read(): array;

    public function save(array $properties): bool;

    public function refresh(): array;

    public function invalidate(): void;

    public function getCachePath(): string;

    public function getSourceSignature(): string;
}
