<?php

namespace PSFS\tests\base\admin;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PSFS\base\admin\AdminManagerFormSchemaAdapter;

class AdminManagerFormSchemaAdapterTest extends TestCase
{
    public function testAdaptsTheActualManagerTraitJsonResponseShape(): void
    {
        $schema = (new AdminManagerFormSchemaAdapter())->fromManagerResponse([
            'success' => true,
            'message' => 'No message',
            'total' => 3,
            'pages' => 1,
            'data' => ['fields' => [
                ['name' => '__pk', 'type' => 'hidden', 'required' => false],
                ['name' => 'Title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'value' => ''],
                ['name' => 'State', 'label' => 'State', 'type' => 'combo', 'options' => ['draft' => 'Draft']],
            ], 'actions' => []],
        ], 'CLIENT.Related', 'Related');

        self::assertSame('CLIENT.Related', $schema['name']);
        self::assertSame('Related', $schema['title']);
        self::assertArrayNotHasKey('__pk', $schema['fields']);
        self::assertSame('text', $schema['fields']['Title']['type']);
        self::assertTrue($schema['fields']['Title']['required']);
        self::assertSame('select', $schema['fields']['State']['type']);
        self::assertSame(['draft' => 'Draft'], $schema['fields']['State']['options']);
    }

    public function testRejectsNonManagerJsonResponse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AdminManagerFormSchemaAdapter())->fromManagerResponse(['success' => false, 'data' => []], 'x', 'x');
    }
}
