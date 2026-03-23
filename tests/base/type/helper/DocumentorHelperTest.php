<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\DocumentorHelper;

class DocumentorHelperTest extends TestCase
{
    public function testTranslateSwaggerFormatsCoversKnownAliases(): void
    {
        $this->assertSame(['boolean', ''], DocumentorHelper::translateSwaggerFormats('bool'));
        $this->assertSame(['integer', 'int32'], DocumentorHelper::translateSwaggerFormats('int'));
        $this->assertSame(['number', 'float'], DocumentorHelper::translateSwaggerFormats('float'));
        $this->assertSame(['string', 'password'], DocumentorHelper::translateSwaggerFormats('varbinary'));
        $this->assertSame(['string', 'date-time'], DocumentorHelper::translateSwaggerFormats('datetime'));
    }

    public function testTranslateSwaggerFormatsDefaultsToString(): void
    {
        $this->assertSame(['string', ''], DocumentorHelper::translateSwaggerFormats('unknown_type'));
    }

    public function testParsePayloadAddsBodyParameterForObjectPayloadOnlyOnce(): void
    {
        $endpoint = [
            'payload' => [
                'is_array' => false,
                'type' => 'OrderPayload',
            ],
        ];
        $paths = [
            '/orders' => [
                'post' => [
                    'parameters' => [],
                ],
            ],
        ];

        [, $paths] = DocumentorHelper::parsePayload($endpoint, [], $paths, '/orders', 'post');
        [, $paths] = DocumentorHelper::parsePayload($endpoint, [], $paths, '/orders', 'post');

        $parameters = $paths['/orders']['post']['parameters'];
        $this->assertCount(1, $parameters);
        $this->assertSame('body', $parameters[0]['in']);
        $this->assertSame('OrderPayload', $parameters[0]['name']);
        $this->assertSame([
            'type' => 'object',
            '$ref' => '#/definitions/OrderPayload',
        ], $parameters[0]['schema']);
    }

    public function testParsePayloadBuildsArraySchemaWhenPayloadIsArray(): void
    {
        $endpoint = [
            'payload' => [
                'is_array' => true,
                'type' => 'BatchItem',
            ],
        ];
        $paths = [
            '/batch' => [
                'post' => [
                    'parameters' => [],
                ],
            ],
        ];

        [, $updatedPaths] = DocumentorHelper::parsePayload($endpoint, [], $paths, '/batch', 'post');
        $schema = $updatedPaths['/batch']['post']['parameters'][0]['schema'];

        $this->assertSame('array', $schema['type']);
        $this->assertSame(['$ref' => '#/definitions/BatchItem'], $schema['items']);
    }

    public function testParseObjectsReplacesBooleanDataSchemaAddsTagAndExtractsDefinitions(): void
    {
        $paths = [
            '/items' => [
                'get' => [
                'responses' => [
                    200 => [
                        'schema' => [
                            'properties' => [
                                'data' => ['type' => 'boolean'],
                            ],
                        ],
                    ],
                ],
                'parameters' => [],
            ],
        ],
    ];
        $dtos = [];
        $endpoint = [
            'class' => 'Inventory',
            'return' => [
                'data' => [
                    'id' => ['type' => 'int', 'required' => true],
                ],
            ],
            'payload' => [
                'is_array' => false,
                'type' => 'InventoryRequest',
            ],
        ];
        $object = [
            'id' => ['type' => 'int', 'required' => true, 'description' => 'Identifier'],
        ];

        DocumentorHelper::parseObjects($paths, $dtos, \stdClass::class, $endpoint, $object, '/items', 'get');
        DocumentorHelper::parseObjects($paths, $dtos, \stdClass::class, $endpoint, $object, '/items', 'get');

        $dataSchema = $paths['/items']['get']['responses'][200]['schema']['properties']['data'];
        $this->assertSame('object', $dataSchema['type']);
        $this->assertSame('#/definitions/stdClass', $dataSchema['$ref']);
        $this->assertSame(['Inventory'], $paths['/items']['get']['tags']);
        $this->assertArrayHasKey('stdClass', $dtos);
        $this->assertSame('integer', $dtos['stdClass']['properties']['id']['type']);
        $this->assertSame('int32', $dtos['stdClass']['properties']['id']['format']);
        $this->assertCount(1, $paths['/items']['get']['parameters']);
    }

    public function testExtractSwaggerDefinitionPreservesComplexTypesAndScalarMetadata(): void
    {
        $definition = DocumentorHelper::extractSwaggerDefinition('SampleDto', [
            'meta' => [
                'type' => 'object',
                'properties' => [
                    'inner' => ['type' => 'string'],
                ],
            ],
            'ids' => [
                'type' => 'array',
                'items' => [
                    'type' => 'integer',
                ],
            ],
            'status' => [
                'type' => 'varchar',
                'required' => true,
                'description' => 'Readable status',
                'format' => 'custom-format',
            ],
        ]);

        $this->assertSame('object', $definition['SampleDto']['properties']['meta']['type']);
        $this->assertSame('array', $definition['SampleDto']['properties']['ids']['type']);
        $this->assertSame('string', $definition['SampleDto']['properties']['status']['type']);
        $this->assertSame('custom-format', $definition['SampleDto']['properties']['status']['format']);
        $this->assertTrue((bool)$definition['SampleDto']['properties']['status']['required']);
        $this->assertSame('Readable status', $definition['SampleDto']['properties']['status']['description']);
    }
}
