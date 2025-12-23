<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\types\helpers\I18nHelper;

class I18nHelperTest extends TestCase
{
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
}
