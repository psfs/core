<?php

namespace PSFS\services;

use PSFS\base\config\Config;
use PSFS\base\Security;
use PSFS\base\Service;
use PSFS\base\Template;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\types\traits\TestTrait;

class AdminServices extends Service
{
    use TestTrait;

    /**
     * @Injectable
     * @var \PSFS\base\config\Config
     */
    #[Injectable]
    protected Config $config;
    /**
     * @Injectable
     * @var \PSFS\base\Security
     */
    #[Injectable]
    protected Security $security;
    /**
     * @Injectable
     * @var \PSFS\base\Template
     */
    #[Injectable]
    protected Template $tpl;

    /**
     * @return string
     */
    public function setAdminHeaders()
    {
        $platform = trim(Config::getInstance()->get('platform.name', 'PSFS'));
        ResponseHelper::setHeader('HTTP/1.1 401 Unauthorized');
        ResponseHelper::setHeader('WWW-Authenticate: Basic Realm="' . $platform . '"');
        echo t('Restricted area');
        exit();
    }

    /**
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
     * @param array $admins
     */
    private function parseAdmins(&$admins)
    {
        if (!empty($admins)) {
            foreach ($admins as &$admin) {
                if (isset($admin['profile'])) {
                    switch ($admin['profile']) {
                        case AuthHelper::MANAGER_ID_TOKEN:
                            $admin['class'] = 'warning';
                            break;
                        case AuthHelper::ADMIN_ID_TOKEN:
                            $admin['class'] = 'info';
                            break;
                        default:
                        case AuthHelper::USER_ID_TOKEN:
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
