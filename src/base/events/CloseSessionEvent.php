<?php

namespace PSFS\base\events;

use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\types\interfaces\EventInterface;

final class CloseSessionEvent implements EventInterface
{
    public function __invoke(): int
    {
        $uri = Request::requestUri();
        Security::getInstance()->setSessionKey('lastRequest', array(
            'url' => Request::getInstance()->getRootUrl() . $uri,
            'ts' => microtime(true),
            'eta' => microtime(true) - PSFS_START_TS,
            'mem' => memory_get_usage() / 1024 / 1024,
        ));
        Security::getInstance()->updateSession();
        return EventInterface::EVENT_SUCCESS;
    }

}
