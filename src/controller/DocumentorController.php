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

    #[Injectable(class: DocumentorService::class)]
    protected DocumentorService $srv;

    /**
     * @param string $domain
     * @throws \ReflectionException
     */
    #[HttpMethod('GET')]
    #[Cacheable(true)]
    #[Label('API documentation generator')]
    #[Route('/{domain}/api/doc')]
    public function createApiDocs($domain)
    {
        $this->prepareDocumentationRuntime();
        $request = $this->documentationRequest((string)$domain);
        $cached = $this->cachedApiDoc($request);
        if (null !== $cached) {
            return $this->json($cached, 200);
        }

        $module = $this->srv->getModules((string)$domain);
        if (empty($module)) {
            return ResponseHelper::httpNotFound(null, true);
        }
        $doc = $this->formatApiDoc($module, $this->srv->buildEndpointSpec($module), $request['type']);

        if ($this->isDownloadableDoc($request['type']) && $request['download']) {
            return $this->download(
                json_encode($doc),
                'application/json',
                $this->downloadFilename($request['type'])
            );
        }
        if ($request['type'] === ApiController::HTML_DOC) {
            return $this->render('documentation.html.twig', ["data" => json_encode($doc)]);
        }
        $this->storeApiDoc($request, $doc);
        return $this->json($doc, 200);
    }

    private function prepareDocumentationRuntime(): void
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);
    }

    /**
     * @return array{domain:string,type:string,download:mixed,cache_ttl:int,cache_key:string}
     */
    private function documentationRequest(string $domain): array
    {
        $type = strtolower((string)($this->getRequest()->get('type') ?: ApiController::PSFS_DOC));
        $cacheVersion = (string)Config::getParam('cache.var', 'v1');

        return [
            'domain' => $domain,
            'type' => $type,
            'download' => $this->getRequest()->get('download') ?: false,
            'cache_ttl' => (int)Config::getParam('api.doc.cache.ttl', 300),
            'cache_key' => implode(':', [$domain, $type, $cacheVersion]),
        ];
    }

    /**
     * @param array{download:mixed,cache_ttl:int,cache_key:string} $request
     */
    private function cachedApiDoc(array $request): ?array
    {
        if ($request['cache_ttl'] <= 0 || $request['download'] || !isset(self::$docsCache[$request['cache_key']])) {
            return null;
        }

        $entry = self::$docsCache[$request['cache_key']];
        if ($entry['expires'] >= time()) {
            return $entry['doc'];
        }

        unset(self::$docsCache[$request['cache_key']]);
        return null;
    }

    private function formatApiDoc(array $module, array $doc, string $type): array
    {
        return match ($type) {
            ApiController::SWAGGER_DOC => $this->srv->swaggerFormatter($module, $doc),
            ApiController::POSTMAN_DOC => $this->srv->postmanFormatter($module, $doc),
            ApiController::OPENAPI_DOC => $this->srv->openApiFormatter($module, $doc),
            default => $doc,
        };
    }

    private function isDownloadableDoc(string $type): bool
    {
        return in_array($type, [ApiController::SWAGGER_DOC, ApiController::POSTMAN_DOC, ApiController::OPENAPI_DOC], true);
    }

    private function downloadFilename(string $type): string
    {
        return match ($type) {
            ApiController::POSTMAN_DOC => 'postman.collection.json',
            ApiController::OPENAPI_DOC => 'openapi.json',
            default => 'swagger.json',
        };
    }

    /**
     * @param array{type:string,cache_ttl:int,cache_key:string} $request
     */
    private function storeApiDoc(array $request, array $doc): void
    {
        if (
            $request['cache_ttl'] <= 0
            || in_array($request['type'], [ApiController::PSFS_DOC, ApiController::HTML_DOC], true)
        ) {
            return;
        }
        self::$docsCache[$request['cache_key']] = [
            'doc' => $doc,
            'expires' => time() + $request['cache_ttl'],
        ];
    }

    /**
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
