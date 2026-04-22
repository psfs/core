<?php

namespace PSFS\controller;

use PSFS\base\Router;
use PSFS\base\types\AuthAdminController;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Icon;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\attributes\Label;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\services\DocumentorService;

/**
 * Class Api
 * @package PSFS\controller
 */
class ApiController extends AuthAdminController
{
    const PSFS_DOC = 'psfs';
    const SWAGGER_DOC = 'swagger';
    const POSTMAN_DOC = 'postman';
    const OPENAPI_DOC = 'openapi';
    const HTML_DOC = 'html';

    #[Injectable(class: DocumentorService::class)]
    protected DocumentorService $srv;

    #[HttpMethod('GET')]
    #[Route('/admin/api/docs')]
    #[Icon('fa-books')]
    #[Label('API documentation')]
    public function documentorHome()
    {
        $domains = Router::getInstance()->getDomains();
        return $this->render('api.home.html.twig', [
            'domains' => $domains,
            'types' => [
                self::PSFS_DOC,
                self::SWAGGER_DOC,
                self::POSTMAN_DOC,
                self::OPENAPI_DOC,
                self::HTML_DOC,
            ]
        ]);
    }
}
