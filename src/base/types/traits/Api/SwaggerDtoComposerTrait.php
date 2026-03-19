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
        foreach ($dto as $param => &$info) {
            if (array_key_exists('class', $info)) {
                if ($info['is_array']) {
                    $modelDto['objects'][$dtoName][$param] = [
                        'type' => 'array',
                        'items' => [
                            '$ref' => '#/definitions/' . $info['type'],
                        ]
                    ];
                } else {
                    $modelDto['objects'][$dtoName][$param] = [
                        'type' => 'object',
                        '$ref' => '#/definitions/' . $info['type'],
                    ];
                }
                $modelDto['objects'][$info['class']] = $info['properties'];
                $paramDto = $this->checkDtoAttributes($info['properties'], $info['properties'], $info['class']);
                if (array_key_exists('objects', $paramDto)) {
                    $modelDto['objects'] = array_merge($modelDto['objects'], $paramDto['objects']);
                }
            } else {
                $modelDto['objects'][$dtoName][$param] = $info;
            }
        }
        return $modelDto;
    }
}
