<?php

namespace PSFS\base\types\helpers\attributes;

interface MetadataAttributeContract
{
    public static function tag(): string;

    public function resolve(): mixed;
}
