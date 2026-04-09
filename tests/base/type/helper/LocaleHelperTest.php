<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\LocaleHelper;

class LocaleHelperTest extends TestCase
{
    public function testNormalizeLocaleCodeNormalizesSupportedPatterns(): void
    {
        $this->assertSame('en_US', LocaleHelper::normalizeLocaleCode('en'));
        $this->assertSame('pt_BR', LocaleHelper::normalizeLocaleCode('pt-br'));
        $this->assertSame('ca_ES', LocaleHelper::normalizeLocaleCode('ca_es'));
        $this->assertNull(LocaleHelper::normalizeLocaleCode('bad-locale-value'));
    }

    public function testBuildAvailableLocalesMergesConfiguredSessionAndDefaultWithFallback(): void
    {
        $locales = LocaleHelper::buildAvailableLocales('', 'de_de', 'ca_es');
        $this->assertSame(['ca_ES', 'de_DE', 'en_US', 'es_ES'], $locales);
    }
}
