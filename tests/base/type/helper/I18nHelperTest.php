<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\config\Config;
use PSFS\base\extension\CustomTranslateExtension;
use PSFS\base\types\helpers\I18nHelper;

class I18nHelperTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetTranslationState();
        $defaultLanguage = Config::getParam('default.language', 'es_ES');
        $security = Security::getInstance();
        $security->setSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY, substr($defaultLanguage, 0, 2));
        $security->setSessionKey(I18nHelper::PSFS_SESSION_LOCALE_KEY, $defaultLanguage);
        Request::setLanguageHeader('');
    }

    public function testDefaultLanguageExtraction()
    {
        $default_language = Config::getParam('default.language', 'es_ES');
        // In command line interface it should get the default language from the config
        $this->assertEquals($default_language, I18nHelper::extractLocale());
    }

    public function testSanitize()
    {
        $input = 'Un murciélago en JAÉN es malagüero para un niño del Barça';
        $expected = 'Un murcielago en JAEN es malaguero para un nino del Barca';
        $this->assertEquals($expected, I18nHelper::sanitize($input));
    }

    public function testAvoidAttacks()
    {
        $input = '<script>alert("attack");</script>';
        $this->assertEquals('', I18nHelper::cleanHtmlAttacks($input));
        $input = '<iframe src="https://www.google.com"></iframe>';
        $this->assertEquals('', I18nHelper::cleanHtmlAttacks($input));
        $input = '<p>Hola mundo</p>';
        $this->assertEquals($input, I18nHelper::cleanHtmlAttacks($input));
    }

    public function testForceLanguage()
    {
        $default_language = Config::getParam('default.language', 'es_ES');
        $forced_language = 'en_GB';
        $string_to_translate = 'Página no encontrada';
        // First of all, let's check the default behavior
        $this->assertNotEquals($forced_language, I18nHelper::extractLocale());
        $this->assertEquals($default_language, I18nHelper::extractLocale());
        $this->assertEquals($string_to_translate, t($string_to_translate));
        // Now we try to force a different language
        I18nHelper::setLocale($forced_language, force: true);
        $this->assertNotEquals($default_language, I18nHelper::extractLocale());
        $this->assertEquals($forced_language, I18nHelper::extractLocale());
        $this->assertEquals('Page not found', t($string_to_translate));
        // And finally we try again changing to default language
        I18nHelper::setLocale($default_language, force: true);
        $this->assertNotEquals($forced_language, I18nHelper::extractLocale());
        $this->assertEquals($default_language, I18nHelper::extractLocale());
        $this->assertEquals($string_to_translate, t($string_to_translate));
    }

    public function testExtractLocaleVariantsFromHeaderAndSession(): void
    {
        $defaultLanguage = Config::getParam('default.language', 'es_ES');
        $security = Security::getInstance();

        Request::setLanguageHeader('en');
        $this->assertSame('en_GB', I18nHelper::extractLocale($defaultLanguage));

        Request::setLanguageHeader('zz');
        $this->assertSame($defaultLanguage, I18nHelper::extractLocale($defaultLanguage));

        $this->clearLanguageHeader();
        $security->setSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY, 'fr');
        $this->assertSame('fr_FR', I18nHelper::extractLocale());

        $security->setSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY, null);
        Request::setLanguageHeader('');
    }

    public function testTranslateWithProvidersOrderCustomThenGettextThenOriginal(): void
    {
        I18nHelper::clearMissingTranslationsReport();
        I18nHelper::setLocale('en_GB', force: true);

        $knownMessage = 'Página no encontrada';
        $catalog = [$knownMessage => 'Custom translation first'];
        $catalogMap = [mb_convert_case($knownMessage, MB_CASE_LOWER, 'UTF-8') => $knownMessage];

        $fromCustom = I18nHelper::translateWithProviders($knownMessage, 'en_GB', $catalog, $catalogMap, true);
        $this->assertSame('Custom translation first', $fromCustom);

        $fromGettext = I18nHelper::translateWithProviders($knownMessage, 'en_GB', [], [], true);
        $this->assertSame(gettext($knownMessage), $fromGettext);

        $missingMessage = '__I18N_MISSING_' . uniqid('', true);
        $fromOriginal = I18nHelper::translateWithProviders($missingMessage, 'en_GB', [], [], false);
        $this->assertSame($missingMessage, $fromOriginal);

        // Missing report should be recorded once per locale/message.
        I18nHelper::translateWithProviders($missingMessage, 'en_GB', [], [], false);
        $report = I18nHelper::getMissingTranslationsReport();
        $this->assertArrayHasKey('en_GB', $report);
        $matches = array_values(array_filter($report['en_GB'], static fn ($msg) => $msg === $missingMessage));
        $this->assertCount(1, $matches);
    }

    private function clearLanguageHeader(): void
    {
        $request = Request::getInstance();
        $reflection = new \ReflectionClass($request);
        $headerProperty = $reflection->getProperty('header');
        $headerProperty->setAccessible(true);
        $headers = $headerProperty->getValue($request);
        if (!is_array($headers)) {
            $headers = [];
        }
        unset($headers['X-API-LANG']);
        $headerProperty->setValue($request, $headers);
    }

    private function resetTranslationState(): void
    {
        CustomTranslateExtension::dropInstance();
        $reflection = new \ReflectionClass(CustomTranslateExtension::class);
        $values = [
            'translations' => [],
            'translationsKeys' => [],
            'locale' => 'es_ES',
            'generate' => false,
            'filename' => '',
        ];
        foreach ($values as $property => $value) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue(null, $value);
        }
    }
}
