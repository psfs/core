<?php

namespace PSFS\base\types\helpers\attributes;

use InvalidArgumentException;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Injectable implements MetadataAttributeContract
{
    public function __construct(
        public string $class,
        public bool $singleton = true,
        public bool $required = true
    )
    {
        $this->class = $this->normalizeFqcn($this->class);
    }

    public static function tag(): string
    {
        return 'injectable';
    }

    public function resolve(): array
    {
        return [
            'class' => $this->class,
            'singleton' => $this->singleton,
            'required' => $this->required,
        ];
    }

    private function normalizeFqcn(string $className): string
    {
        $normalized = trim($className);
        if ($normalized === '') {
            throw new InvalidArgumentException('[Injectable] `class` is required and cannot be empty');
        }
        return str_starts_with($normalized, '\\') ? $normalized : '\\' . $normalized;
    }
}
