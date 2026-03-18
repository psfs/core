<?php

namespace PSFS\base\types\traits\Helper;

use Exception;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Template;
use PSFS\base\config\Config;
use PSFS\base\dto\JsonResponse;
use PSFS\base\exception\GeneratorException;
use PSFS\base\Logger;
use PSFS\base\types\helpers\Inspector;

trait ResponseNotFoundTrait
{
    /**
     * @param Exception|NULL $exception
     * @param bool $isJson
     * @return int|string
     * @throws GeneratorException
 */
    public static function httpNotFound(\Throwable $exception = null, bool $isJson = false): int|string
    {
        if (self::isTest()) {
            return 404;
        }
        Inspector::stats('[Router] Throw not found exception', Inspector::SCOPE_DEBUG);
        if (null === $exception) {
            Logger::log('Not found page thrown without previous exception', LOG_WARNING);
            $exception = new Exception(t('Page not found'), 404);
        }
        $template = Template::getInstance()->setStatus($exception->getCode());
        if (self::shouldReturnJsonNotFound($isJson)) {
            $response = new JsonResponse(null, false, 0, 0, $exception->getMessage());
            return $template->output(json_encode($response), 'application/json');
        }

        $notFoundRoute = Config::getParam('route.404');
        if (null !== $notFoundRoute) {
            Request::getInstance()->redirect(Router::getInstance()->getRoute($notFoundRoute, true));
        } else {
            return $template->render('error.html.twig', array(
                'exception' => $exception,
                'trace' => $exception->getTraceAsString(),
                'error_page' => true,
            ));
        }
        return 200;
    }

    public static function shouldReturnJsonNotFound(bool $isJson = false): bool
    {
        if ($isJson) {
            return true;
        }

        $request = Request::getInstance();
        $contentType = strtolower((string)$request->getServer('CONTENT_TYPE', ''));
        $accept = strtolower((string)$request->getServer('HTTP_ACCEPT', ''));

        return str_contains($contentType, 'json') || str_contains($accept, 'json');
    }
}
