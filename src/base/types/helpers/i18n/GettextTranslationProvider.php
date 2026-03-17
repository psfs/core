<?php

namespace PSFS\base\types\helpers\i18n;

class GettextTranslationProvider implements TranslationProviderInterface
{
    public function translate(string $message, string $locale, array $context = []): ?string
    {
        return gettext($message);
    }
}

