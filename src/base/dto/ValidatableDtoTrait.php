<?php

namespace PSFS\base\dto;

trait ValidatableDtoTrait
{
    private static ?\WeakMap $__validationInputMap = null;
    private ?ValidationResult $__validationResult = null;

    public function checkValidations(?ValidationContext $ctx = null): ValidationResult
    {
        $context = $ctx ?? new ValidationContext($this->getValidationInputData());
        $result = DtoValidationEngine::validate(
            $this,
            $context,
            fn (mixed $value, string $type): mixed => $this->checkCastedValue($value, $type)
        );
        $this->__validationResult = $result;
        return $result;
    }

    public function isValid(?ValidationContext $ctx = null): bool
    {
        return $this->checkValidations($ctx)->isValid();
    }

    /**
     * @return array<int, array{field:string,code:string,message:string}>
     */
    public function getValidationErrors(): array
    {
        return $this->__validationResult?->getErrors() ?? [];
    }

    public function getValidationResult(): ?ValidationResult
    {
        return $this->__validationResult;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function setValidationInputData(array $data): void
    {
        if (self::$__validationInputMap === null) {
            self::$__validationInputMap = new \WeakMap();
        }
        self::$__validationInputMap[$this] = $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function getValidationInputData(): array
    {
        if (self::$__validationInputMap === null || !isset(self::$__validationInputMap[$this])) {
            return [];
        }
        $data = self::$__validationInputMap[$this];
        return is_array($data) ? $data : [];
    }
}

