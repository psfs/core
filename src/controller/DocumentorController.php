<?php
namespace PSFS\controller;

use PSFS\base\Router;
use PSFS\base\types\Controller;
use PSFS\services\DocumentorService;

class DocumentorController extends Controller {
    const DOMAIN = 'ROOT';

    /**
     * @Inyectable
     * @var \PSFS\services\DocumentorService $srv
     */
    protected $srv;

    /**
     * @GET
     * @CACHE 600
     * @label Generador de documentación API
     * @route /{domain}/api/doc
     *
     * @param string $domain
     *
     * @return string JSON
     */
    public function createApiDocs($domain)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);

        $type = $this->getRequest()->get('type') ?: ApiController::PSFS_DOC;

        $endpoints = [];
        $module = $this->srv->getModules($domain);
        if(empty($module)) {
            return Router::getInstance()->httpNotFound(null, true);
        }
        switch (strtolower($type)) {
            case ApiController::SWAGGER_DOC:
                $doc = DocumentorService::swaggerFormatter($module);
                break;
            default:
            case ApiController::HTML_DOC:
            case ApiController::PSFS_DOC:
                $endpoints = array_merge($endpoints, $this->srv->extractApiEndpoints($module));
                $doc = $endpoints;
                break;
        }

        ini_restore('max_execution_time');
        ini_restore('memory_limit');

        return ($type === ApiController::HTML_DOC) ? $this->render('documentation.html.twig', ["data" => json_encode($doc)]) : $this->json($doc, 200);
    }
}