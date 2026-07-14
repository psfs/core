<?php

namespace PSFS\controller;

use PSFS\base\AdminFrontendNavigationCatalog;
use PSFS\base\Router;
use PSFS\base\admin\AdminFrontendCsrf;
use PSFS\base\Security;
use PSFS\base\config\Config;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\AdminHelper;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\Visible;
use PSFS\controller\base\Admin;

/**
 * Authenticated bootstrap contract for the standalone administrative SPA.
 */
final class AdminFrontendController extends Admin
{
    #[HttpMethod('GET')]
    #[Route('/admin/api/v2/bootstrap')]
    #[Visible(false)]
    public function bootstrap(): string
    {
        $router = Router::getInstance();
        $identity = Security::getInstance()->getAdmin() ?? [];
        $profiles = Security::getProfiles();

        return $this->json([
            'identity' => [
                'username' => (string)($identity['alias'] ?? ''),
                'role' => (string)($profiles[(string)($identity['profile'] ?? '')] ?? t('User')),
            ],
            'locale' => I18nHelper::extractLocale((string) Config::getParam('default.language', 'en_US')),
            'locales' => $this->availableLocales(),
            'csrfToken' => AdminFrontendCsrf::issue(),
            'menu' => (new AdminFrontendNavigationCatalog())->build(
                AdminHelper::getAdminRoutes($router->getRoutes()),
                static fn(string $slug): ?string => $router->getRoute($slug)
            ),
        ]);
    }

    #[HttpMethod('PUT')]
    #[Route('/admin/api/v2/locale/{locale}')]
    #[Visible(false)]
    public function changeLocale(string $locale): string
    {
        if (!in_array($locale, $this->availableLocales(), true)) {
            return $this->json([
                'ok' => false,
                'message' => t('Invalid locale'),
                'data' => null,
                'errors' => ['locale' => [t('Invalid locale')]],
            ], 422);
        }

        I18nHelper::setLocale($locale, null, true);
        Security::getInstance()->updateSession();

        return $this->json([
            'ok' => true,
            'message' => null,
            'data' => ['locale' => $locale],
            'errors' => [],
        ]);
    }

    /** @return array<int,string> */
    private function availableLocales(): array
    {
        $locales = [];
        foreach (explode(',', (string) Config::getParam('i18n.locales', 'en_US,es_ES')) as $locale) {
            $locale = trim($locale);
            if ($locale !== '' && I18nHelper::isValidLocale($locale)) {
                $locales[] = $locale;
            }
        }

        return array_values(array_unique($locales ?: ['en_US', 'es_ES']));
    }
}
