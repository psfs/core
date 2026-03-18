<?php

namespace PSFS\base\types\traits\Generator;

use PSFS\base\types\helpers\GeneratorHelper;

trait ApiGenerationTrait
{
    /**
     * @param string $module
     * @param string $modPath
     * @param boolean $force
     * @param string $apiClass
     * @return boolean
     * @throws \ReflectionException
 */
    private function generateBaseApiTemplate($module, $modPath, $force = false, $apiClass = "")
    {
        $created = true;
        $modelPath = $modPath . $module . DIRECTORY_SEPARATOR . 'Models';
        $apiPath = $modPath . $module . DIRECTORY_SEPARATOR . 'Api';
        if (file_exists($modelPath)) {
            $dir = dir($modelPath);
            $this->generateApiFiles($module, $force, $apiClass, $dir, $apiPath);
        }
        return $created;
    }

    /**
     * @param string $module
     * @param string $modPath
     * @param string $api
     * @param string $apiClass
     * @param string $package
     *
     * @return bool
 */
    private function createApiBaseFile($module, $modPath, $api, $apiClass = '', $package = null)
    {
        $class = preg_replace('/(\\\|\/)/', '', $module);
        $customClass = GeneratorHelper::extractClassFromNamespace($apiClass);
        $controller = $this->tpl->dump("generator/api.base.template.twig", array(
            "module" => $module,
            "api" => $api,
            "namespace" => preg_replace('/(\\\|\/)/', '\\', $module),
            "url" => preg_replace('/(\\\|\/)/', '/', $module),
            "class" => $class,
            'customClass' => $customClass,
            'customNamespace' => $apiClass,
            'package' => $package,
        ));

        return $this->writeTemplateToFile($controller,
            $modPath . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . "{$api}BaseApi.php", true);
    }

    /**
     * @param string $module
     * @param string $modPath
     * @param bool $force
     * @param string $api
     * @param string $package
     *
     * @return bool
 */
    private function createApi($module, $modPath, $force, $api, $package = null)
    {
        $class = preg_replace('/(\\\|\/)/', '', $module);
        $controller = $this->tpl->dump("generator/api.template.twig", array(
            "module" => $module,
            "api" => $api,
            "namespace" => preg_replace('/(\\\|\/)/', '\\', $module),
            "url" => preg_replace('/(\\\|\/)/', '/', $module),
            "class" => $class,
            "package" => $package,
        ));

        return $this->writeTemplateToFile($controller, $modPath . DIRECTORY_SEPARATOR . "{$api}.php", $force);
    }

    /**
     * @param $module
     * @param $force
     * @param $apiClass
     * @param \Directory|null $dir
     * @param string $apiPath
     * @param string $package
     * @throws \ReflectionException
 */
    private function generateApiFiles($module, $force, $apiClass, \Directory $dir, string $apiPath, $package = null)
    {
        $base = $dir->path;
        while ($file = $dir->read()) {
            if (!in_array(strtolower($file), ['.', '..', 'base', 'map'])) {
                if (is_dir($base . DIRECTORY_SEPARATOR . $file)) {
                    $this->generateApiFiles($module, $force, $apiClass, dir($base . DIRECTORY_SEPARATOR . $file), $apiPath . DIRECTORY_SEPARATOR . $file, $file);
                } else if (!preg_match('/Query\.php$/i', $file)
                    && !preg_match('/I18n\.php$/i', $file)
                    && preg_match('/\.php$/i', $file)
                ) {
                    $filename = str_replace(".php", "", $file);
                    $this->log->addLog("Generating BASE APIs for {$filename}");
                    if ($this->checkIfIsModel($module, $filename, $package)) {
                        $this->createApiBaseFile($module, $apiPath, $filename, $apiClass, $package);
                        $this->createApi($module, $apiPath, $force, $filename, $package);
                    }
                }
            }
        }
    }

    /**
     * @param string $module
     * @param string $package
     * @param string $filename
     * @return bool
     * @throws \ReflectionException
 */
    private function checkIfIsModel($module, $filename, $package = null)
    {
        $parts = [$module, 'Models'];
        if (strlen($package ?: '')) {
            $parts[] = $package;
        }
        $parts[] = $filename;
        $namespace = '\\' . implode('\\', $parts);
        $reflectorClass = new \ReflectionClass($namespace);
        $isModel = $reflectorClass->isInstantiable();
        return $isModel;
    }
}
