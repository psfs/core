<?php

namespace PSFS\base\types\helpers\attributes;

interface DtoConstraintAttributeContract
{
    public function validateValue(mixed $value): bool;

    public function errorCode(): string;
}

