<?php

namespace PSFS\controller;

use PSFS\base\exception\RouterException;
use PSFS\base\Router;
use PSFS\base\types\Controller;
use PSFS\base\types\helpers\attributes\Cacheable;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\attributes\Label;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\services\DocumentorService;

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
    #[Injectable]
    protected DocumentorService $srv;

    /**
     * @GET
     * @CACHE 600
     * @label API documentation generator
     * @route /{domain}/api/doc
     * @param string $domain
     * @return mixed|string
     * @throws \ReflectionException
     */
    #[HttpMethod('GET')]
    #[Cacheable(true)]
    #[Label('API documentation generator')]
    #[Route('/{domain}/api/doc')]
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
    #[HttpMethod('GET')]
    #[Route('/admin/{domain}/swagger-ui')]
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
