<?php

namespace PSFS\base\types\helpers\i18n;

interface TranslationProviderInterface
{
    /**
     * @param string $message
     * @param string $locale
     * @param array $context
     * @return string|null
 */
    public function translate(string $message, string $locale, array $context = []): ?string;
}

