<?php

namespace PSFS\base\types\traits\Api;

/**
 * @package PSFS\base\types\traits\Api
 */
trait SwaggerDtoComposerTrait
{
    /**
     * @param array $dto
     * @param array $modelDto
     * @param string $dtoName
     * @return array
     */
    protected function checkDtoAttributes(array $dto, array $modelDto, string $dtoName): array
    {
        $modelDto['objects'] = $modelDto['objects'] ?? [];
        $modelDto['objects'][$dtoName] = $modelDto['objects'][$dtoName] ?? [];

        foreach ($dto as $param => $info) {
            if (!$this->isDtoReference($info)) {
                $modelDto['objects'][$dtoName][$param] = $info;
                continue;
            }
            $modelDto['objects'][$dtoName][$param] = $this->buildDtoReferenceSchema($info);
            $modelDto = $this->mergeNestedDtoDefinition($modelDto, $info);
        }

        return $modelDto;
    }

    private function isDtoReference(mixed $info): bool
    {
        return is_array($info)
            && array_key_exists('class', $info)
            && array_key_exists('type', $info)
            && array_key_exists('properties', $info);
    }

    private function buildDtoReferenceSchema(array $info): array
    {
        if ((bool)($info['is_array'] ?? false)) {
            return [
                'type' => 'array',
                'items' => [
                    '$ref' => '#/definitions/' . $info['type'],
                ],
            ];
        }

        return [
            'type' => 'object',
            '$ref' => '#/definitions/' . $info['type'],
        ];
    }

    private function mergeNestedDtoDefinition(array $modelDto, array $info): array
    {
        $className = (string)$info['class'];
        $properties = is_array($info['properties']) ? $info['properties'] : [];
        $modelDto['objects'][$className] = $properties;

        $paramDto = $this->checkDtoAttributes($properties, ['objects' => [$className => $properties]], $className);
        if (array_key_exists('objects', $paramDto) && is_array($paramDto['objects'])) {
            $modelDto['objects'] = array_merge($modelDto['objects'], $paramDto['objects']);
        }

        return $modelDto;
    }
}
