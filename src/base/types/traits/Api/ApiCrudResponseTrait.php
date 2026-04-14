<?php

namespace PSFS\base\types\traits\Api;

use Exception;
use PSFS\base\Logger;

trait ApiCrudResponseTrait
{
    protected function buildMutationErrorMessage(string $publicPrefix, Exception $e, bool $debug): string
    {
        if ($debug) {
            return t($publicPrefix) . '<br>' . $e->getMessage();
        }
        return t($publicPrefix) . '<br>' . $e->getCode();
    }

    protected function logCriticalException(Exception $e): void
    {
        $context = $this->extractExceptionContext($e);
        Logger::log($e->getMessage(), LOG_CRIT, $context);
    }

    /**
     * @return array<int, string>
     */
    protected function extractExceptionContext(Exception $e): array
    {
        $context = [];
        if (null !== $e->getPrevious()) {
            $context[] = $e->getPrevious()->getMessage();
        }
        return $context;
    }
}

