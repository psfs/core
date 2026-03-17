<?php

namespace PSFS\base\types\helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PSFS\base\config\Config;
use PSFS\base\exception\SecurityException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Security;

class AuthHelper
{
    const CRYPTO_VERSION_PREFIX = 'v2:';
    const CRYPTO_CIPHER = 'aes-256-gcm';
    const CRYPTO_TAG_LENGTH = 16;
    const USER_ID_TOKEN = '12dea96fec20593566ab75692c9949596833adc9';
    const MANAGER_ID_TOKEN = 'd033e22ae348aeb5660fc2140aec35850c4da997';
    const ADMIN_ID_TOKEN = '889a3a791b3875cfae413574b53da4bb8a90d53e';
    const SESSION_TOKEN = '659d0629624c0071863f3783e19608ffd9eb97e2';
    const EXPIRATION_TIMESTAMP_FORMAT = 'YmdHis';
    private static array $legacyFallbackTelemetry = [];

    /**
     * @return array
     */
    public static function getAdminFromCookie(): array
    {
        $authCookie = Request::getInstance()->getCookie(self::generateProfileHash());
        $user = $pass = null;
        if (!empty($authCookie)) {
            $secret = self::decrypt($authCookie, self::SESSION_TOKEN);
            // Legacy fallback: old cookies/tests may still use ADMIN_ID_TOKEN.
            if (false === $secret || !str_contains((string)$secret, ':')) {
                $secret = self::decrypt($authCookie, self::ADMIN_ID_TOKEN);
                if (is_string($secret) && str_contains($secret, ':')) {
                    self::logLegacyFallbackUsage('cookie_key_admin_token');
                }
            }
            if (is_string($secret) && str_contains($secret, ':')) {
                list($user, $pass) = explode(':', $secret, 2);
            }
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
        if (!array_key_exists((string)$user, $admins)) {
            return [null, null];
        }
        $storedHash = (string)($admins[$user]['hash'] ?? '');
        if ('' === $storedHash || null === $pass) {
            return [null, null];
        }
        $legacyHash = sha1($user . $pass);
        if (self::isPasswordHash($storedHash)) {
            return password_verify($user . $pass, $storedHash) ? [$user, $storedHash] : [null, null];
        }
        if (hash_equals($storedHash, $legacyHash)) {
            self::logLegacyFallbackUsage('basic_auth_legacy_sha1_hash');
            return [$user, $legacyHash];
        }

        return [null, null];
    }

    public static function checkComplexAuth(array $admins)
    {
        $request = Request::getInstance();
        $token = $request->getHeader('Authorization');
        $user = $password = null;
        $reqUserAgent = array_key_exists('HTTP_USER_AGENT', $_SERVER) ? $_SERVER['HTTP_USER_AGENT'] : 'psfs';
        if (str_contains($token ?? '', 'Basic ')) {
            $token = str_replace('Basic ', '', $token);
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            foreach ($admins as $admin => $profile) {
                list($decrypted_user, $timestamp, $userAgent) = self::decodeToken($token, $profile['hash']);
                if (!empty($decrypted_user) && !empty($timestamp)) {
                    if (!empty($userAgent) && $userAgent === $reqUserAgent) {
                        $expiration = \DateTime::createFromFormat(self::EXPIRATION_TIMESTAMP_FORMAT, $timestamp);
                        if (false !== $expiration && $decrypted_user === $admin && $expiration > $now) {
                            $user = $admin;
                            $password = $profile['hash'];
                            break;
                        }
                    }
                }
            }
        }
        return [$user, $password];
    }

    public static function encrypt(string $data, string $key): string
    {
        $cipher = self::secureEncrypt($data, $key);
        if (false !== $cipher) {
            return self::CRYPTO_VERSION_PREFIX . $cipher;
        }
        return self::legacyEncrypt($data, $key);
    }

    public static function decrypt(string $encrypted_data, string $key): false|string
    {
        $data = false;
        if (str_starts_with($encrypted_data, self::CRYPTO_VERSION_PREFIX)) {
            $payload = substr($encrypted_data, strlen(self::CRYPTO_VERSION_PREFIX));
            $data = self::secureDecrypt($payload, $key);
            if (false !== $data) {
                return $data;
            }
        }
        $legacyData = self::legacyDecrypt($encrypted_data, $key);
        if (false !== $legacyData) {
            self::logLegacyFallbackUsage('legacy_xor_decrypt');
        }

        return $legacyData;
    }

    public static function generateToken(string $user, string $password, $userAgent = null): string
    {
        $tz = new \DateTimeZone('UTC');
        $timestamp = new \DateTime('now', $tz);
        $timestamp->modify(Config::getParam('auth.expiration', '+1 day'));
        if (null === $userAgent && array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
        }
        $data = [
            'sub' => $user,
            'exp' => $timestamp->format(self::EXPIRATION_TIMESTAMP_FORMAT),
            'ua' => $userAgent ?? 'psfs',
        ];
        return self::encrypt(json_encode($data), sha1($user . $password));
    }

    public static function decodeToken(string $token, string $password): array
    {
        $user = $timestamp = $userAgent = null;
        $secret = self::decrypt($token, $password);
        if (!empty($secret) && self::isJson($secret)) {
            $payload = json_decode($secret, true);
            if (is_array($payload)) {
                $user = $payload['sub'] ?? null;
                $timestamp = $payload['exp'] ?? null;
                $userAgent = $payload['ua'] ?? null;
            }
        } else if (!empty($secret) && str_contains($secret, Security::LOGGED_USER_TOKEN)) {
            list($user, $timestamp, $userAgent) = explode(Security::LOGGED_USER_TOKEN, $secret);
            self::logLegacyFallbackUsage('token_payload_delimited');
        }
        return [$user, $timestamp, $userAgent];
    }

    public static function checkJwtAuth(array $admins)
    {
        $user = $hash = null;
        $request = Request::getInstance();
        $authorization = $request->getHeader('Authorization');
        if (!empty($authorization)) {
            $token = null;
            if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
                $token = $matches[1];
            }
            if (null === $token) {
                return [null, null];
            }
            $parts = explode('.', $token);
            if (count($parts) > 1) {
                $payload = json_decode(JWT::urlsafeB64Decode($parts[1]), true);
                if (is_array($payload) && array_key_exists('sub', $payload)) {
                    foreach ($admins as $admin => $profile) {
                        if ($admin === $payload['sub']) {
                            try {
                                $decoded = (array)JWT::decode($token, new Key($profile['hash'], Config::getParam('jwt.alg', 'HS256')));
                                if ($decoded === $payload) {
                                    if(time() < (int)($decoded['iat'] ?? 0)) {
                                        throw new SecurityException(t('Token not valid yet'));
                                    }
                                    if(time() > (int)($decoded['exp'] ?? 0)) {
                                        throw new SecurityException(t('Token expired'));
                                    }
                                    // TODO check modules restrictions
                                    $user = $admin;
                                    $hash = $profile['hash'];
                                }
                            } catch (SecurityException|\DomainException|\UnexpectedValueException|\InvalidArgumentException $exception) {
                                Logger::log($exception->getMessage(), LOG_ERR);
                            }
                            break;
                        }
                    }
                }
            }
        }
        return [$user, $hash];
    }

    private static function isPasswordHash(string $hash): bool
    {
        $info = password_get_info($hash);
        return is_array($info) && array_key_exists('algo', $info) && 0 !== ($info['algo'] ?? 0);
    }

    private static function secureEncrypt(string $data, string $key): false|string
    {
        $ivLen = openssl_cipher_iv_length(self::CRYPTO_CIPHER);
        if (false === $ivLen || $ivLen < 1) {
            return false;
        }
        try {
            $iv = random_bytes($ivLen);
        } catch (\Exception) {
            return false;
        }
        $tag = '';
        $encrypted = openssl_encrypt(
            $data,
            self::CRYPTO_CIPHER,
            hash('sha256', $key, true),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::CRYPTO_TAG_LENGTH
        );
        if (false === $encrypted) {
            return false;
        }
        $payload = json_encode([
            'iv' => self::toBase64Url($iv),
            'tag' => self::toBase64Url($tag),
            'data' => self::toBase64Url($encrypted),
        ]);
        if (false === $payload) {
            return false;
        }

        return self::toBase64Url($payload);
    }

    private static function secureDecrypt(string $payload, string $key): false|string
    {
        $decodedPayload = self::fromBase64Url($payload);
        if (false === $decodedPayload) {
            return false;
        }
        $json = json_decode($decodedPayload, true);
        if (!is_array($json) || !isset($json['iv'], $json['tag'], $json['data'])) {
            return false;
        }
        $iv = self::fromBase64Url((string)$json['iv']);
        $tag = self::fromBase64Url((string)$json['tag']);
        $encrypted = self::fromBase64Url((string)$json['data']);
        if (false === $iv || false === $tag || false === $encrypted) {
            return false;
        }

        $decrypted = openssl_decrypt(
            $encrypted,
            self::CRYPTO_CIPHER,
            hash('sha256', $key, true),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        return false === $decrypted ? false : $decrypted;
    }

    private static function legacyEncrypt(string $data, string $key): string
    {
        $data = base64_encode($data);
        $encrypted_data = '';
        for ($i = 0, $j = 0, $iMax = strlen($data); $i < $iMax; $i++, $j++) {
            if ($j === strlen($key)) {
                $j = 0;
            }
            $encrypted_data .= $data[$i] ^ $key[$j];
        }
        return base64_encode($encrypted_data);
    }

    private static function legacyDecrypt(string $encrypted_data, string $key): false|string
    {
        $encrypted_data = base64_decode($encrypted_data);
        $data = '';
        for ($i = 0, $j = 0, $iMax = strlen((string)$encrypted_data); $i < $iMax; $i++, $j++) {
            if ($j === strlen($key)) {
                $j = 0;
            }
            $data .= $encrypted_data[$i] ^ $key[$j];
        }
        return base64_decode($data);
    }

    private static function toBase64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function fromBase64Url(string $data): false|string
    {
        if ('' === $data) {
            return false;
        }
        $encoded = strtr($data, '-_', '+/');
        $padding = strlen($encoded) % 4;
        if ($padding > 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }
        return base64_decode($encoded);
    }

    private static function isJson(string $string): bool
    {
        json_decode($string, true);
        return JSON_ERROR_NONE === json_last_error();
    }

    private static function logLegacyFallbackUsage(string $context): void
    {
        if (array_key_exists($context, self::$legacyFallbackTelemetry)) {
            return;
        }
        self::$legacyFallbackTelemetry[$context] = true;
        Logger::log('[LegacyFallback] ' . $context, LOG_NOTICE);
    }
}
