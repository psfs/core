<?php

namespace PSFS\base\types\helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PSFS\base\config\Config;
use PSFS\base\exception\SecurityException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Security;
use Throwable;

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
    private static array $invalidInputTelemetry = [];

    /**
     * @return array
     */
    public static function getAdminFromCookie(): array
    {
        $authCookie = Request::getInstance()->getCookie(self::generateProfileHash());
        if (empty($authCookie)) {
            return self::authTuple();
        }
        return self::extractCredentialsFromCookie((string)$authCookie);
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
        $admins = is_array($admins) ? $admins : [];
        [$candidateUser, $candidatePass] = self::resolveBasicCredentials($user, $pass, $admins);
        if (null === $candidateUser || !array_key_exists((string)$candidateUser, $admins)) {
            return self::authTuple();
        }
        return self::validateAdminHash($candidateUser, $candidatePass, $admins[$candidateUser] ?? []);
    }

    public static function checkComplexAuth(array $admins)
    {
        $authorization = Request::getInstance()->getHeader('Authorization');
        $token = self::extractAuthorizationToken($authorization, 'basic');
        if (null === $token) {
            if (!empty($authorization)) {
                self::logInvalidAuthInput('complex_invalid_authorization_header');
            }
            return self::authTuple();
        }
        $requestUserAgent = self::extractRequestUserAgent();
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        foreach ($admins as $admin => $profile) {
            [$decodedUser, $timestamp, $userAgent] = self::decodeToken($token, (string)($profile['hash'] ?? ''));
            if (!self::isValidComplexTokenPayload($decodedUser, $timestamp, $userAgent, $requestUserAgent)) {
                continue;
            }
            $expiration = \DateTime::createFromFormat(self::EXPIRATION_TIMESTAMP_FORMAT, (string)$timestamp);
            if (false === $expiration) {
                self::logInvalidAuthInput('complex_invalid_expiration_timestamp');
                continue;
            }
            if ($decodedUser === $admin && $expiration > $now) {
                return self::authTuple($admin, (string)($profile['hash'] ?? null));
            }
        }
        self::logInvalidAuthInput('complex_token_rejected');
        return self::authTuple();
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
        if (false === $secret || '' === trim((string)$secret)) {
            self::logInvalidAuthInput('token_decrypt_failed');
            return self::tokenTuple();
        }
        if (self::isJson($secret)) {
            $payload = json_decode($secret, true);
            if (is_array($payload)) {
                $user = isset($payload['sub']) ? (string)$payload['sub'] : null;
                $timestamp = isset($payload['exp']) ? (string)$payload['exp'] : null;
                $userAgent = isset($payload['ua']) ? (string)$payload['ua'] : null;
                return self::tokenTuple($user, $timestamp, $userAgent);
            }
            self::logInvalidAuthInput('token_json_payload_invalid');
            return self::tokenTuple();
        }
        if (str_contains($secret, Security::LOGGED_USER_TOKEN)) {
            [$user, $timestamp, $userAgent] = array_pad(explode(Security::LOGGED_USER_TOKEN, $secret), 3, null);
            self::logLegacyFallbackUsage('token_payload_delimited');
            return self::tokenTuple($user, $timestamp, $userAgent);
        }
        self::logInvalidAuthInput('token_payload_unknown_format');
        return self::tokenTuple();
    }

    public static function checkJwtAuth(array $admins)
    {
        $authorization = Request::getInstance()->getHeader('Authorization');
        if (empty($authorization)) {
            return self::authTuple();
        }
        $token = self::extractAuthorizationToken($authorization, 'bearer');
        if (null === $token) {
            self::logInvalidAuthInput('jwt_invalid_authorization_header');
            return self::authTuple();
        }
        $payload = self::decodeJwtPayloadWithoutVerification($token);
        if (!is_array($payload) || !array_key_exists('sub', $payload)) {
            self::logInvalidAuthInput('jwt_missing_subject');
            return self::authTuple();
        }
        $subject = (string)$payload['sub'];
        if (!array_key_exists($subject, $admins)) {
            self::logInvalidAuthInput('jwt_subject_not_found');
            return self::authTuple();
        }
        $profile = $admins[$subject] ?? [];
        $hash = (string)($profile['hash'] ?? '');
        if ('' === $hash) {
            self::logInvalidAuthInput('jwt_hash_missing');
            return self::authTuple();
        }
        try {
            $decoded = (array)JWT::decode($token, new Key($hash, Config::getParam('jwt.alg', 'HS256')));
            if ($decoded !== $payload) {
                self::logInvalidAuthInput('jwt_payload_mismatch');
                return self::authTuple();
            }
            if (time() < (int)($decoded['iat'] ?? 0)) {
                throw new SecurityException(t('Token not valid yet'));
            }
            if (time() > (int)($decoded['exp'] ?? 0)) {
                throw new SecurityException(t('Token expired'));
            }
            return self::authTuple($subject, $hash);
        } catch (Throwable $exception) {
            self::logInvalidAuthInput('jwt_decode_failure');
            Logger::log('[AuthInvalid] jwt_decode_failure:' . get_class($exception), LOG_WARNING);
            return self::authTuple();
        }
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

    /**
     * @return array<string,bool>
     */
    public static function getLegacyFallbackTelemetry(): array
    {
        return self::$legacyFallbackTelemetry;
    }

    public static function resetLegacyFallbackTelemetry(): void
    {
        self::$legacyFallbackTelemetry = [];
        self::$invalidInputTelemetry = [];
    }

    /**
     * @param string|null $authorization
     * @param string $scheme basic|bearer
     * @return string|null
     */
    private static function extractAuthorizationToken(?string $authorization, string $scheme): ?string
    {
        if (null === $authorization || '' === trim($authorization)) {
            return null;
        }
        $pattern = 'basic' === strtolower($scheme)
            ? '/^Basic\s+(.+)$/i'
            : '/^Bearer\s+(.+)$/i';
        if (preg_match($pattern, $authorization, $matches) !== 1) {
            return null;
        }
        $token = trim((string)($matches[1] ?? ''));
        return '' === $token ? null : $token;
    }

    private static function resolveBasicCredentials(?string $user, ?string $pass, array $admins): array
    {
        $request = Request::getInstance();
        $candidateUser = $user ?: $request->getServer('PHP_AUTH_USER');
        $candidatePass = $pass ?: $request->getServer('PHP_AUTH_PW');
        if (self::shouldTryCookieCredentials($candidateUser, $admins)) {
            [$cookieUser, $cookiePass] = self::getAdminFromCookie();
            if (null !== $cookieUser) {
                $candidateUser = $cookieUser;
                $candidatePass = $cookiePass;
            }
        }
        return [is_string($candidateUser) ? $candidateUser : null, is_string($candidatePass) ? $candidatePass : null];
    }

    private static function shouldTryCookieCredentials(?string $user, array $admins): bool
    {
        return null === $user || (array_key_exists($user, $admins) && empty($admins[$user]));
    }

    private static function validateAdminHash(?string $user, ?string $pass, array $profile): array
    {
        if (null === $user || null === $pass) {
            return self::authTuple();
        }
        $storedHash = (string)($profile['hash'] ?? '');
        if ('' === $storedHash) {
            return self::authTuple();
        }
        $legacyHash = sha1($user . $pass);
        if (self::isPasswordHash($storedHash)) {
            return password_verify($user . $pass, $storedHash)
                ? self::authTuple($user, $storedHash)
                : self::authTuple();
        }
        if (hash_equals($storedHash, $legacyHash)) {
            self::logLegacyFallbackUsage('basic_auth_legacy_sha1_hash');
            return self::authTuple($user, $legacyHash);
        }
        self::logInvalidAuthInput('basic_hash_mismatch');
        return self::authTuple();
    }

    private static function extractCredentialsFromCookie(string $authCookie): array
    {
        $secret = self::decrypt($authCookie, self::SESSION_TOKEN);
        if (is_string($secret) && str_contains($secret, ':')) {
            [$user, $pass] = explode(':', $secret, 2);
            return self::authTuple($user, $pass);
        }
        // Legacy fallback: old cookies/tests may still use ADMIN_ID_TOKEN.
        $legacySecret = self::decrypt($authCookie, self::ADMIN_ID_TOKEN);
        if (is_string($legacySecret) && str_contains($legacySecret, ':')) {
            self::logLegacyFallbackUsage('cookie_key_admin_token');
            [$user, $pass] = explode(':', $legacySecret, 2);
            return self::authTuple($user, $pass);
        }
        self::logInvalidAuthInput('cookie_payload_invalid');
        return self::authTuple();
    }

    private static function decodeJwtPayloadWithoutVerification(string $token): ?array
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) < 2) {
                self::logInvalidAuthInput('jwt_parts_invalid');
                return null;
            }
            $payload = json_decode(JWT::urlsafeB64Decode($parts[1]), true);
            if (!is_array($payload)) {
                self::logInvalidAuthInput('jwt_payload_invalid_json');
                return null;
            }
            return $payload;
        } catch (Throwable) {
            self::logInvalidAuthInput('jwt_payload_decode_exception');
            return null;
        }
    }

    private static function isValidComplexTokenPayload(?string $user, ?string $timestamp, ?string $userAgent, string $requestUserAgent): bool
    {
        if (null === $user || null === $timestamp) {
            return false;
        }
        if (null === $userAgent || $userAgent !== $requestUserAgent) {
            self::logInvalidAuthInput('complex_user_agent_mismatch');
            return false;
        }
        return true;
    }

    private static function extractRequestUserAgent(): string
    {
        return array_key_exists('HTTP_USER_AGENT', $_SERVER) ? (string)$_SERVER['HTTP_USER_AGENT'] : 'psfs';
    }

    private static function authTuple(?string $user = null, ?string $token = null): array
    {
        return [$user, $token];
    }

    private static function tokenTuple(?string $user = null, ?string $timestamp = null, ?string $userAgent = null): array
    {
        return [$user, $timestamp, $userAgent];
    }

    private static function logLegacyFallbackUsage(string $context): void
    {
        if (array_key_exists($context, self::$legacyFallbackTelemetry)) {
            return;
        }
        self::$legacyFallbackTelemetry[$context] = true;
        Logger::log('[LegacyFallback] ' . $context, LOG_NOTICE);
    }

    private static function logInvalidAuthInput(string $context): void
    {
        if (array_key_exists($context, self::$invalidInputTelemetry)) {
            return;
        }
        self::$invalidInputTelemetry[$context] = true;
        Logger::log('[AuthInvalid] ' . $context, LOG_WARNING);
    }
}
