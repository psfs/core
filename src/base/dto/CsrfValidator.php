<?php

namespace PSFS\base\dto;

use PSFS\base\config\Config;
use PSFS\base\Security;

class CsrfValidator
{
    private const SESSION_TOKEN_KEY = '__PSFS_CSRF_FORM_TOKENS__';
    private const TOKEN_REGEX = '/^[a-f0-9]{64}$/';

    /**
     * @return array{token:string,key:string}
     */
    public static function issueToken(string $formKey): array
    {
        $storage = self::purgeExpiredStorage(self::getStorage());
        $token = self::generateToken();
        $key = self::generateToken();
        $storage[$key] = [
            'token' => $token,
            'expires_at' => time() + self::csrfExpiration(),
            'form' => $formKey,
        ];
        self::setStorage($storage);

        return [
            'token' => $token,
            'key' => $key,
        ];
    }

    public static function validateSubmission(string $token, string $tokenKey, string $formKey): bool
    {
        if (preg_match(self::TOKEN_REGEX, $token) !== 1 || preg_match(self::TOKEN_REGEX, $tokenKey) !== 1) {
            self::purgeInvalidTokenEntry($tokenKey);
            return false;
        }

        $storage = self::purgeExpiredStorage(self::getStorage());
        $entry = $storage[$tokenKey] ?? null;
        if (!is_array($entry)) {
            self::setStorage($storage);
            return false;
        }
        $storedToken = (string)($entry['token'] ?? '');
        $expiresAt = (int)($entry['expires_at'] ?? 0);
        $storedForm = (string)($entry['form'] ?? '');
        $isValid = preg_match(self::TOKEN_REGEX, $storedToken) === 1
            && $expiresAt >= time()
            && $storedForm === $formKey
            && hash_equals($storedToken, $token);

        unset($storage[$tokenKey]);
        self::setStorage($storage);

        return $isValid;
    }

    private static function purgeInvalidTokenEntry(string $tokenKey): void
    {
        if (preg_match(self::TOKEN_REGEX, $tokenKey) !== 1) {
            return;
        }
        $storage = self::getStorage();
        if (!array_key_exists($tokenKey, $storage)) {
            return;
        }
        unset($storage[$tokenKey]);
        self::setStorage($storage);
    }

    /**
     * @return array<string, array{token:string,expires_at:int,form:string}>
     */
    private static function getStorage(): array
    {
        $storage = Security::getInstance()->getSessionKey(self::SESSION_TOKEN_KEY);
        return is_array($storage) ? $storage : [];
    }

    /**
     * @param array<string, array{token:string,expires_at:int,form:string}> $storage
     */
    private static function setStorage(array $storage): void
    {
        $security = Security::getInstance();
        $security->setSessionKey(self::SESSION_TOKEN_KEY, $storage);
        $security->updateSession();
    }

    /**
     * @param array<string, array{token:string,expires_at:int,form:string}> $storage
     * @return array<string, array{token:string,expires_at:int,form:string}>
     */
    private static function purgeExpiredStorage(array $storage): array
    {
        $now = time();
        foreach ($storage as $key => $entry) {
            if ((int)($entry['expires_at'] ?? 0) < $now) {
                unset($storage[$key]);
            }
        }

        return $storage;
    }

    private static function generateToken(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Exception) {
            return hash('sha256', uniqid('csrf', true) . ':' . microtime(true));
        }
    }

    private static function csrfExpiration(): int
    {
        $expiration = (int)Config::getParam('csrf.expiration', 1800);
        return max(60, $expiration);
    }
}

