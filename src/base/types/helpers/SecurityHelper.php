<?php
namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\exception\AccessDeniedException;
use PSFS\base\Logger;
use PSFS\base\Security;

class SecurityHelper {
    /**
     * Method that checks the access to the restricted zone
     *
     * @param string $route
     *
     * @throws AccessDeniedException
     */
    public static function checkRestrictedAccess($route)
    {
        Logger::log('Checking admin zone');
        //Chequeamos si entramos en el admin
        if (!Config::getInstance()->checkTryToSaveConfig()
            && (preg_match('/^\/(admin|setup\-admin)/i', $route) || NULL !== Config::getInstance()->get('restricted'))
        ) {
            if (!Security::getInstance()->checkAdmin()) {
                throw new AccessDeniedException();
            }
            Logger::log('Admin access granted');
        }
    }

}