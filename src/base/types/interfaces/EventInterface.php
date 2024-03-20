<?php

namespace PSFS\base\types\interfaces;

interface EventInterface
{
    const EVENT_SUCCESS = 0;
    const EVENT_FAILED = 1;
    const EVENT_SKIPPED = 2;

    public function __invoke(): int;
}
