<?php

namespace PSFS\base\types\traits\Api;

use PSFS\base\dto\JsonResponse;
use PSFS\base\exception\ApiException;
use PSFS\base\exception\GeneratorException;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\Api;
use PSFS\base\types\AuthAdminController;
use PSFS\base\types\helpers\ApiFormHelper;
use PSFS\base\types\helpers\ApiHelper;
use PSFS\base\types\helpers\attributes\Cacheable;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Icon;
use PSFS\base\types\helpers\attributes\Label;
use PSFS\base\types\helpers\attributes\Route as RouteAttribute;
use PSFS\base\types\helpers\attributes\Visible;
use PSFS\base\types\traits\RouteTrait;

/**
 * @package PSFS\base\types\traits\Api
 */
trait ManagerTrait
{
    use RouteTrait;
    use ApiTrait;

    /**
     * @return array
     */
    protected function getMenu()
    {
        return Router::getInstance()->getAllRoutes();
    }

    /**
     * @label {__API__} Manager
     * @GET
     * @icon fa-database
     * @route /admin/{__DOMAIN__}/{__API__}
     * @return string
     * @throws ApiException
     */
    #[Label('{__API__} Manager')]
    #[HttpMethod('GET')]
    #[Icon('fa-database')]
    #[RouteAttribute('/admin/{__DOMAIN__}/{__API__}')]
    public function admin()
    {
        if (Security::getInstance()->isUser()) {
            throw new ApiException(t('You are not authorized to access this resource'), 403);
        }
        return AuthAdminController::getInstance()->render('api.admin.html.twig', array(
            "api" => $this->getApi(),
            "domain" => $this->getDomain(),
            "listLabel" => Api::API_LIST_NAME_FIELD,
            'modelId' => Api::API_MODEL_KEY_FIELD,
            'formUrl' => preg_replace(
                '/\/\{(.*)\}$/i',
                '',
                $this->getRoute(strtolower('admin-api-form-' . $this->getDomain() . '-' . $this->getApi()), true)
            ),
            "url" => preg_replace(
                '/\/\{(.*)\}$/i',
                '',
                $this->getRoute(strtolower($this->getDomain() . '-' . 'api-' . $this->getApi() . "-pk"), true)
            ),
        ), [], '');
    }

    /**
     * @label Returns form data for any entity
     * @POST
     * @visible false
     * @cache 3600
     * @route /admin/api/form/{__DOMAIN__}/{__API__}
     * @return JsonResponse(data=\PSFS\base\dto\Form)
     * @throws GeneratorException
     * @throws \ReflectionException
     */
    #[Label('Returns form data for any entity')]
    #[HttpMethod('POST')]
    #[Visible(false)]
    #[Cacheable(true)]
    #[RouteAttribute('/admin/api/form/{__DOMAIN__}/{__API__}')]
    public function getForm()
    {
        $map = $this->getModelTableMap();
        $form = ApiHelper::generateFormFields($map, $this->getDomain());
        $form->actions = ApiFormHelper::checkApiActions(get_class($this), $this->getDomain(), $this->getApi());

        return $this->_json(new JsonResponse($form->toArray(), true), 200);
    }

}
