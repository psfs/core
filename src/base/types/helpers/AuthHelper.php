<?php

namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\Request;
use PSFS\base\Security;

class AuthHelper
{
    const USER_ID_TOKEN = '12dea96fec20593566ab75692c9949596833adc9';
    const MANAGER_ID_TOKEN = 'd033e22ae348aeb5660fc2140aec35850c4da997';
    const ADMIN_ID_TOKEN = '889a3a791b3875cfae413574b53da4bb8a90d53e';
    const SESSION_TOKEN = '659d0629624c0071863f3783e19608ffd9eb97e2';
    const EXPIRATION_TIMESTAMP_FORMAT = 'YmdHis';

    /**
     * @return array
     */
    public static function getAdminFromCookie(): array
    {
        $authCookie = Request::getInstance()->getCookie(self::generateProfileHash());
        $user = $pass = null;
        if (!empty($authCookie)) {
            list($user, $pass) = explode(':', self::decrypt($authCookie, self::SESSION_TOKEN));
        }

        return [$user, $pass];
    }

    /**
     * @param string $role
     * @return string
     */
    public static function generateProfileHash(?string $role = AuthHelper::SESSION_TOKEN): string
    {
        return substr($role, 0, 8);
    }


    public static function checkBasicAuth(?string $user = null, ?string $pass = null, ?array $admins = []): array
    {
        $request = Request::getInstance();
        // Extract credentials from HTTP headers
        $user = $user ?: $request->getServer('PHP_AUTH_USER');
        $pass = $pass ?: $request->getServer('PHP_AUTH_PW');
        if (NULL === $user || (array_key_exists($user, $admins) && empty($admins[$user]))) {
            list($user, $pass) = self::getAdminFromCookie();
        }
        return array_key_exists($user, $admins) ? [$user, sha1($user . $pass)] : [null, null];
    }

    public static function checkComplexAuth(array $admins)
    {
        $request = Request::getInstance();
        $token = $request->getHeader('Authorization');
        $user = $password = null;
        if (str_contains($token ?? '', 'Basic ')) {
            $token = str_replace('Basic ', '', $token);
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            foreach ($admins as $admin => $profile) {
                list($decrypted_user, $timestamp) = self::decodeToken($token, $profile['hash']);
                if (!empty($decrypted_user) && !empty($timestamp)) {
                    $expiration = \DateTime::createFromFormat(self::EXPIRATION_TIMESTAMP_FORMAT, $timestamp);
                    if (false !== $expiration && $decrypted_user === $admin && $expiration > $now) {
                        $user = $admin;
                        $password = $profile['hash'];
                        break;
                    }
                }
            }
        }
        return [$user, $password];
    }

    public static function encrypt(string $data, string $key): string
    {
        $data = base64_encode($data);
        $encrypted_data = '';
        for ($i = 0, $j = 0; $i < strlen($data); $i++, $j++) {
            if ($j === strlen($key)) {
                $j = 0;
            }
            $encrypted_data .= $data[$i] ^ $key[$j]; // XOR entre caracteres
        }
        return base64_encode($encrypted_data);
    }

    public static function decrypt(string $encrypted_data, string $key): false|string
    {
        $encrypted_data = base64_decode($encrypted_data);
        $data = '';
        for ($i = 0, $j = 0; $i < strlen($encrypted_data); $i++, $j++) {
            if ($j === strlen($key)) {
                $j = 0;
            }
            $data .= $encrypted_data[$i] ^ $key[$j]; // XOR entre caracteres
        }
        return base64_decode($data);
    }

    public static function generateToken(string $user, string $password): string
    {
        $tz = new \DateTimeZone('UTC');
        $timestamp = new \DateTime('now', $tz);
        $timestamp->modify(Config::getParam('auth.expiration', '+1 day'));
        $data = $user . Security::LOGGED_USER_TOKEN . $timestamp->format(self::EXPIRATION_TIMESTAMP_FORMAT);
        return self::encrypt($data, sha1($user . $password));
    }

    public static function decodeToken(string $token, string $password): array
    {
        $user = $timestamp = null;
        $secret = self::decrypt($token, $password);
        if(!empty($secret) && str_contains($secret, Security::LOGGED_USER_TOKEN)) {
            list($user, $timestamp) = explode(Security::LOGGED_USER_TOKEN, $secret);
        }
        return [$user, $timestamp];
    }
}