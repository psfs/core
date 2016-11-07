<?php
namespace PSFS\controller;

use PSFS\base\types\Controller;
use PSFS\services\DocumentorService;

/**
 * Class Api
 * @package PSFS\controller
 */
class Api extends Controller
{
    const DOMAIN = 'ROOT';
    const PSFS_DOC = 'psfs';
    const SWAGGER_DOC = 'swagger';
    const POSTMAN_DOC = 'postman';
    const HTML_DOC = 'html';

    /**
     * @Inyectable
     * @var \PSFS\services\DocumentorService $srv
     */
    protected $srv;

    /**
     * @GET
     * @route /api/__doc/{type}
     *
     * @param string $type
     *
     * @return string JSON
     */
    public function createApiDocs($type = Api::PSFS_DOC)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);

        $endpoints = [];
        $modules = $this->srv->getModules();

        switch (strtolower($type)) {
            case self::SWAGGER_DOC:
                $doc = DocumentorService::swaggerFormatter($modules);
                break;
            default:
            case self::HTML_DOC:
            case self::PSFS_DOC:
                if (count($modules)) {
                    foreach ($modules as $module) {
                        $endpoints = array_merge($endpoints, $this->srv->extractApiEndpoints($module));
                    }
                }
                $doc = $endpoints;
                break;
        }

        ini_restore('max_execution_time');
        ini_restore('memory_limit');

        return ($type === self::HTML_DOC) ? $this->render('documentation.html.twig', ["data" => json_encode($doc)]) : $this->json($doc, 200);
    }
}