<?php

namespace PSFS\services;

use PSFS\base\config\Config;
use PSFS\base\exception\RequestTerminationException;
use PSFS\base\runtime\RuntimeMode;
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
    #[Injectable(class: Config::class)]
    protected Config $config;
    /**
     * @Injectable
     * @var \PSFS\base\Security
     */
    #[Injectable(class: Security::class)]
    protected Security $security;
    /**
     * @Injectable
     * @var \PSFS\base\Template
     */
    #[Injectable(class: Template::class)]
    protected Template $tpl;

    /**
     * @return string
     */
    public function setAdminHeaders(bool $forceNewRealm = false)
    {
        $platform = trim(Config::getInstance()->get('platform.name', 'PSFS'));
        if ($forceNewRealm) {
            $platform .= ' reauth-' . substr(sha1((string)microtime(true)), 0, 8);
        }
        ResponseHelper::setHeader('HTTP/1.1 401 Unauthorized');
        ResponseHelper::setHeader('WWW-Authenticate: Basic Realm="' . $platform . '"');
        $message = t('Restricted area');
        $isUnitTestExecution = defined('PSFS_UNIT_TESTING_EXECUTION') && true === PSFS_UNIT_TESTING_EXECUTION;
        if (!self::isTest() && !$isUnitTestExecution) {
            echo $message;
            if (RuntimeMode::isLongRunningServer()) {
                throw new RequestTerminationException($message);
            }
            exit();
        }
        return $message;
    }

    public function switchUser(): string
    {
        $this->security->clearAdminAuthentication();
        return $this->setAdminHeaders(true);
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
