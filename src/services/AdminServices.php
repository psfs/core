<?php

namespace PSFS\services;

use PSFS\base\config\Config;
use PSFS\base\Security;
use PSFS\base\Service;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\types\traits\TestTrait;

class AdminServices extends Service
{
    use TestTrait;

    /**
     * @Injectable
     * @var \PSFS\base\config\Config Servicio de configuración
     */
    protected $config;
    /**
     * @Injectable
     * @var \PSFS\base\Security Servicio de autenticación
     */
    protected $security;
    /**
     * @Injectable
     * @var \PSFS\base\Template Servicio de gestión de plantillas
     */
    protected $tpl;

    /**
     * Servicio que devuelve las cabeceras de autenticación
     * @return string HTML
     */
    public function setAdminHeaders()
    {
        $platform = trim(Config::getInstance()->get('platform.name', 'PSFS'));
        ResponseHelper::setHeader('HTTP/1.1 401 Unauthorized');
        ResponseHelper::setHeader('WWW-Authenticate: Basic Realm="' . $platform . '"');
        echo t('Zona restringida');
        exit();
    }

    /**
     * Servicio que devuelve los administradores de la plataforma
     * @return array|mixed
     */
    public function getAdmins()
    {
        $admins = $this->security->getAdmins();
        if (!empty($admins)) {
            if (!$this->security->checkAdmin() && !self::isTest()) {
                $this->setAdminHeaders();
            }
        }
        $this->parseAdmins($admins);
        return $admins;
    }

    /**
     * Servicio que parsea los administradores para mostrarlos en la gestión de usuarios
     * @param array $admins
     */
    private function parseAdmins(&$admins)
    {
        if (!empty($admins)) {
            foreach ($admins as &$admin) {
                if (isset($admin['profile'])) {
                    switch ($admin['profile']) {
                        case Security::MANAGER_ID_TOKEN:
                            $admin['class'] = 'warning';
                            break;
                        case Security::ADMIN_ID_TOKEN:
                            $admin['class'] = 'info';
                            break;
                        default:
                        case Security::USER_ID_TOKEN:
                            $admin['class'] = 'primary';
                            break;
                    }
                } else {
                    $admin['class'] = 'primary';
                }
            }
        }
    }

}
