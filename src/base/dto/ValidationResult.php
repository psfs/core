<?php

namespace PSFS\base\dto;

class ValidationResult
{
    /**
     * @var array<int, array{field:string,code:string,message:string}>
     */
    private array $errors = [];

    public function addError(string $field, string $code, string $message): void
    {
        $this->errors[] = [
            'field' => $field,
            'code' => $code,
            'message' => $message,
        ];
    }

    public function isValid(): bool
    {
        return count($this->errors) === 0;
    }

    /**
     * @return array<int, array{field:string,code:string,message:string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function firstMessage(string $default = ''): string
    {
        if ($this->isValid()) {
            return $default;
        }
        return $this->errors[0]['message'];
    }
}

