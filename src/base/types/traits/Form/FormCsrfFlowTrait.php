<?php

namespace PSFS\base\types\traits\Form;

trait FormCsrfFlowTrait
{
    /**
     * @return array{0:string,1:string}
     */
    private function resolveCsrfFieldNames(): array
    {
        $formName = (string)$this->getName();
        return [$formName . '_token', $formName . $this->csrfTokenKeyFieldSuffix()];
    }

    /**
     * @return array{token:string,expires_at:int,form:string}
     */
    private function buildCsrfStorageEntry(string $token, string $formKey): array
    {
        return [
            'token' => $token,
            'expires_at' => time() + $this->getCsrfExpiration(),
            'form' => $formKey,
        ];
    }

    private function isStoredTokenEntryValid(mixed $entry, string $formKey): bool
    {
        if (!is_array($entry)) {
            return false;
        }
        $storedToken = (string)($entry['token'] ?? '');
        $expiresAt = (int)($entry['expires_at'] ?? 0);
        $storedForm = (string)($entry['form'] ?? '');
        return $this->isValidCsrfTokenValue($storedToken)
            && $expiresAt >= time()
            && $storedForm === $formKey;
    }
}

