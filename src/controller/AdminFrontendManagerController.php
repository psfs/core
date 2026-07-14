<?php

namespace PSFS\controller;

use PSFS\base\Router;
use PSFS\base\admin\AdminApiResponse;
use PSFS\base\exception\ApiException;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\Visible;
use PSFS\base\Security;
use PSFS\controller\base\Admin;

/**
 * JSON entry point for the native manager. It deliberately does not execute
 * CRUD itself: generated APIs remain the sole authorization and persistence
 * boundary.
 */
class AdminFrontendManagerController extends Admin
{
    #[HttpMethod('GET')]
    #[Route('/admin/api/v2/managers/{domain}/{api}')]
    #[Visible(false)]
    public function show(string $domain, string $api): string
    {
        $this->assertManagerAccess();
        $domain = $this->segment($domain);
        $api = $this->segment($api);
        $managerPath = '/admin/' . $domain . '/' . $api;

        if (!$this->managerExists($managerPath)) {
            return $this->json(AdminApiResponse::failure(
                t('Manager API not found'),
                ['manager' => [t('Manager API not found')]]
            ), 404);
        }

        return $this->json(AdminApiResponse::success([
            'domain' => $domain,
            'api' => $api,
            'endpoints' => [
                'list' => '/' . $domain . '/api/' . $api,
                'item' => '/' . $domain . '/api/' . $api . '/{pk}',
            ],
            // Generated manager mutations are not exposed until they have an
            // explicit v2 proxy that validates AdminFrontendCsrf server-side.
            'mutation' => ['supported' => false],
            'query' => [
                'page' => '__page',
                'limit' => '__limit',
                'order' => '__order',
                'combo' => '__combo',
            ],
        ]));
    }

    private function assertManagerAccess(): void
    {
        if (!Security::isTest() && Security::getInstance()->isUser()) {
            throw new ApiException(t('You are not authorized to access this resource'), 403);
        }
    }

    private function segment(string $value): string
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $value)) {
            throw new ApiException(t('Invalid manager identifier'), 422);
        }

        return $value;
    }

    private function managerExists(string $path): bool
    {
        foreach (Router::getInstance()->getSlugs() as $route) {
            if (is_string($route) && str_contains($route, '#' . $path)) {
                return true;
            }
        }

        return false;
    }
}
