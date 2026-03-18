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
    /**
     * Método que pinta por pantalla todas las rutas del sistema
     * @GET
     * @label Visor de rutas del sistema
     * @icon fa-folder-tree
     * @route /admin/routes
     */
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

    /**
     * Servicio que devuelve los parámetros disponibles
     * @GET
     * @route /admin/routes/show
     * @label Servicio de rutas del sistema
     * @visible false
     * @return mixed
     */
    #[HttpMethod('GET')]
    #[Route('/admin/routes/show')]
    #[Label('System routes service')]
    #[Visible(false)]
    public function getRouting()
    {
        $response = Router::getInstance()->getSlugs();
        return $this->json($response);
    }

    /**
     * Service to regenerate routes
     * @GET
     * @route /admin/routes/gen
     * @label Regenerar rutas
     * @visible false
     * @return string HTML
     */
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
            Security::getInstance()->setFlash("callback_message", t("Rutas generadas correctamente"));
            Security::getInstance()->setFlash("callback_route", $this->getRoute("admin-routes", true));
        } catch (Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
            Security::getInstance()->setFlash("callback_message", t("Algo no ha salido bien, revisa los logs"));
            Security::getInstance()->setFlash("callback_route", $this->getRoute("admin-routes", true));
        }
        return $this->redirect('admin-routes');
    }
}
