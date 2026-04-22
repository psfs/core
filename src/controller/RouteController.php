<?php

namespace PSFS\controller;

use Exception;
use PSFS\base\Logger;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Icon;
use PSFS\base\types\helpers\attributes\Label;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\Visible;
use PSFS\controller\base\Admin;

/**
 * Class RouteController
 * @package PSFS\controller
 */
class RouteController extends Admin
{
    #[HttpMethod('GET')]
    #[Label('System routes viewer')]
    #[Icon('fa-folder-tree')]
    #[Route('/admin/routes')]
    public function printRoutes()
    {
        return $this->render('routing.html.twig', array(
            'slugs' => Router::getInstance()->getAllRoutes(),
        ));
    }

    #[HttpMethod('GET')]
    #[Route('/admin/routes/show')]
    #[Label('System routes service')]
    #[Visible(false)]
    public function getRouting()
    {
        $response = Router::getInstance()->getSlugs();
        return $this->json($response);
    }

    #[HttpMethod('GET')]
    #[Route('/admin/routes/gen')]
    #[Label('Regenerate routes')]
    #[Visible(false)]
    public function regenerateUrls()
    {
        $router = Router::getInstance();
        try {
            $router->hydrateRouting();
            $router->simpatize();
            Security::getInstance()->setFlash("callback_message", t("Routes generated successfully"));
            Security::getInstance()->setFlash("callback_route", $this->getRoute("admin-routes", true));
        } catch (Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
            Security::getInstance()->setFlash("callback_message", t("Something went wrong, check the logs"));
            Security::getInstance()->setFlash("callback_route", $this->getRoute("admin-routes", true));
        }
        return $this->redirect('admin-routes');
    }
}
