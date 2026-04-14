<?php

namespace PSFS\runtime\swoole;

use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\SingletonRegistry;
use PSFS\base\Template;
use PSFS\base\extension\CustomTranslateExtension;
use PSFS\base\types\helpers\EventHelper;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\Dispatcher;

class SwooleRuntimeStateManager
{
    public function resetBeforeRequest(): void
    {
        ResponseHelper::$headers_sent = [];
        Inspector::reset();
        EventHelper::clear(EventHelper::EVENT_END_REQUEST);
        SingletonRegistry::clear();
        Dispatcher::dropInstance();
        Request::dropInstance();
        Security::dropInstance();
        Template::dropInstance();
        CustomTranslateExtension::resetRuntimeState();
    }

    public function cleanupAfterRequest(string $contextId): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $closed = session_write_close();
            if (false === $closed) {
                // Continue cleanup even if session close failed.
            }
        }
        if (function_exists('session_id')) {
            $sessionId = session_id('');
            if (false === $sessionId) {
                // Ignore invalid session id reset attempts.
            }
        }

        $_SESSION = [];
        ResponseHelper::$headers_sent = [];
        EventHelper::clear(EventHelper::EVENT_END_REQUEST);
        SingletonRegistry::clear();
        CustomTranslateExtension::resetRuntimeState();

        if (function_exists('header_remove')) {
            header_remove();
        }

        if (
            isset($_SERVER[SingletonRegistry::CONTEXT_SESSION]) &&
            $_SERVER[SingletonRegistry::CONTEXT_SESSION] === $contextId
        ) {
            unset($_SERVER[SingletonRegistry::CONTEXT_SESSION]);
        }
        unset($_SERVER[SwooleRequestHandler::RAW_BODY_SERVER_KEY]);
    }
}
