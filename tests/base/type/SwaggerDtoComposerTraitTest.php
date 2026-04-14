<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\traits\Api\SwaggerDtoComposerTrait;

class SwaggerDtoComposerProbe
{
    use SwaggerDtoComposerTrait;

    public function compose(array $dto, array $modelDto, string $dtoName): array
    {
        return $this->checkDtoAttributes($dto, $modelDto, $dtoName);
    }
}

class SwaggerDtoComposerTraitTest extends TestCase
{
    private SwaggerDtoComposerProbe $probe;

    protected function setUp(): void
    {
        $this->probe = new SwaggerDtoComposerProbe();
    }

    public function testCheckDtoAttributesKeepsScalarAndUnknownValues(): void
    {
        $dto = [
            'id' => ['type' => 'integer', 'required' => true],
            'raw' => 'value',
        ];

        $result = $this->probe->compose($dto, ['objects' => []], 'SampleDto');

        $this->assertSame($dto['id'], $result['objects']['SampleDto']['id']);
        $this->assertSame('value', $result['objects']['SampleDto']['raw']);
    }

    public function testCheckDtoAttributesComposesNestedObjectReference(): void
    {
        $dto = [
            'profile' => [
                'class' => 'ProfileDto',
                'type' => 'ProfileDto',
                'is_array' => false,
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ],
        ];

        $result = $this->probe->compose($dto, ['objects' => []], 'UserDto');

        $this->assertSame([
            'type' => 'object',
            '$ref' => '#/definitions/ProfileDto',
        ], $result['objects']['UserDto']['profile']);
        $this->assertSame(['type' => 'string'], $result['objects']['ProfileDto']['name']);
    }

    public function testCheckDtoAttributesComposesNestedArrayAndDeepReferences(): void
    {
        $dto = [
            'items' => [
                'class' => 'OrderItemDto',
                'type' => 'OrderItemDto',
                'is_array' => true,
                'properties' => [
                    'qty' => ['type' => 'integer'],
                    'product' => [
                        'class' => 'ProductDto',
                        'type' => 'ProductDto',
                        'is_array' => false,
                        'properties' => [
                            'sku' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->probe->compose($dto, ['objects' => ['BaseDto' => ['base' => ['type' => 'string']]]], 'OrderDto');

        $this->assertSame([
            'type' => 'array',
            'items' => ['$ref' => '#/definitions/OrderItemDto'],
        ], $result['objects']['OrderDto']['items']);
        $this->assertSame(['type' => 'integer'], $result['objects']['OrderItemDto']['qty']);
        $this->assertSame([
            'type' => 'object',
            '$ref' => '#/definitions/ProductDto',
        ], $result['objects']['OrderItemDto']['product']);
        $this->assertSame(['type' => 'string'], $result['objects']['ProductDto']['sku']);
        $this->assertArrayHasKey('BaseDto', $result['objects']);
    }
}
