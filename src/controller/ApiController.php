<?php
namespace PSFS\controller;

use PSFS\base\Router;
use PSFS\base\types\AuthAdminController;

/**
 * Class Api
 * @package PSFS\controller
 */
class ApiController extends AuthAdminController
{
    const PSFS_DOC = 'psfs';
    const SWAGGER_DOC = 'swagger';
    const POSTMAN_DOC = 'postman';
    const HTML_DOC = 'html';

    /**
     * @Injectable
     * @var \PSFS\services\DocumentorService $srv
     */
    protected $srv;

    /**
     * @GET
     * @route /admin/api/docs
     * @label Documentación api
     * @return string HTML
     */
    public function documentorHome() {
        $domains = Router::getInstance()->getDomains();
        return $this->render('api.home.html.twig', [
            'domains' => $domains,
            'types' => [
                self::PSFS_DOC,
                self::SWAGGER_DOC,
                self::POSTMAN_DOC,
                self::HTML_DOC,
            ]
        ]);
    }
}