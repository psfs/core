<?php

namespace PSFS\base\types\helpers;

use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\exception\AccessDeniedException;
use PSFS\base\exception\AdminCredentialsException;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\traits\TestTrait;

class SecurityHelper
{
    use TestTrait;

    const RAND_SEP = '?!.:,()[]+{}#|_-=';
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
        Inspector::stats('[SecurityHelper] Checking admin zone', Inspector::SCOPE_DEBUG);
        //Chequeamos si entramos en el admin
        if (!Config::getInstance()->checkTryToSaveConfig()
            && (preg_match('/^\/(admin|setup\-admin)/i', $route) || Config::getParam('restricted', false))
        ) {
            if (!self::isTest() &&
                null === Cache::getInstance()->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json', Cache::JSONGZ, true)) {
                throw new AdminCredentialsException();
            }
            if (!Security::getInstance()->checkAdmin()) {
                throw new AccessDeniedException();
            }
            Inspector::stats('[SecurityHelper] Admin access granted', Inspector::SCOPE_DEBUG);
        }
    }

    /**
     * @param string $timestamp
     * @param string $hash
     * @return string
     */
    private static function mixSecret($timestamp, $hash)
    {
        $token = '';
        $length = strlen($timestamp);
        for ($i = 0; $i < $length; $i++) {
            $token = substr($hash, $i, 1) .
                substr($timestamp, $i, 1) .
                $token;
        }
        return $token;
    }

    /**
     * @param string $timestamp
     * @param string $hash
     * @param string $token
     * @return string
     */
    private static function mixToken($timestamp, $hash, $token)
    {
        $mixedToken = '';
        $hashRest = strlen($hash) - strlen($timestamp);
        $charsLength = strlen(self::RAND_SEP) - 1;
        $tsLength = strlen($timestamp);
        $i = 0;
        $partCount = ceil($hashRest / 4);
        $part = substr($hash, $tsLength + $partCount * $i, $partCount);
        while (false !== $part) {
            $mixedToken .= $part .
                substr(self::RAND_SEP, round(rand(0, $charsLength), 0, PHP_ROUND_HALF_DOWN), 1) .
                substr(self::RAND_SEP, round(rand(0, $charsLength), 0, PHP_ROUND_HALF_DOWN), 1);
            $part = substr($hash, $tsLength + $partCount * $i, $partCount);
            $i++;
        }
        return $mixedToken . $token;
    }

    /**
     * @param bool $isOdd
     * @return int
     */
    private static function getTs($isOdd = null)
    {
        $timestamp = time();
        $tsIsOdd = (bool)((int)substr($timestamp, -1) % 2);
        if (false === $isOdd && !$tsIsOdd) {
            $timestamp--;
        } elseif (true === $isOdd && !$tsIsOdd) {
            $timestamp--;
        }
        return $timestamp;
    }

    /**
     * Generate a authorized token
     * @param string $secret
     * @param string $module
     * @param boolean $isOdd
     *
     * @return string
     */
    public static function generateToken($secret, $module = Router::PSFS_BASE_NAMESPACE, $isOdd = null)
    {
        $timestamp = self::getTs($isOdd);
        $module = strtolower($module);
        $hash = hash_hmac('sha256', $module, $secret);
        $token = self::mixSecret($timestamp, $hash);
        $finalToken = self::mixToken($timestamp, $hash, $token);
        return $finalToken;
    }

    /**
     * @param string $part
     * @return array
     */
    private static function extractTs($part)
    {
        $partToken = '';
        $timestamp = '';
        $part = strrev($part);
        for ($i = 0, $ct = strlen($part); $i < $ct; $i++) {
            if ($i % 2 == 0) {
                $timestamp .= substr($part, $i, 1);
            } else {
                $partToken .= substr($part, $i, 1);
            }
        }
        $timestamp = (int)$timestamp;
        return [$partToken, $timestamp];
    }

    /**
     * @param array $parts
     * @return array
     */
    private static function parseTokenParts(array $parts)
    {
        $token = '';
        list($partToken, $timestamp) = self::extractTs(array_pop($parts));
        if ($timestamp > 0) {
            foreach ($parts as $part) {
                $token .= $part;
            }
            $token = $partToken . $token;
        }
        return [$token, $timestamp];
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
        list($token, $timestamp) = self::parseTokenParts($parts);
        if ($force || time() - (integer)$timestamp < 300) {
            $decoded = $token;
        }
        return $decoded;
    }

    /**
     * @param string $token
     * @return array
     */
    private static function extractTokenParts($token)
    {
        for ($i = 0, $ct = strlen(self::RAND_SEP); $i < $ct; $i++) {
            $token = str_replace(substr(self::RAND_SEP, $i, 1), "|", $token);
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
    public static function checkToken($token, $secret, $module = Router::PSFS_BASE_NAMESPACE)
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
