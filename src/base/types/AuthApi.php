<?php
namespace PSFS\base\types;

use PSFS\base\config\Config;
use PSFS\base\dto\JsonResponse;
use PSFS\base\Request;
use PSFS\base\types\helpers\SecurityHelper;
use PSFS\base\types\traits\SecureTrait;

/**
 * Class AuthApi
 * @package PSFS\base\types
 */
abstract class AuthApi extends Api
{
    use SecureTrait;

    public function init()
    {
        parent::init();
        if (!$this->checkAuth()) {
            return $this->json(new JsonResponse(t('Not authorized'), FALSE), 401);
        }
    }

    /**
     * Check service authentication
     * @return bool
     */
    private function checkAuth()
    {
        $namespace = explode('\\', $this->getModelTableMap());
        $module = strtolower($namespace[0]);
        $secret = Config::getInstance()->get($module . '.api.secret');
        if (NULL === $secret) {
            $secret = Config::getInstance()->get("api.secret");
        }
        if (NULL === $secret) {
            $auth = TRUE;
        } else {
            $token = Request::getInstance()->getHeader('X-API-SEC-TOKEN');
            if (array_key_exists('API_TOKEN', $this->query)) {
                $token = $this->query['API_TOKEN'];
            }
            $auth = SecurityHelper::checkToken($token ?: '', $secret, $module);
        }

        return $auth || $this->isAdmin();
    }
}