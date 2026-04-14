<?php

namespace PSFS\controller;

use PSFS\base\exception\RouterException;
use PSFS\base\Router;
use PSFS\base\config\Config;
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
     * @var array<string, array{doc:array, expires:int}>
     */
    private static array $docsCache = [];

    /**
     * @Injectable
     * @var \PSFS\services\DocumentorService $srv
     */
    #[Injectable(class: DocumentorService::class)]
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

        $type = strtolower((string)($this->getRequest()->get('type') ?: ApiController::PSFS_DOC));
        $download = $this->getRequest()->get('download') ?: false;
        $cacheVersion = (string)Config::getParam('cache.var', 'v1');
        $cacheTtl = (int)Config::getParam('api.doc.cache.ttl', 300);
        $cacheKey = implode(':', [$domain, $type, $cacheVersion]);

        if ($cacheTtl > 0 && !$download && isset(self::$docsCache[$cacheKey])) {
            $entry = self::$docsCache[$cacheKey];
            if ($entry['expires'] >= time()) {
                return $this->json($entry['doc'], 200);
            }
            unset(self::$docsCache[$cacheKey]);
        }

        $module = $this->srv->getModules((string)$domain);
        if (empty($module)) {
            return ResponseHelper::httpNotFound(null, true);
        }
        $doc = $this->srv->buildEndpointSpec($module);
        switch ($type) {
            case ApiController::SWAGGER_DOC:
                $doc = $this->srv->swaggerFormatter($module, $doc);
                break;
            case ApiController::POSTMAN_DOC:
                $doc = $this->srv->postmanFormatter($module, $doc);
                break;
            case ApiController::OPENAPI_DOC:
                $doc = $this->srv->openApiFormatter($module, $doc);
                break;
        }

        if ($download && in_array($type, [ApiController::SWAGGER_DOC, ApiController::POSTMAN_DOC, ApiController::OPENAPI_DOC], true)) {
            if ($type === ApiController::POSTMAN_DOC) {
                $filename = 'postman.collection.json';
            } elseif ($type === ApiController::OPENAPI_DOC) {
                $filename = 'openapi.json';
            } else {
                $filename = 'swagger.json';
            }
            return $this->download(json_encode($doc), 'application/json', $filename);
        }
        if ($type === ApiController::HTML_DOC) {
            return $this->render('documentation.html.twig', ["data" => json_encode($doc)]);
        }
        if ($cacheTtl > 0 && !in_array($type, [ApiController::PSFS_DOC, ApiController::HTML_DOC], true)) {
            self::$docsCache[$cacheKey] = [
                'doc' => $doc,
                'expires' => time() + $cacheTtl,
            ];
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
