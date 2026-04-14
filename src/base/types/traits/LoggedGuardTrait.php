<?php

namespace PSFS\base\types\traits;

trait LoggedGuardTrait
{
    protected function ensureLoggedOrThrow(\Throwable $exception): void
    {
        if (!$this->isLogged()) {
            throw $exception;
        }
    }
}
