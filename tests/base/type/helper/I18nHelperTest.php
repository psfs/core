<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\config\Config;
use PSFS\base\extension\CustomTranslateExtension;
use PSFS\base\exception\GeneratorException;
use PSFS\base\types\helpers\I18nHelper;

class I18nHelperTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->resetTranslationState();
        $defaultLanguage = Config::getParam('default.language', 'es_ES');
        $security = Security::getInstance();
        $security->setSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY, substr($defaultLanguage, 0, 2));
        $security->setSessionKey(I18nHelper::PSFS_SESSION_LOCALE_KEY, $defaultLanguage);
        Request::setLanguageHeader('');
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        Request::dropInstance();
    }

    public function testDefaultLanguageExtraction()
    {
        $default_language = Config::getParam('default.language', 'es_ES');
        // In command line interface it should get the default language from the config
        $this->assertEquals($default_language, I18nHelper::extractLocale());
    }

    public function testSanitize()
    {
        $input = 'Numéro façade naïve rôle';
        $expected = 'Numero facade naive role';
        $this->assertEquals($expected, I18nHelper::sanitize($input));
    }

    public function testAvoidAttacks()
    {
        $input = '<script>alert("attack");</script>';
        $this->assertEquals('', I18nHelper::cleanHtmlAttacks($input));
        $input = '<iframe src="https://www.google.com"></iframe>';
        $this->assertEquals('', I18nHelper::cleanHtmlAttacks($input));
        $input = '<p>Hello world</p>';
        $this->assertEquals($input, I18nHelper::cleanHtmlAttacks($input));
    }

    public function testForceLanguage()
    {
        $default_language = Config::getParam('default.language', 'es_ES');
        $forced_language = ($default_language === 'en_GB') ? 'es_ES' : 'en_GB';
        $string_to_translate = $this->getKnownTranslationKey();
        // First of all, let's check the default behavior
        $this->assertNotEquals($forced_language, I18nHelper::extractLocale());
        $this->assertEquals($default_language, I18nHelper::extractLocale());
        $this->assertIsString(t($string_to_translate));
        // Now we try to force a different language
        I18nHelper::setLocale($forced_language, force: true);
        $this->assertNotEquals($default_language, I18nHelper::extractLocale());
        $this->assertEquals($forced_language, I18nHelper::extractLocale());
        $this->assertIsString(t($string_to_translate));
        // And finally we try again changing to default language
        I18nHelper::setLocale($default_language, force: true);
        $this->assertNotEquals($forced_language, I18nHelper::extractLocale());
        $this->assertEquals($default_language, I18nHelper::extractLocale());
        $this->assertIsString(t($string_to_translate));
    }

    public function testExtractLocaleVariantsFromHeaderAndSession(): void
    {
        $defaultLanguage = Config::getParam('default.language', 'es_ES');
        $security = Security::getInstance();

        Request::setLanguageHeader('en');
        $this->assertSame('en_US', I18nHelper::extractLocale($defaultLanguage));

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

        $knownMessage = $this->getKnownTranslationKey();
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

    public function testLocaleValidationAcceptsAndRejectsExpectedFormats(): void
    {
        $this->assertTrue(I18nHelper::isValidLocale('es_ES'));
        $this->assertTrue(I18nHelper::isValidLocale('en'));
        $this->assertFalse(I18nHelper::isValidLocale('ES_es'));
        $this->assertFalse(I18nHelper::isValidLocale('english'));
        $this->assertFalse(I18nHelper::isValidLocale('en-GB'));
    }

    public function testExtractLocaleNormalizesBrowserHeaderVariants(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pt-BR,pt;q=0.8';
        Request::dropInstance();
        Request::getInstance()->init();
        Request::setLanguageHeader('');
        Security::getInstance()->setSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY, null);
        Security::getInstance()->setSessionKey(I18nHelper::PSFS_SESSION_LOCALE_KEY, null);

        $this->assertSame('pt_PT', I18nHelper::extractLocale());
    }

    public function testFindTranslationsRejectsInvalidLocale(): void
    {
        $this->expectException(GeneratorException::class);
        I18nHelper::findTranslations(BASE_DIR, 'en-GB');
    }

    public function testGenerateTranslationsFileCreatesAndLoadsCatalog(): void
    {
        $filename = CACHE_DIR . DIRECTORY_SEPARATOR . 'tmp_i18n_' . uniqid('', true) . '.php';
        @unlink($filename);
        $generated = I18nHelper::generateTranslationsFile($filename);
        $this->assertIsArray($generated);
        $this->assertFileExists($filename);

        file_put_contents($filename, '<?php $translations = ["hello" => "hello"];');
        $loaded = I18nHelper::generateTranslationsFile($filename);
        $this->assertSame('hello', $loaded['hello'] ?? null);
        @unlink($filename);
    }

    public function testUtf8EncodeAndCheckI18nClassBranches(): void
    {
        $encoded = I18nHelper::utf8Encode([
            'a' => "ni\xC3\xB1o",
            'nested' => ['b' => "canci\xC3\xB3n"],
        ]);
        $this->assertIsArray($encoded);
        $this->assertArrayHasKey('a', $encoded);
        $this->assertIsArray($encoded['nested']);

        $this->assertTrue(I18nHelper::checkI18Class(\PSFS\controller\ConfigController::class . 'I18n'));
        $this->assertFalse(I18nHelper::checkI18Class('PSFS\\Unknown\\NopeI18n'));
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

    private function getKnownTranslationKey(): string
    {
        $catalogPath = LOCALE_DIR . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'en_GB.json';
        if (file_exists($catalogPath)) {
            $catalog = json_decode((string)file_get_contents($catalogPath), true);
            if (is_array($catalog) && !empty($catalog)) {
                foreach ($catalog as $key => $value) {
                    if (is_string($value) && stripos($value, 'page not found') !== false) {
                        return (string)$key;
                    }
                }
                $firstKey = array_key_first($catalog);
                if (is_string($firstKey) && '' !== $firstKey) {
                    return $firstKey;
                }
            }
        }
        return '__KNOWN_TRANSLATION_KEY__';
    }
}
