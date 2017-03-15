<?php
namespace PSFS\base\types\helpers;

use PSFS\base\dto\FormAction;

/**
 * Class ApiFormHelper
 * @package PSFS\base\types\helpers
 */
class ApiFormHelper {
    /**
     * @param string $namespace
     * @param string $domain
     * @param string $api
     * @return FormAction[]
     */
    public static function checkApiActions($namespace, $domain, $api) {
        $actions = [];
        $reflector = new \ReflectionClass($namespace);
        if(null !== $reflector) {
            foreach($reflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $apiAction) {
                $docComments = $apiAction->getDocComment();
                $action = self::extractAction($docComments);
                if(null !== $action) {
                    list($route, $info) = RouterHelper::extractRouteInfo($apiAction, $api, $domain);
                    list($method, $cleanRoute) = RouterHelper::extractHttpRoute($route);
                    $formAction = new FormAction();
                    $formAction->label = _($info['label']);
                    $formAction->method = $method;
                    $formAction->url = $cleanRoute;
                    $actions[] = $formAction;
                }
            }
        }
        return $actions;
    }

    /**
     * Method that extract the instance of the class
     * @param $doc
     * @return null|string
     */
    private static function extractAction($doc)
    {
        $action = null;
        if (false !== preg_match('/@action\s+([^\s]+)/', $doc, $matches)) {
            list(, $action) = $matches;
        }
        return $action;
    }
}