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
            $this->generateApiFiles($module, $force, $apiClass, $modelPath, $apiPath);
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

        return $this->writeTemplateToFile(
            $controller,
            $modPath . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . "{$api}BaseApi.php",
            true
        );
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
     * @param string $module
     * @param bool $force
     * @param string $apiClass
     * @param string $modelPath directory with the generated models
     * @param string $apiPath directory where the APIs are written
     * @throws \ReflectionException
     */
    private function generateApiFiles($module, $force, $apiClass, string $modelPath, string $apiPath)
    {
        foreach ($this->modelFiles($modelPath, $apiPath) as [$filename, $package, $targetPath]) {
            $this->log->addLog("Generating BASE APIs for {$filename}");
            if ($this->checkIfIsModel($module, $filename, $package)) {
                $this->createApiBaseFile($module, $targetPath, $filename, $apiClass, $package);
                $this->createApi($module, $targetPath, $force, $filename, $package);
            }
        }
    }

    /**
     * Walks the generated models one at a time, so the list of models is never held in memory
     *
     * @param string $path directory to walk
     * @param string $apiPath API directory that matches the directory
     * @param string|null $package package (sub directory) the models belong to
     * @return \Generator<int, array{0: string, 1: string|null, 2: string}> model name, package and API directory
     */
    private function modelFiles(string $path, string $apiPath, ?string $package = null): \Generator
    {
        foreach (new \DirectoryIterator($path) as $entry) {
            $name = $entry->getFilename();
            if ($entry->isDot() || in_array(strtolower($name), ['base', 'map'], true)) {
                continue;
            }
            if ($entry->isDir()) {
                yield from $this->modelFiles($entry->getPathname(), $apiPath . DIRECTORY_SEPARATOR . $name, $name);
            } elseif ($this->isModelFile($name)) {
                yield [str_replace('.php', '', $name), $package, $apiPath];
            }
        }
    }

    private function isModelFile(string $file): bool
    {
        return !preg_match('/Query\.php$/i', $file)
            && !preg_match('/I18n\.php$/i', $file)
            && preg_match('/\.php$/i', $file);
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
