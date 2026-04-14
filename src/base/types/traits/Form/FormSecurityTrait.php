<?php

namespace PSFS\base\types\traits\Form;

use PSFS\base\config\Config;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\types\Form;
use PSFS\base\types\interfaces\FormType;

/**
 * @package PSFS\base\types\traits\Form
 */
trait FormSecurityTrait
{
    use FormCsrfFlowTrait;
    /**
     * Contract: provided by FormType implementations.
     */
    abstract public function getName();

    /**
     * Contract: provided by FormDataTrait.
     *
     * @param mixed $name
     * @param array $value
     * @return mixed
     */
    abstract public function add($name, array $value = []);

    /**
     * Contract: provided by FormValidatorTrait.
     *
     * @param mixed $field
     * @param mixed $key
     * @return array
     */
    abstract protected function checkFieldValidation($field, $key);

    /**
     * Contract: provided by FormValidatorTrait.
     *
     * @param mixed $field
     * @param mixed $error
     * @return mixed
     */
    abstract public function setError($field, $error = 'Validation error');

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

        [$tokenField, $tokenKeyField] = $this->resolveCsrfFieldNames();
        $formKey = $this->getCsrfFormKey();
        $storage = $this->purgeExpiredCsrfStorage($this->getCsrfStorage());

        $submittedToken = $this->extractSubmittedToken($tokenField);
        $submittedTokenKey = $this->extractSubmittedToken($tokenKeyField);
        $entry = ($this->isValidCsrfTokenKey($submittedTokenKey) && array_key_exists($submittedTokenKey, $storage))
            ? $storage[$submittedTokenKey]
            : null;
        $storedToken = is_array($entry) ? (string)($entry['token'] ?? '') : '';
        if ($this->isValidCsrfTokenValue($submittedToken)
            && $this->isStoredTokenEntryValid($entry, $formKey)
            && hash_equals($storedToken, $submittedToken)
        ) {
            // Keep the same token during POST build to preserve legacy form flow: build() -> hydrate() -> isValid().
            $this->crfs = $storedToken;
            $tokenKey = $submittedTokenKey;
        } else {
            $this->crfs = $this->generateRandomToken();
            $tokenKey = $this->generateTokenKey();
            $storage[$tokenKey] = $this->buildCsrfStorageEntry($this->crfs, $formKey);
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
        [$tokenField, $tokenKeyField] = $this->resolveCsrfFieldNames();
        // Check crfs token
        if (!$this->existsFormToken($tokenField)) {
            $this->setError($tokenField, t('Invalid form'));
            $valid = false;
        }
        // Validate all the fields
        if ($valid && !empty($this->fields)) {
            foreach ($this->fields as $key => &$field) {
                if ($key === $tokenField || $key === $tokenKeyField || $key === FormType::SEPARATOR) {
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
        if (!$this->isPostMethod()) {
            return true;
        }
        [, $tokenKeyField] = $this->resolveCsrfFieldNames();
        if (!$this->hasTokenFields($tokenField, $tokenKeyField)) {
            return false;
        }

        [$submittedToken, $submittedTokenKey] = $this->readSubmittedTokenPair($tokenField, $tokenKeyField);
        if ($submittedToken === null || $submittedTokenKey === null) {
            return false;
        }

        if (!$this->isSubmittedTokenPairValid($submittedToken, $submittedTokenKey)) {
            return false;
        }

        $formKey = $this->getCsrfFormKey();
        return $this->validateStoredTokenSubmission($submittedToken, $submittedTokenKey, $formKey);
    }

    private function isPostMethod(): bool
    {
        return $this->method === 'POST';
    }

    private function isSubmittedTokenPairValid(string $submittedToken, string $submittedTokenKey): bool
    {
        if (!$this->isValidCsrfTokenValue($submittedToken)) {
            $this->purgeSubmittedTokenIfNeeded($submittedTokenKey);
            return false;
        }

        return $this->isValidCsrfTokenKey($submittedTokenKey);
    }

    private function validateStoredTokenSubmission(string $submittedToken, string $submittedTokenKey, string $formKey): bool
    {
        $storage = $this->purgeExpiredCsrfStorage($this->getCsrfStorage());
        $entry = $storage[$submittedTokenKey] ?? null;
        if (!is_array($entry)) {
            $this->setCsrfStorage($storage);
            return false;
        }

        if (!$this->isStoredTokenEntryValid($entry, $formKey)) {
            unset($storage[$submittedTokenKey]);
            $this->setCsrfStorage($storage);
            return false;
        }

        $sessionToken = (string)($entry['token'] ?? '');
        $valid = hash_equals($sessionToken, $submittedToken);
        unset($storage[$submittedTokenKey]);
        $this->setCsrfStorage($storage);
        $this->crfs = $sessionToken;

        return $valid;
    }

    private function hasTokenFields($tokenField, string $tokenKeyField): bool
    {
        if ($tokenField === null) {
            return false;
        }

        return array_key_exists($tokenField, $this->fields)
            && array_key_exists($tokenKeyField, $this->fields);
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function readSubmittedTokenPair(string $tokenField, string $tokenKeyField): array
    {
        if (!array_key_exists('value', $this->fields[$tokenField])) {
            return [null, null];
        }
        if (!array_key_exists('value', $this->fields[$tokenKeyField])) {
            return [null, null];
        }

        return [
            (string)$this->fields[$tokenField]['value'],
            (string)$this->fields[$tokenKeyField]['value'],
        ];
    }

    private function purgeSubmittedTokenIfNeeded(string $submittedTokenKey): void
    {
        $storage = $this->getCsrfStorage();
        if (!$this->isValidCsrfTokenKey($submittedTokenKey) || !array_key_exists($submittedTokenKey, $storage)) {
            return;
        }

        unset($storage[$submittedTokenKey]);
        $this->setCsrfStorage($storage);
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
        $expiration = (int)Config::getParam('csrf.expiration', $this->csrfDefaultExpirationSeconds());
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
        $storage = Security::getInstance()->getSessionKey($this->csrfSessionTokenKey());
        return is_array($storage) ? $storage : [];
    }

    /**
     * @param array $storage
     * @return void
     */
    private function setCsrfStorage(array $storage): void
    {
        $security = Security::getInstance();
        $security->setSessionKey($this->csrfSessionTokenKey(), $storage);
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
        return preg_match($this->csrfTokenRegex(), $token) === 1;
    }

    private function isValidCsrfTokenKey(string $tokenKey): bool
    {
        return preg_match($this->csrfTokenRegex(), $tokenKey) === 1;
    }

    private function csrfSessionTokenKey(): string
    {
        return '__PSFS_CSRF_FORM_TOKENS__';
    }

    private function csrfDefaultExpirationSeconds(): int
    {
        return 1800;
    }

    private function csrfTokenKeyFieldSuffix(): string
    {
        return '_token_key';
    }

    private function csrfTokenRegex(): string
    {
        return '/^[a-f0-9]{64}$/';
    }

}
