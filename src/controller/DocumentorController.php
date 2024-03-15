<?php

namespace PSFS\controller;

use PSFS\base\exception\RouterException;
use PSFS\base\Router;
use PSFS\base\types\Controller;
use PSFS\base\types\helpers\ResponseHelper;

/**
 * Class DocumentorController
 * @package PSFS\controller
 */
class DocumentorController extends Controller
{
    const DOMAIN = 'ROOT';

    /**
     * @Injectable
     * @var \PSFS\services\DocumentorService $srv
     */
    protected $srv;

    /**
     * @GET
     * @CACHE 600
     * @label Generador de documentación API
     * @route /{domain}/api/doc
     * @param string $domain
     * @return mixed|string
     * @throws \ReflectionException
     */
    public function createApiDocs($domain)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);

        $type = $this->getRequest()->get('type') ?: ApiController::PSFS_DOC;
        $download = $this->getRequest()->get('download') ?: false;

        $module = $this->srv->getModules($domain);
        if (empty($module)) {
            return ResponseHelper::httpNotFound(null, true);
        }
        $doc = $this->srv->extractApiEndpoints($module);
        switch (strtolower($type)) {
            case ApiController::SWAGGER_DOC:
                $doc = $this->srv->swaggerFormatter($module, $doc);
                break;
            case ApiController::POSTMAN_DOC:
                $doc = ['Pending...'];
                break;
        }

        if ($download && $type === ApiController::SWAGGER_DOC) {
            return $this->download(json_encode($doc), 'application/json', 'swagger.json');
        }
        if ($type === ApiController::HTML_DOC) {
            return $this->render('documentation.html.twig', ["data" => json_encode($doc)]);
        }
        return $this->json($doc, 200);
    }

    /**
     * @GET
     * @route /admin/{domain}/swagger-ui
     * @param string $domain
     * @return string HTML
     */
    public function swaggerUi($domain)
    {
        if (!Router::getInstance()->domainExists($domain)) {
            throw new RouterException('Domains is empty');
        }
        return $this->render('swagger.html.twig', [
            'domain' => $domain,
        ]);
    }
}
