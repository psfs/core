<?php
namespace PSFS\base\types\helpers;

use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\exception\AccessDeniedException;
use PSFS\base\exception\AdminCredentialsException;
use PSFS\base\Logger;
use PSFS\base\Security;

class SecurityHelper
{
    const RAND_SEP = '?!.:,(){}#|_-=';
    const RAND_ODD = 0;
    const RAND_EVEN = 1;

    /**
     * Method that checks the access to the restricted zone
     *
     * @param string $route
     *
     * @throws AccessDeniedException
     * @throws AdminCredentialsException
     */
    public static function checkRestrictedAccess($route)
    {
        Logger::log('Checking admin zone');
        //Chequeamos si entramos en el admin
        if (!Config::getInstance()->checkTryToSaveConfig()
            && (preg_match('/^\/(admin|setup\-admin)/i', $route) || NULL !== Config::getInstance()->get('restricted'))
        ) {
            if (null === Cache::getInstance()->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json', Cache::JSONGZ, true)) {
                throw new AdminCredentialsException();
            }
            if (!Security::getInstance()->checkAdmin()) {
                throw new AccessDeniedException();
            }
            Logger::log('Admin access granted');
        }
    }

    /**
     * @param string $ts
     * @param string $hash
     * @return string
     */
    private static function mixSecret($ts, $hash)
    {
        $token = '';
        $ct = strlen($ts);
        for ($i = 0; $i < $ct; $i++) {
            $token = substr($hash, $i, 1) .
                    substr($ts, $i, 1) .
                    $token;
        }
        return $token;
    }

    /**
     * @param string $ts
     * @param string $hash
     * @param string $token
     * @return string
     */
    private static function mixToken($ts, $hash, $token) {
        $mixedToken = '';
        $hashRest = strlen($hash) - strlen($ts);
        $charsLength = strlen(self::RAND_SEP) - 1;
        $tsLength = strlen($ts);
        $i = 0;
        $ct = ceil($hashRest / 4);
        $part = substr($hash, $tsLength + $ct * $i, $ct);
        while(false !== $part) {
            $mixedToken .= $part .
                substr(self::RAND_SEP, round(rand(0, $charsLength), 0, PHP_ROUND_HALF_DOWN), 1) .
                substr(self::RAND_SEP, round(rand(0, $charsLength), 0, PHP_ROUND_HALF_DOWN), 1);
            $part = substr($hash, $tsLength + $ct * $i, $ct);
            $i++;
        }
        return $mixedToken . $token;
    }

    /**
     * @param bool $isOdd
     * @return int
     */
    private static function getTs($isOdd = null) {
        $ts = time();
        $tsIsOdd = (bool)((int)substr($ts, -1) % 2);
        if(false === $isOdd && !$tsIsOdd) {
            $ts--;
        } elseif(true === $isOdd && !$tsIsOdd) {
            $ts--;
        }
        return $ts;
    }

    /**
     * Generate a authorized token
     * @param string $secret
     * @param string $module
     * @param boolean $isOdd
     *
     * @return string
     */
    public static function generateToken($secret, $module = 'PSFS', $isOdd = null)
    {
        $ts = self::getTs($isOdd);
        $module = strtolower($module);
        $hash = hash_hmac('sha256', $module, $secret);
        $token = self::mixSecret($ts, $hash);
        $finalToken = self::mixToken($ts, $hash, $token);
        return $finalToken;
    }

    /**
     * @param string $part
     * @return array
     */
    private static function extractTs($part) {
        $partToken = '';
        $ts = '';
        $part = strrev($part);
        for($i = 0, $ct = strlen($part); $i < $ct; $i++) {
            if($i % 2 == 0) {
                $ts .= substr($part, $i, 1);
            } else {
                $partToken .= substr($part, $i, 1);
            }
        }
        $ts = (int)$ts;
        return [$partToken, $ts];
    }

    /**
     * @param array $parts
     * @return array
     */
    private static function parseTokenParts(array $parts) {
        $token = '';
        list($partToken, $ts) = self::extractTs(array_pop($parts));
        if($ts > 0) {
            foreach($parts as $part) {
                $token .= $part;
            }
            $token = $partToken . $token;
        }
        return [$token, $ts];
    }

    /**
     * Decode token to check authorized request
     * @param string $token
     * @param boolean $force
     *
     * @return null|string
     */
    private static function decodeToken($token, $force = false)
    {
        $decoded = NULL;
        $parts = self::extractTokenParts($token);
        list($token, $ts) = self::parseTokenParts($parts);
        if ($force || time() - (integer)$ts < 300) {
            $decoded = $token;
        }
        return $decoded;
    }

    /**
     * @param string $token
     * @return array
     */
    private static function extractTokenParts($token) {
        for($i = 0, $ct = strlen(self::RAND_SEP); $i < $ct; $i++) {
            $token = str_replace(self::RAND_SEP[$i], "|", $token);
        }
        return array_unique(explode('||', $token));
    }

    /**
     * Checks if auth token is correct
     * @param string $token
     * @param string $secret
     * @param string $module
     *
     * @return bool
     */
    public static function checkToken($token, $secret, $module = 'PSFS')
    {
        if (0 === strlen($token) || 0 === strlen($secret)) {
            return false;
        }
        $module = strtolower($module);
        $decodedToken = self::decodeToken($token);
        $expectedToken = self::decodeToken(self::generateToken($secret, $module), true);

        return $decodedToken === $expectedToken;
    }

}