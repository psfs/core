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
use PSFS\base\types\traits\RouteTrait;

/**
 * Trait ManagerTrait
 * @package PSFS\base\types\traits\Api
 */
trait ManagerTrait
{
    use RouteTrait;
    use ApiTrait;

    /**
     * Return the admin menus
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
     * @return string HTML
     * @throws ApiException
     */
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
            'formUrl' => preg_replace('/\/\{(.*)\}$/i', '', $this->getRoute(strtolower('admin-api-form-' . $this->getDomain() . '-' . $this->getApi()), TRUE)),
            "url" => preg_replace('/\/\{(.*)\}$/i', '', $this->getRoute(strtolower($this->getDomain() . '-' . 'api-' . $this->getApi() . "-pk"), TRUE)),
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
    public function getForm()
    {
        $map = $this->getModelTableMap();
        $form = ApiHelper::generateFormFields($map, $this->getDomain());
        $form->actions = ApiFormHelper::checkApiActions(get_class($this), $this->getDomain(), $this->getApi());

        return $this->_json(new JsonResponse($form->toArray(), TRUE), 200);
    }

}
