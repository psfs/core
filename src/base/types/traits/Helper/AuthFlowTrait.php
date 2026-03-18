<?php

namespace PSFS\base\types\traits\Helper;

use Firebase\JWT\JWT;
use PSFS\base\Request;
use Throwable;

trait AuthFlowTrait
{
    /**
     * @param string|null $authorization
     * @param string $scheme
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
}
