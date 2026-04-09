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
        $this->localePath = BASE_DIR . DIRECTORY_SEPARATOR . 'locale' . DIRECTORY_SEPARATOR . $this->locale;
        $this->scanPath = CACHE_DIR . DIRECTORY_SEPARATOR . 'i18n_discovery_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->scanPath);
        $this->deleteDir($this->localePath);
    }

    public function testCompileTranslationsReturnsCustomCatalogMessage(): void
    {
        $result = I18nDiscoveryTraitTestDouble::compileTranslations('/tmp/custom/');

        $this->assertStringContainsString('Legacy PO/MO compilation disabled', $result);
    }

    public function testFindTranslationsReturnsEmptyArrayForMissingPath(): void
    {
        $result = I18nDiscoveryTraitTestDouble::findTranslations('/path/that/does/not/exist', $this->locale);

        $this->assertSame([], $result);
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
        $this->assertStringContainsString('Legacy PO extraction disabled', $results[0]);
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
}
