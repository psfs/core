<?php

namespace PSFS\base\types\traits\Form;

use PSFS\base\config\Config;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\types\Form;

/**
 * Trait FormSecurityTrait
 * @package PSFS\base\types\traits\Form
 */
trait FormSecurityTrait
{
    private const CSRF_SESSION_TOKEN_KEY = '__PSFS_CSRF_FORM_TOKENS__';
    private const CSRF_DEFAULT_EXPIRATION_SECONDS = 1800;

    /**
     * @var
     */
    protected $crfs;

    /**
     * Método que genera un CRFS token para los formularios
     * @return Form
     */
    private function genCrfsToken()
    {
        if (empty($this->fields)) {
            return $this;
        }

        $tokenField = $this->getName() . '_token';
        $formKey = $this->getCsrfFormKey();
        $storage = $this->getCsrfStorage();

        $submittedToken = $this->extractSubmittedToken($tokenField);
        $storedToken = (string)($storage[$formKey]['token'] ?? '');
        $expiresAt = (int)($storage[$formKey]['expires_at'] ?? 0);
        $storedValid = ($storedToken !== '' && $expiresAt >= time());

        if ($submittedToken !== '' && $storedValid && hash_equals($storedToken, $submittedToken)) {
            // Keep the same token during POST build to preserve legacy form flow: build() -> hydrate() -> isValid().
            $this->crfs = $storedToken;
        } else {
            $this->crfs = $this->generateRandomToken();
            $storage[$formKey] = [
                'token' => $this->crfs,
                'expires_at' => time() + $this->getCsrfExpiration(),
            ];
            $this->setCsrfStorage($storage);
        }

        $this->add($tokenField, array(
            'type' => 'hidden',
            'value' => $this->crfs,
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
        // Check crfs token
        if (!$this->existsFormToken($tokenField)) {
            $this->errors[$tokenField] = t('Formulario no válido');
            $this->fields[$tokenField]['error'] = $this->errors[$tokenField];
            $valid = false;
        }
        // Validate all the fields
        if ($valid && count($this->fields) > 0) {
            foreach ($this->fields as $key => &$field) {
                if ($key === $tokenField || $key === self::SEPARATOR) {
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
        if (null === $tokenField
            || !array_key_exists($tokenField, $this->fields)
        ) {
            return false;
        }

        if (!array_key_exists('value', $this->fields[$tokenField])) {
            return false;
        }

        $submittedToken = (string)$this->fields[$tokenField]['value'];
        if ('' === $submittedToken) {
            return false;
        }

        $formKey = $this->getCsrfFormKey();
        $storage = $this->getCsrfStorage();
        $sessionToken = (string)($storage[$formKey]['token'] ?? '');
        $expiresAt = (int)($storage[$formKey]['expires_at'] ?? 0);

        if ('' === $sessionToken || $expiresAt < time()) {
            unset($storage[$formKey]);
            $this->setCsrfStorage($storage);
            return false;
        }

        $valid = hash_equals($sessionToken, $submittedToken);
        unset($storage[$formKey]);
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
        Security::getInstance()->setSessionKey(self::CSRF_SESSION_TOKEN_KEY, $storage);
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

}
