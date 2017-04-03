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
        $download = $this->getRequest()->get('download') ?: false;

        $module = $this->srv->getModules($domain);
        if(empty($module)) {
            return Router::getInstance()->httpNotFound(null, true);
        }
        switch (strtolower($type)) {
            case ApiController::SWAGGER_DOC:
                $doc = DocumentorService::swaggerFormatter($module);
                break;
            case ApiController::POSTMAN_DOC:
                $doc = ['Pending...'];
                break;
            default:
            case ApiController::HTML_DOC:
            case ApiController::PSFS_DOC:
                $doc = $this->srv->extractApiEndpoints($module);
                break;
        }

        ini_restore('max_execution_time');
        ini_restore('memory_limit');

        if($download && $type === ApiController::SWAGGER_DOC) {
            return $this->download(\GuzzleHttp\json_encode($doc), 'application/json', 'swagger.json');
        } elseif($type === ApiController::HTML_DOC) {
            return $this->render('documentation.html.twig', ["data" => json_encode($doc)]);
        } else {
            return $this->json($doc, 200);
        }
    }

    /**
     * @GET
     * @route /admin/{domain}/swagger-ui
     * @param string $domain
     * @return string HTML
     */
    public function swaggerUi($domain) {
        return $this->render('swagger.html.twig', [
            'domain' => $domain,
        ]);
    }
}