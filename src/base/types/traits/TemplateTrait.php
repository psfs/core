<?php

namespace PSFS\base\types\traits;

use PSFS\base\Cache;
use PSFS\base\Logger;
use PSFS\base\Template;
use PSFS\base\types\helpers\attributes\Injectable;

/**
 * @package PSFS\base\types\traits
 */
trait TemplateTrait
{
    /**
     * @Injectable
     * @var \PSFS\base\Template
     */
    #[Injectable(class: Template::class)]
    protected Template $tpl;

    /**
     * @param string $fileContent
     * @param string $filename
     * @param boolean $force
     * @return boolean
     */
    private function writeTemplateToFile($fileContent, $filename, $force = false)
    {
        $created = false;
        if ($force || !file_exists($filename)) {
            try {
                Cache::getInstance()->storeData($filename, $fileContent, Cache::TEXT, true);
                $created = true;
            } catch (\Exception $e) {
                Logger::log($e->getMessage(), LOG_ERR);
            }
        } else {
            Logger::log($filename . t(' not exists or cant write'), LOG_ERR);
        }
        return $created;
    }
}
