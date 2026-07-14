<?php

namespace PSFS\controller;

use Exception;
use PSFS\base\Logger;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\admin\AdminApiResponse;
use PSFS\base\exception\ApiException;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\Visible;
use PSFS\controller\base\Admin;
use PSFS\services\DocumentorService;

/**
 * JSON-only administrative routes and API-documentation contract for Admin v2.
 */
class AdminFrontendRoutesController extends Admin
{
    #[HttpMethod('GET')]
    #[Route('/admin/api/v2/routes')]
    #[Visible(false)]
    public function routes(): string
    {
        return $this->json(AdminApiResponse::success([
            'routes' => $this->routeRows(Router::getInstance()->getSlugs()),
        ]));
    }

    #[HttpMethod('POST')]
    #[Route('/admin/api/v2/routes/regenerate')]
    #[Visible(false)]
    public function regenerate(): string
    {
        ini_set('memory_limit', '-1');
        $this->assertAdminAuthorization();
        $router = Router::getInstance();

        try {
            $router->hydrateRouting();
            $router->simpatize();

            return $this->json(AdminApiResponse::success([
                'regenerated' => true,
            ], t('Routes generated successfully')));
        } catch (Exception $exception) {
            Logger::log($exception->getMessage(), LOG_ERR);

            return $this->json(AdminApiResponse::failure(
                t('Something went wrong, check the logs'),
                ['routes' => [$exception->getMessage()]]
            ), 500);
        }
    }

    #[HttpMethod('GET')]
    #[Route('/admin/api/v2/docs')]
    #[Visible(false)]
    public function documentation(): string
    {
        $domainPaths = [];
        foreach (array_keys(Router::getInstance()->getDomains()) as $domain) {
            $canonical = str_replace(['@', '/', '\\'], '', (string)$domain);
            $normalized = strtolower($canonical);
            if ($normalized !== '' && $normalized !== 'root') {
                $domainPaths[$normalized] = '/' . $canonical . '/api/doc';
            }
        }
        $domains = array_keys($domainPaths);
        $domains = array_values(array_unique(array_filter(
            $domains,
            static fn (string $domain): bool => $domain !== '' && $domain !== 'root'
        )));
        sort($domains, SORT_NATURAL | SORT_FLAG_CASE);

        return $this->json(AdminApiResponse::success([
            'domains' => $domains,
            'documentPaths' => $domainPaths,
        ]));
    }

    #[HttpMethod('GET')]
    #[Route('/admin/api/v2/docs/{domain}')]
    #[Visible(false)]
    public function documentationDomain(string $domain): string
    {
        $router = Router::getInstance();
        if (!$router->domainExists($domain)) {
            return $this->documentationNotFound();
        }

        $service = DocumentorService::getInstance();
        $module = $service->getModules($this->canonicalDomainName($router->getDomains(), $domain));
        if (empty($module)) {
            return $this->documentationNotFound();
        }

        return $this->json(AdminApiResponse::success(
            $service->openApiFormatter($module, $service->buildEndpointSpec($module))
        ));
    }

    /** @param array<mixed> $slugs @return array<int, array{slug:string,route:mixed}> */
    private function routeRows(array $slugs): array
    {
        $rows = [];
        foreach ($slugs as $slug => $route) {
            $rows[] = [
                'slug' => (string)$slug,
                'route' => $route,
            ];
        }

        usort($rows, static fn (array $left, array $right): int => strnatcasecmp($left['slug'], $right['slug']));
        return $rows;
    }

    private function assertAdminAuthorization(): void
    {
        if (!Security::isTest() && !Security::getInstance()->isAdmin()) {
            throw new ApiException(t('Restricted area'), 403);
        }
    }

    private function documentationNotFound(): string
    {
        return $this->json(AdminApiResponse::failure(
            t('Documentation domain not found'),
            ['domain' => [t('Documentation domain not found')]]
        ), 404);
    }

    /** @param array<string, mixed> $domains */
    private function canonicalDomainName(array $domains, string $requestedDomain): string
    {
        foreach (array_keys($domains) as $domain) {
            $canonical = str_replace(['@', '/', '\\'], '', (string)$domain);
            if (strcasecmp($canonical, $requestedDomain) === 0) {
                return $canonical;
            }
        }

        return $requestedDomain;
    }
}
