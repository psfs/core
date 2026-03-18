<?php

namespace PSFS\base\types\helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PSFS\base\config\Config;
use PSFS\base\exception\SecurityException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\types\traits\Helper\AuthCryptoTrait;
use PSFS\base\types\traits\Helper\AuthFlowTrait;
use PSFS\base\types\traits\Helper\AuthTelemetryTrait;
use Throwable;

class AuthHelper
{
    use AuthCryptoTrait;
    use AuthFlowTrait;
    use AuthTelemetryTrait;

    const CRYPTO_VERSION_PREFIX = 'v2:';
    const CRYPTO_CIPHER = 'aes-256-gcm';
    const CRYPTO_TAG_LENGTH = 16;
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
            // Versioned payloads should not be reinterpreted by legacy XOR decrypt,
            // otherwise invalid random plaintext can bypass fallback detection.
            return false;
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
}
