<?php

namespace PSFS\base\admin;

use InvalidArgumentException;

/**
 * Adapts the stable legacy ManagerTrait::getForm JsonResponse payload into
 * the Admin v2 form contract.  This is intentionally a pure adapter: it does
 * not make the generated manager endpoint a v2 mutation endpoint.
 */
final class AdminManagerFormSchemaAdapter
{
    /**
     * @param array<string,mixed> $response JsonResponse serialized by ManagerTrait::getForm().
     * @return array{name:string,title:string,fields:array<string,array<string,mixed>>}
     */
    public function fromManagerResponse(array $response, string $name, string $title): array
    {
        if (($response['success'] ?? false) !== true || !is_array($response['data'] ?? null)) {
            throw new InvalidArgumentException('Invalid manager form response');
        }

        $legacyFields = $response['data']['fields'] ?? null;
        if (!is_array($legacyFields)) {
            throw new InvalidArgumentException('Manager form response does not contain fields');
        }

        $fields = [];
        foreach ($legacyFields as $field) {
            if (!is_array($field) || !is_string($field['name'] ?? null) || $field['name'] === '') {
                continue;
            }
            if (($field['type'] ?? '') === 'hidden') {
                continue;
            }

            $fieldName = $field['name'];
            $fields[$fieldName] = [
                'name' => $fieldName,
                'label' => (string) ($field['label'] ?? $fieldName),
                'type' => $this->type((string) ($field['type'] ?? 'text')),
                'value' => $field['value'] ?? '',
                'required' => (bool) ($field['required'] ?? false),
                'options' => is_array($field['options'] ?? null) ? $field['options'] : [],
                'help' => (string) ($field['help'] ?? ''),
                'rules' => is_array($field['rules'] ?? null) ? $field['rules'] : [],
            ];
        }

        return ['name' => $name, 'title' => $title, 'fields' => $fields];
    }

    private function type(string $type): string
    {
        return match ($type) {
            'combo', 'radio', 'switch' => 'select',
            'check' => 'checkbox',
            'password' => 'password',
            'textarea' => 'textarea',
            default => 'text',
        };
    }
}
