<?php

namespace PSFS\base\types\traits\Api;

use Exception;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\types\Api;
use PSFS\base\types\helpers\ApiHelper;
use PSFS\base\types\helpers\I18nHelper;

trait MutationRequestHydrationTrait
{
    protected function hydrateRequestData()
    {
        $request = Request::getInstance();
        $this->query = array_merge($this->query, $request->getQueryParams());
        $this->data = array_merge($this->data, $request->getRawData());
    }

    protected function extractApiLang()
    {
        $defaultLanguage = (string)Config::getParam('default.language', 'en_US');
        $this->lang = Request::header(Api::HEADER_API_LANG, $defaultLanguage);
    }

    /**
     * @param ModelCriteria $query
     */
    protected function checkI18n(ModelCriteria &$query)
    {
        $this->extractApiLang();
        if (!$this->hasI18nQuerySupport($query)) {
            return;
        }
        $query->useI18nQuery($this->lang);
        $model = (string)$this->getModelNamespace();
        $i18nMapClass = $this->resolveI18nMapClassName($model);
        $modelI18nTableMap = $i18nMapClass::getTableMap();
        $this->appendI18nColumnsToQuery($query, $modelI18nTableMap, (string)$this->lang);
    }

    protected function cleanData(array &$data)
    {
        foreach ($data as &$value) {
            $this->sanitizeRecursiveValue($value);
        }
    }

    /**
     * @param mixed $value
     */
    private function sanitizeRecursiveValue(mixed &$value): void
    {
        if (is_array($value)) {
            foreach ($value as &$nested) {
                $this->sanitizeRecursiveValue($nested);
            }
            return;
        }
        if (is_string($value)) {
            $value = $this->sanitizeString($value);
        }
    }

    /**
     * @param ActiveRecordInterface $model
     * @param array $data
     */
    protected function hydrateModelFromRequest(ActiveRecordInterface $model, array $data = [])
    {
        $this->cleanData($data);
        $model->fromArray($data, ApiHelper::getFieldTypes());
        $tableMap = $this->getTableMap();
        if (!$tableMap instanceof TableMap) {
            return;
        }
        try {
            $this->applyI18nFieldsToModel($model, $tableMap, $data, $this->resolveLocaleFromInput($data));
        } catch (Exception $e) {
            Logger::log($e->getMessage(), LOG_DEBUG);
        }
    }

    protected function checkFieldType()
    {
        $this->fieldType = ApiHelper::getFieldTypes();
    }

    protected function getBulkSavedCount(): int
    {
        return $this->bulkSavedCount;
    }

    protected function sanitizeString(string $value): string
    {
        return I18nHelper::cleanHtmlAttacks($value);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function resolveLocaleFromInput(array $data): string
    {
        if (array_key_exists('Locale', $data)) {
            return (string)$data['Locale'];
        }
        if (array_key_exists('locale', $data)) {
            return (string)$data['locale'];
        }
        $defaultLanguage = (string)Config::getParam('default.language', 'en_US');
        return (string)Request::header(Api::HEADER_API_LANG, $defaultLanguage);
    }
}
