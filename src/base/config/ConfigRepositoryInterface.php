<?php

namespace PSFS\base\config;

interface ConfigRepositoryInterface
{
    /**
     * @return array
     */
    public function read(): array;

    /**
     * @param array $data
     * @return bool
     */
    public function save(array $data): bool;

    /**
     * @return array
     */
    public function refresh(): array;

    /**
     * @return void
     */
    public function invalidate(): void;

    /**
     * @return string
     */
    public function getConfigPath(): string;
}

