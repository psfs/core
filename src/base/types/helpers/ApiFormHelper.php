<?php

namespace PSFS\base\types\helpers;

use PSFS\base\dto\FormAction;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * Class ApiFormHelper
 * @package PSFS\base\types\helpers
 */
class ApiFormHelper
{
    /**
     * @param string $namespace
     * @param string $domain
     * @param string $api
     * @return FormAction[]
     * @throws ReflectionException
     */
    public static function checkApiActions(string $namespace, string $domain, string $api): array
    {
        $actions = [];
        $reflector = new ReflectionClass($namespace);
        if (null !== $reflector) {
            foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $apiAction) {
                $docComments = $apiAction->getDocComment();
                $action = AnnotationHelper::extractAction($docComments);
                if (null !== $action) {
                    list($route, $info) = RouterHelper::extractRouteInfo($apiAction, $api, $domain);
                    list($method, $cleanRoute) = RouterHelper::extractHttpRoute($route);
                    $formAction = new FormAction();
                    $formAction->label = t($info['label']);
                    $formAction->method = $method;
                    $formAction->url = $cleanRoute;
                    $actions[] = $formAction;
                }
            }
        }
        return $actions;
    }
}
