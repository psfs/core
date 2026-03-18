<?php

namespace PSFS\base\types\traits\Form;

use PSFS\base\config\Config;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\types\Form;

/**
 * @package PSFS\base\types\traits\Form
 */
trait FormSecurityTrait
{
    private const CSRF_SESSION_TOKEN_KEY = '__PSFS_CSRF_FORM_TOKENS__';
    private const CSRF_DEFAULT_EXPIRATION_SECONDS = 1800;
    private const CSRF_TOKEN_KEY_FIELD_SUFFIX = '_token_key';
    private const CSRF_TOKEN_REGEX = '/^[a-f0-9]{64}$/';

    /**
     * @var
     */
    protected $crfs;

    /**
     * @return Form
     */
    private function genCrfsToken()
    {
        if (empty($this->fields)) {
            return $this;
        }

        $tokenField = $this->getName() . '_token';
        $tokenKeyField = $this->getName() . self::CSRF_TOKEN_KEY_FIELD_SUFFIX;
        $formKey = $this->getCsrfFormKey();
        $storage = $this->purgeExpiredCsrfStorage($this->getCsrfStorage());

        $submittedToken = $this->extractSubmittedToken($tokenField);
        $submittedTokenKey = $this->extractSubmittedToken($tokenKeyField);
        $storedToken = '';
        $storedValid = false;
        if ($this->isValidCsrfTokenKey($submittedTokenKey) && array_key_exists($submittedTokenKey, $storage)) {
            $entry = $storage[$submittedTokenKey];
            $storedToken = (string)($entry['token'] ?? '');
            $expiresAt = (int)($entry['expires_at'] ?? 0);
            $storedForm = (string)($entry['form'] ?? '');
            $storedValid = (
                $this->isValidCsrfTokenValue($storedToken)
                && $expiresAt >= time()
                && $storedForm === $formKey
            );
        }

        if ($this->isValidCsrfTokenValue($submittedToken) && $storedValid && hash_equals(
                $storedToken,
                $submittedToken
            )) {
            // Keep the same token during POST build to preserve legacy form flow: build() -> hydrate() -> isValid().
            $this->crfs = $storedToken;
            $tokenKey = $submittedTokenKey;
        } else {
            $this->crfs = $this->generateRandomToken();
            $tokenKey = $this->generateTokenKey();
            $storage[$tokenKey] = [
                'token' => $this->crfs,
                'expires_at' => time() + $this->getCsrfExpiration(),
                'form' => $formKey,
            ];
            $this->setCsrfStorage($storage);
        }

        $this->add($tokenField, array(
            'type' => 'hidden',
            'value' => $this->crfs,
        ));
        $this->add($tokenKeyField, array(
            'type' => 'hidden',
            'value' => $tokenKey,
        ));

        return $this;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        $valid = true;
        $tokenField = $this->getName() . '_token';
        $tokenKeyField = $this->getName() . self::CSRF_TOKEN_KEY_FIELD_SUFFIX;
        // Check crfs token
        if (!$this->existsFormToken($tokenField)) {
            $this->errors[$tokenField] = t('Invalid form');
            $this->fields[$tokenField]['error'] = $this->errors[$tokenField];
            $valid = false;
        }
        // Validate all the fields
        if ($valid && !empty($this->fields)) {
            foreach ($this->fields as $key => &$field) {
                if ($key === $tokenField || $key === $tokenKeyField || $key === self::SEPARATOR) {
                    continue;
                }
                list($field, $valid) = $this->checkFieldValidation($field, $key);
            }
        }
        return $valid;
    }

    /**
     * @param string $tokenField
     * @return bool
     */
    protected function existsFormToken($tokenField)
    {
        if ($this->method !== 'POST') {
            return true;
        }
        $tokenKeyField = $this->getName() . self::CSRF_TOKEN_KEY_FIELD_SUFFIX;
        if (null === $tokenField
            || !array_key_exists($tokenField, $this->fields)
            || !array_key_exists($tokenKeyField, $this->fields)
        ) {
            return false;
        }

        if (!array_key_exists('value', $this->fields[$tokenField])) {
            return false;
        }
        if (!array_key_exists('value', $this->fields[$tokenKeyField])) {
            return false;
        }

        $submittedToken = (string)$this->fields[$tokenField]['value'];
        if (!$this->isValidCsrfTokenValue($submittedToken)) {
            $submittedTokenKey = (string)$this->fields[$tokenKeyField]['value'];
            $storage = $this->getCsrfStorage();
            if ($this->isValidCsrfTokenKey($submittedTokenKey) && array_key_exists($submittedTokenKey, $storage)) {
                unset($storage[$submittedTokenKey]);
                $this->setCsrfStorage($storage);
            }
            return false;
        }
        $submittedTokenKey = (string)$this->fields[$tokenKeyField]['value'];
        if (!$this->isValidCsrfTokenKey($submittedTokenKey)) {
            return false;
        }

        $formKey = $this->getCsrfFormKey();
        $storage = $this->purgeExpiredCsrfStorage($this->getCsrfStorage());
        if (!array_key_exists($submittedTokenKey, $storage)) {
            $this->setCsrfStorage($storage);
            return false;
        }
        $entry = $storage[$submittedTokenKey];
        $sessionToken = (string)($entry['token'] ?? '');
        $expiresAt = (int)($entry['expires_at'] ?? 0);
        $storedForm = (string)($entry['form'] ?? '');

        if (!$this->isValidCsrfTokenValue($sessionToken) || $expiresAt < time() || $storedForm !== $formKey) {
            unset($storage[$submittedTokenKey]);
            $this->setCsrfStorage($storage);
            return false;
        }

        $valid = hash_equals($sessionToken, $submittedToken);
        unset($storage[$submittedTokenKey]);
        $this->setCsrfStorage($storage);
        $this->crfs = $sessionToken;

        return $valid;
    }

    /**
     * @return string
     */
    private function getCsrfFormKey(): string
    {
        return $this->getName();
    }

    /**
     * @return int
     */
    private function getCsrfExpiration(): int
    {
        $expiration = (int)Config::getParam('csrf.expiration', self::CSRF_DEFAULT_EXPIRATION_SECONDS);
        return max(60, $expiration);
    }

    /**
     * @return string
     */
    private function generateRandomToken(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Exception) {
            return hash('sha256', uniqid('csrf', true) . microtime(true));
        }
    }

    /**
     * @return string
     */
    private function generateTokenKey(): string
    {
        return hash('sha256', microtime(true) . ':' . $this->generateRandomToken() . ':' . mt_rand());
    }

    /**
     * @return array
     */
    private function getCsrfStorage(): array
    {
        $storage = Security::getInstance()->getSessionKey(self::CSRF_SESSION_TOKEN_KEY);
        return is_array($storage) ? $storage : [];
    }

    /**
     * @param array $storage
     * @return void
     */
    private function setCsrfStorage(array $storage): void
    {
        $security = Security::getInstance();
        $security->setSessionKey(self::CSRF_SESSION_TOKEN_KEY, $storage);
        // Persist CSRF token state immediately to avoid losing it in flows that don't reach normal shutdown hooks.
        $security->updateSession();
    }

    /**
     * @param array $storage
     * @return array
     */
    private function purgeExpiredCsrfStorage(array $storage): array
    {
        if (empty($storage)) {
            return [];
        }
        $now = time();
        foreach ($storage as $key => $entry) {
            $expiresAt = (int)($entry['expires_at'] ?? 0);
            if ($expiresAt < $now) {
                unset($storage[$key]);
            }
        }
        return $storage;
    }

    /**
     * @param string $tokenField
     * @return string
     */
    private function extractSubmittedToken(string $tokenField): string
    {
        $requestData = Request::getInstance()->getData();
        $formName = $this->getName();
        if (!array_key_exists($formName, $requestData) || !is_array($requestData[$formName])) {
            return '';
        }
        return (string)($requestData[$formName][$tokenField] ?? '');
    }

    private function isValidCsrfTokenValue(string $token): bool
    {
        return preg_match(self::CSRF_TOKEN_REGEX, $token) === 1;
    }

    private function isValidCsrfTokenKey(string $tokenKey): bool
    {
        return preg_match(self::CSRF_TOKEN_REGEX, $tokenKey) === 1;
    }

}
