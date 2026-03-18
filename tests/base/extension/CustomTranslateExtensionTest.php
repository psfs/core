<?php

namespace PSFS\tests\base\extension;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\extension\CustomTranslateExtension;
use PSFS\base\Security;
use PSFS\base\types\helpers\FileHelper;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\helpers\I18nHelper;

class CustomTranslateExtensionTest extends TestCase
{
    private array $configBackup = [];
    private array $tmpPaths = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
        $this->resetTranslationsState();
        I18nHelper::clearMissingTranslationsReport();
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpPaths as $path) {
            if (file_exists($path)) {
                FileHelper::deleteDir($path);
            }
        }
        if (!empty($this->configBackup)) {
            Config::save($this->configBackup, []);
            Config::getInstance()->loadConfigData(true);
        }
        $this->resetTranslationsState();
        I18nHelper::clearMissingTranslationsReport();
    }

    public function testCustomOverrideHasPriorityOverBaseCatalog(): void
    {
        $this->overrideConfig([
            'debug' => false,
            'i18n.autogenerate' => false,
        ]);
        I18nHelper::setLocale('en_GB', force: true);

        $customKey = 'compat_' . uniqid('', true);
        $customPath = LOCALE_DIR . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . $customKey;
        GeneratorHelper::createDir($customPath);
        $this->tmpPaths[] = $customPath;

        $override = 'Page overridden by custom';
        $customLocaleFile = $customPath . DIRECTORY_SEPARATOR . 'en_GB.json';
        $knownMessage = $this->getKnownTranslationKey();
        file_put_contents($customLocaleFile, json_encode([
            $knownMessage => $override,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $translated = CustomTranslateExtension::_($knownMessage, $customKey, true);
        $this->assertSame($override, $translated);
    }

    public function testFallbackGettextWhenTranslationMissingInCustomCatalog(): void
    {
        $this->overrideConfig([
            'debug' => false,
            'i18n.autogenerate' => false,
        ]);
        I18nHelper::setLocale('en_GB', force: true);

        $message = '__MISSING_I18N_' . uniqid('', true);
        $translated = CustomTranslateExtension::_($message);
        $this->assertSame(gettext($message), $translated);

        $report = I18nHelper::getMissingTranslationsReport();
        $this->assertArrayHasKey('en_GB', $report);
        $this->assertContains($message, $report['en_GB']);
    }

    public function testAutogeneratePersistsMissingTranslationInCustomCatalog(): void
    {
        $this->overrideConfig([
            'debug' => false,
            'i18n.autogenerate' => true,
        ]);
        $customKey = 'autogen_' . uniqid('', true);
        $customPath = LOCALE_DIR . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . $customKey;
        GeneratorHelper::createDir($customPath);
        $this->tmpPaths[] = $customPath;

        $security = Security::getInstance();
        $security->setSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY, 'en');
        $security->setSessionKey(I18nHelper::PSFS_SESSION_LOCALE_KEY, 'en_GB');

        $customLocaleFile = $customPath . DIRECTORY_SEPARATOR . 'en_GB.json';
        file_put_contents($customLocaleFile, json_encode([], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $message = '__AUTOGEN_I18N_' . uniqid('', true);

        $translation = CustomTranslateExtension::_($message, $customKey, true);
        $storedCatalog = json_decode((string)file_get_contents($customLocaleFile), true);

        $this->assertSame($message, $translation);
        $this->assertIsArray($storedCatalog);
        $this->assertArrayHasKey($message, $storedCatalog);
        $this->assertSame($translation, $storedCatalog[$message]);
    }

    public function testWrapperSourceOfTruthProviderOrderContract(): void
    {
        $this->overrideConfig([
            'debug' => false,
            'i18n.autogenerate' => false,
        ]);
        I18nHelper::setLocale('en_GB', force: true);
        I18nHelper::clearMissingTranslationsReport();

        $knownMessage = $this->getKnownTranslationKey();
        $this->setRuntimeCatalog('en_GB', [
            $knownMessage => 'Custom provider wins',
        ]);

        // Source of truth wrapper contract: merged custom/base catalog first.
        $this->assertSame('Custom provider wins', CustomTranslateExtension::_($knownMessage));

        // Then gettext fallback when catalog does not contain the key.
        $this->setRuntimeCatalog('en_GB', []);
        $this->assertSame(gettext($knownMessage), CustomTranslateExtension::_($knownMessage));

        // Finally original message when neither provider resolves it.
        $missingMessage = '__I18N_WRAPPER_MISSING_' . uniqid('', true);
        $this->assertSame($missingMessage, CustomTranslateExtension::_($missingMessage));
        $report = I18nHelper::getMissingTranslationsReport();
        $this->assertArrayHasKey('en_GB', $report);
        $this->assertContains($missingMessage, $report['en_GB']);
    }

    public function testTranslationsLoadFromSessionCacheVersionWhenAvailable(): void
    {
        $this->overrideConfig([
            'debug' => false,
            'i18n.autogenerate' => false,
            'cache.var' => 'vtest',
        ]);
        $security = Security::getInstance();
        $security->setSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY, 'en');
        $security->setSessionKey(I18nHelper::PSFS_SESSION_LOCALE_KEY, 'en_GB');
        $security->setSessionKey(CustomTranslateExtension::LOCALE_CACHED_VERSION, 'en_GB_vtest');
        $security->setSessionKey(CustomTranslateExtension::LOCALE_CACHED_TAG, [
            'en_GB' => [
                'HELLO_SESSION' => 'Hello from session cache',
            ],
        ]);

        $translated = CustomTranslateExtension::_('HELLO_SESSION');
        $this->assertSame('Hello from session cache', $translated);
    }

    public function testExtensionMetadataAndFilterContract(): void
    {
        $extension = new CustomTranslateExtension();
        $this->assertSame('PSFSi18n', $extension->getName());
        $filters = $extension->getFilters();
        $this->assertNotEmpty($filters);
        $this->assertNotEmpty($extension->getTokenParsers());
    }

    public function testForceReloadResolvesLocaleAliasAndCustomBaseFallback(): void
    {
        $this->overrideConfig([
            'debug' => false,
            'i18n.autogenerate' => false,
        ]);
        $security = Security::getInstance();
        $security->setSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY, 'en');
        $security->setSessionKey(I18nHelper::PSFS_SESSION_LOCALE_KEY, 'en');
        $security->setSessionKey(CustomTranslateExtension::CUSTOM_LOCALE_SESSION_KEY, '__missing_custom_key__');

        $translated = CustomTranslateExtension::_($this->getKnownTranslationKey(), null, true);
        $this->assertIsString($translated);
        $this->assertNotSame('', $translated);
    }

    private function overrideConfig(array $override): void
    {
        $config = $this->configBackup;
        foreach ($override as $key => $value) {
            $config[$key] = $value;
        }
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
    }

    private function resetTranslationsState(): void
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
            $reflectionProperty = $reflection->getProperty($property);
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue(null, $value);
        }

        $security = Security::getInstance();
        $security->setSessionKey(CustomTranslateExtension::LOCALE_CACHED_TAG, null);
        $security->setSessionKey(CustomTranslateExtension::LOCALE_CACHED_VERSION, null);
        $security->setSessionKey(CustomTranslateExtension::CUSTOM_LOCALE_SESSION_KEY, null);
    }

    private function setRuntimeCatalog(string $locale, array $catalog): void
    {
        $reflection = new \ReflectionClass(CustomTranslateExtension::class);
        $translationsProperty = $reflection->getProperty('translations');
        $translationsProperty->setAccessible(true);
        $translationsProperty->setValue(null, [
            $locale => $catalog,
        ]);

        $map = [];
        foreach ($catalog as $key => $_value) {
            $map[mb_convert_case($key, MB_CASE_LOWER, 'UTF-8')] = $key;
        }
        $keysProperty = $reflection->getProperty('translationsKeys');
        $keysProperty->setAccessible(true);
        $keysProperty->setValue(null, [
            $locale => $map,
        ]);

        $localeProperty = $reflection->getProperty('locale');
        $localeProperty->setAccessible(true);
        $localeProperty->setValue(null, $locale);
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
