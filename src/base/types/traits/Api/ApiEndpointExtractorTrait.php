<?php

namespace PSFS\base\types\traits\Api;

use Exception;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\types\Api;
use PSFS\base\types\helpers\AnnotationHelper;
use PSFS\base\types\helpers\MetadataReader;
use PSFS\base\types\helpers\RouterHelper;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @package PSFS\base\types\traits\Api
 */
trait ApiEndpointExtractorTrait
{
    /**
     * Contract: implemented by composing trait/class (DocumentorHelperTrait).
     */
    abstract protected function extractApi(?string $comments = '', ?ReflectionClass $reflector = null): string;

    /**
     * Contract: implemented by composing trait/class (DocumentorHelperTrait).
     */
    abstract protected function checkDeprecated(ReflectionMethod $method, string $comments = ''): bool;

    /**
     * Contract: implemented by composing trait/class (DocumentorHelperTrait).
     */
    abstract protected function extractReturn(string $model, ReflectionMethod $method, string $comments = ''): array;

    /**
     * Contract: implemented by composing trait/class (DocumentorHelperTrait).
     */
    abstract protected function extractPayload(string $model, ReflectionMethod $method, string $comments = ''): array;

    /**
     * Contract: implemented by composing trait/class (SwaggerDtoComposerTrait).
     */
    abstract protected function checkDtoAttributes(array $dto, array $modelInfo, string $dtoName): array;

    /**
     * Contract: implemented by composing trait/class (DocumentorHelperTrait).
     *
     * @return array<int, string>
     */
    abstract protected function getNativeMethods(): array;

    /**
     * @param string $namespace
     * @param ReflectionMethod $method
     * @param ReflectionClass $reflection
     * @param string $module
     *
     * @return array
     * @throws \ReflectionException
     */
    protected function extractMethodInfo($namespace, ReflectionMethod $method, ReflectionClass $reflection, $module)
    {
        $docComments = $method->getDocComment() ?: '';
        if (null === AnnotationHelper::extractRoute($docComments, $method)) {
            return null;
        }
        $api = $this->extractApi($reflection->getDocComment() ?: '', $reflection);
        list($route, $info) = RouterHelper::extractRouteInfo($method, $api, $module);
        if (!$this->canExposeRoute($info)) {
            return null;
        }
        $modelNamespace = str_replace('Api', 'Models', $namespace);
        return $this->buildMethodInfo(
            $method,
            $reflection,
            $module,
            $modelNamespace,
            $docComments,
            (string)$route,
            $info
        );
    }

    private function canExposeRoute(array $routeInfo): bool
    {
        return (bool)($routeInfo['visible'] ?? false);
    }

    private function buildMethodInfo(
        ReflectionMethod $method,
        ReflectionClass $reflection,
        string $module,
        string $modelNamespace,
        string $docComments,
        string $route,
        array $info
    ): ?array {
        $methodInfo = null;
        try {
            $return = $this->extractReturn($modelNamespace, $method, $docComments);
            $url = $this->extractEndpointUrl($route, $module);
            $methodInfo = [
                'url' => $url,
                'method' => $info['http'],
                'description' => $info['label'],
                'return' => $return,
                'objects' => $return['objects'] ?? [],
                'class' => $reflection->getShortName(),
            ];
            unset($methodInfo['return']['objects']);
            if ($this->checkDeprecated($method, $docComments)) {
                return null;
            }
            $this->setRequestParams($method, $methodInfo, $modelNamespace, $docComments);
            $this->setQueryParams($method, $methodInfo);
            $this->setRequestHeaders($reflection, $methodInfo);
            return $methodInfo;
        } catch (Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
            return $methodInfo;
        }
    }

    private function extractEndpointUrl(string $route, string $module): string
    {
        $parts = explode('#|#', $route);
        $url = (string)array_pop($parts);
        return str_replace('/' . $module . '/api', '', $url);
    }

    /**
     * @param ReflectionMethod $method
     * @param mixed $methodInfo
     */
    protected function setQueryParams(ReflectionMethod $method, &$methodInfo)
    {
        if (!$this->supportsNativeQueryParams($methodInfo, $method)) {
            return;
        }
        $methodInfo['query'] = $this->buildNativeQueryParams();
    }

    private function supportsNativeQueryParams(array $methodInfo, ReflectionMethod $method): bool
    {
        return in_array($methodInfo['method'], [Request::VERB_GET, Request::VERB_POST], true)
            && in_array($method->getShortName(), $this->getNativeMethods(), true);
    }

    private function buildNativeQueryParams(): array
    {
        return [
            [
                "name" => Api::API_LIMIT_FIELD,
                "in" => "query",
                "description" => t("Record limit to return, -1 to return all records"),
                "required" => false,
                "type" => "integer",
            ],
            [
                "name" => Api::API_PAGE_FIELD,
                "in" => "query",
                "description" => t("Page to return"),
                "required" => false,
                "type" => "integer",
            ],
            [
                "name" => Api::API_FIELDS_RESULT_FIELD,
                "in" => "query",
                "description" => t("Fields to return"),
                "required" => false,
                "type" => "array",
                "items" => [
                    "type" => "string",
                ],
            ],
        ];
    }

    /**
     * @param ReflectionClass $reflection
     * @param mixed $methodInfo
     */
    protected function setRequestHeaders(ReflectionClass $reflection, &$methodInfo)
    {
        $methodInfo['headers'] = [];
        foreach ($reflection->getProperties() as $property) {
            $header = $this->extractHeaderDefinition($property);
            if (null !== $header) {
                $methodInfo['headers'][] = $header;
            }
        }
    }

    private function extractHeaderDefinition(ReflectionProperty $property): ?array
    {
        $doc = (string)$property->getDocComment();
        $headerName = MetadataReader::getTagValue('header', $doc, null, $property);
        if (empty($headerName)) {
            return null;
        }
        $required = (bool)MetadataReader::getTagValue('required', $doc, true, $property);
        return [
            "name" => $headerName,
            "in" => "header",
            "required" => $required,
            "type" => MetadataReader::extractVarType($property, $doc),
            "description" => (string)MetadataReader::getTagValue('label', $doc, '', $property),
            "default" => MetadataReader::getTagValue('default', $doc, '', $property),
        ];
    }

    /**
     * @param ReflectionMethod $method
     * @param array $methodInfo
     * @param string $modelNamespace
     * @param string $docComments
     */
    protected function setRequestParams(ReflectionMethod $method, &$methodInfo, $modelNamespace, $docComments)
    {
        $this->setRequestPayload($method, $methodInfo, $modelNamespace, $docComments);
        $parameters = $this->extractParameterTypes($method, $docComments);
        if (!empty($parameters)) {
            $methodInfo['parameters'] = $parameters;
        }
    }

    private function setRequestPayload(
        ReflectionMethod $method,
        array &$methodInfo,
        string $modelNamespace,
        string $docComments
    ): void
    {
        if (!in_array($methodInfo['method'], ['POST', 'PUT'], true)) {
            return;
        }
        list($payloadNamespace, $payloadNamespaceShortName, $payloadDto, $isArray) = $this->extractPayload(
            $modelNamespace,
            $method,
            $docComments
        );
        if (!count($payloadDto)) {
            return;
        }
        $methodInfo['payload'] = [
            'type' => $payloadNamespaceShortName,
            'properties' => $payloadDto,
            'is_array' => $isArray,
        ];
        $methodInfo = $this->checkDtoAttributes($payloadDto, $methodInfo, $payloadNamespace);
    }

    private function extractParameterTypes(ReflectionMethod $method, string $docComments): array
    {
        if ($method->getNumberOfParameters() <= 0) {
            return [];
        }
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $parameterName = $parameter->getName();
            $types = [];
            preg_match_all('/\@param\ (.*)\ \$' . $parameterName . '$/im', $docComments, $types);
            if (count($types) > 1 && !empty($types[1])) {
                $parameters[$parameterName] = $types[1][0];
            }
        }
        return $parameters;
    }
}
