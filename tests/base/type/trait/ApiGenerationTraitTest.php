<?php

namespace PSFS\tests\base\type\trait;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\traits\Generator\ApiGenerationTrait;

class ApiGenerationTraitTest extends TestCase
{
    private string $models;

    protected function setUp(): void
    {
        $this->models = CACHE_DIR . DIRECTORY_SEPARATOR . 'api-generation-' . uniqid('', true);
        foreach (['', '/base', '/map', '/Billing', '/Billing/base'] as $directory) {
            GeneratorHelper::createDir($this->models . $directory);
        }
        foreach (['Client.php', 'ClientQuery.php', 'ClientI18n.php', 'notes.txt', 'base/ClientBase.php', 'Billing/Invoice.php', 'Billing/InvoiceQuery.php', 'Billing/base/InvoiceBase.php'] as $file) {
            file_put_contents($this->models . '/' . $file, '');
        }
    }

    protected function tearDown(): void
    {
        GeneratorHelper::deleteDir($this->models);
    }

    public function testOnlyTheModelsAreListedWithTheirPackageAndApiDirectory(): void
    {
        $files = (new ApiGenerationHost())->models($this->models, '/api');

        sort($files);
        $this->assertSame(
            [['Client', null, '/api'], ['Invoice', 'Billing', '/api/Billing']],
            $files
        );
    }

    public function testTheModelsAreProducedOneByOne(): void
    {
        $generator = (new ApiGenerationHost())->generator($this->models, '/api');

        $this->assertInstanceOf(\Generator::class, $generator);
        $this->assertCount(2, iterator_to_array($generator, false));
    }

    public function testThereIsNothingToListWhenTheDirectoryHasNoModels(): void
    {
        $empty = $this->models . '/map';

        $this->assertSame([], (new ApiGenerationHost())->models($empty, '/api'));
    }
}

/**
 * Exposes the model listing of the API generation trait
 */
final class ApiGenerationHost
{
    use ApiGenerationTrait;

    /** @return list<array{0:string,1:?string,2:string}> */
    public function models(string $path, string $apiPath): array
    {
        return iterator_to_array($this->modelFiles($path, $apiPath), false);
    }

    public function generator(string $path, string $apiPath): \Generator
    {
        return $this->modelFiles($path, $apiPath);
    }
}
