<?php

namespace PSFS\base\admin;

/**
 * Serializes legacy form metadata for native admin consumers without exposing credentials or CSRF material.
 */
class AdminFormSchemaFactory
{
    private const SECRET_FIELD_PATTERN = '/(?:secret|password|token|hash)/i';

    /**
     * @return array{name:mixed,title:mixed,fields:array<string,array<string,mixed>>}
     */
    public function fromForm(object $form, bool $includeSensitiveValues = false): array
    {
        $fields = [];

        foreach ($form->getFields() as $key => $field) {
            if (!is_array($field) || $this->isSecurityField((string) $key, $field)) {
                continue;
            }

            $fields[$key] = $this->normalizeField((string) $key, $field, $includeSensitiveValues);
        }

        return [
            'name' => $form->getName(),
            'title' => $form->getTitle(),
            'fields' => $fields,
        ];
    }

    /**
     * @param array<string,mixed> $field
     * @return array<string,mixed>
     */
    private function normalizeField(string $key, array $field, bool $includeSensitiveValues): array
    {
        $isSecret = $this->isSecretField($key, $field);
        $normalized = [
            'name' => $key,
            'label' => $field['label'] ?? $key,
            'type' => $field['type'] ?? 'text',
            'preserveIfEmpty' => $isSecret,
            'required' => $field['required'] ?? true,
            'options' => $field['data'] ?? [],
            'help' => $field['help'] ?? null,
        ];

        if (!$isSecret || $includeSensitiveValues) {
            $normalized['value'] = $field['value'] ?? null;
        }

        foreach (['pattern', 'min', 'max', 'minlength', 'maxlength'] as $rule) {
            if (array_key_exists($rule, $field)) {
                $normalized[$rule] = $field[$rule];
            }
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $field
     */
    private function isSecurityField(string $key, array $field): bool
    {
        return preg_match('/(?:^|_)(?:csrf|session|token)(?:_|$)|^__/i', $key) === 1
            || preg_match('/(?:^|_)(?:csrf|session|token)(?:_|$)|^__/i', (string) ($field['name'] ?? '')) === 1;
    }

    /**
     * @param array<string,mixed> $field
     */
    private function isSecretField(string $key, array $field): bool
    {
        return preg_match(self::SECRET_FIELD_PATTERN, $key) === 1
            || preg_match(self::SECRET_FIELD_PATTERN, (string) ($field['name'] ?? '')) === 1;
    }
}
