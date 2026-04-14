<?php

namespace PSFS\base\types\traits\Config;

use PSFS\base\Logger;

trait ConfigPersistenceTrait
{
    /**
     * @param array $data
     * @param array|null $extra
     * @return array
     */
    protected static function saveConfigParams(array $data, $extra = null): array
    {
        Logger::log('Saving required config parameters');
        if (!empty($extra) && array_key_exists('label', $extra) && is_array($extra['label'])) {
            foreach ($extra['label'] as $index => $field) {
                if (array_key_exists($index, $extra['value']) && !empty($extra['value'][$index])) {
                    $data[$field] = $extra['value'][$index];
                }
            }
        }
        return $data;
    }

    /**
     * @param array $data
     * @return array
     */
    protected static function saveExtraParams(array $data): array
    {
        $finalData = [];
        if (empty($data)) {
            return $finalData;
        }
        Logger::log('Saving extra configuration parameters');
        foreach (self::iterateConfigEntries($data) as [$key, $value]) {
            if (null !== $value) {
                $finalData[$key] = $value;
            }
        }
        return $finalData;
    }

    protected static function shouldPersistConfigEntry(mixed $value, string $key): bool
    {
        if (in_array($key, self::$required, true)) {
            return true;
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return true;
        }
        return $value !== null && $value !== '';
    }

    /**
     * @param array $data
     * @return \Generator
     */
    protected static function iterateConfigEntries(array $data): \Generator
    {
        foreach ($data as $key => $value) {
            yield [$key, $value];
        }
    }
}

