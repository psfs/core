<?php

namespace PSFS\tests\base\type\trait;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\traits\Helper\I18nDiscoveryTrait;
use PSFS\base\types\traits\Helper\I18nLocaleTrait;

class I18nDiscoveryTraitTest extends TestCase
{
    private string $locale = 'zz_ZZ';
    private string $localePath;
    private string $scanPath;

    protected function setUp(): void
    {
        I18nDiscoveryTraitTestDouble::$commands = [];
        $this->localePath = BASE_DIR . DIRECTORY_SEPARATOR . 'locale' . DIRECTORY_SEPARATOR . $this->locale;
        $this->scanPath = CACHE_DIR . DIRECTORY_SEPARATOR . 'i18n_discovery_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->scanPath);
        $this->deleteDir($this->localePath);
    }

    public function testCompileTranslationsUsesWrappedShellExecutor(): void
    {
        $result = I18nDiscoveryTraitTestDouble::compileTranslations('/tmp/custom/');

        $this->assertSame('EXEC_OK', $result);
        $this->assertCount(1, I18nDiscoveryTraitTestDouble::$commands);
        $this->assertStringContainsString('msgfmt', I18nDiscoveryTraitTestDouble::$commands[0]);
        $this->assertStringContainsString('/tmp/custom/translations.po', I18nDiscoveryTraitTestDouble::$commands[0]);
    }

    public function testFindTranslationsReturnsEmptyArrayForMissingPath(): void
    {
        $result = I18nDiscoveryTraitTestDouble::findTranslations('/path/that/does/not/exist', $this->locale);

        $this->assertSame([], $result);
        $this->assertSame([], I18nDiscoveryTraitTestDouble::$commands);
    }

    public function testFindTranslationsScansPhpDirectoriesAndCreatesPoFile(): void
    {
        $modulePath = $this->scanPath . DIRECTORY_SEPARATOR . 'module';
        mkdir($modulePath, 0777, true);
        file_put_contents($modulePath . DIRECTORY_SEPARATOR . 'Sample.php', '<?php echo "ok";');

        $results = I18nDiscoveryTraitTestDouble::findTranslations($this->scanPath, $this->locale);

        $this->assertNotEmpty($results);
        $this->assertStringContainsString('Reviewing directory:', $results[0]);
        $this->assertStringContainsString('Executed command:', $results[0]);
        $this->assertStringContainsString('xgettext', $results[0]);
        $this->assertStringContainsString('EXEC_OK', $results[0]);
        $this->assertFileExists(
            $this->localePath . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR . 'translations.po'
        );
    }

    private function deleteDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if (false === $items) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $this->deleteDir($fullPath);
            } else {
                @unlink($fullPath);
            }
        }
        @rmdir($path);
    }
}

class I18nDiscoveryTraitTestDouble
{
    use I18nLocaleTrait;
    use I18nDiscoveryTrait;

    public static array $commands = [];

    protected static function executeShellCommand(string $command): string
    {
        self::$commands[] = $command;
        return 'EXEC_OK';
    }
}
