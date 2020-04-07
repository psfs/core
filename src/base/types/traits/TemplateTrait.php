<?php
namespace PSFS\base\types\traits;

use PSFS\base\Cache;
use PSFS\base\Logger;

/**
 * Trait TemplateTrait
 * @package PSFS\base\types\traits
 */
trait TemplateTrait {
    /**
     * @Injectable
     * @var \PSFS\base\Template Servicio de gestión de plantillas
     */
    protected $tpl;
    /**
     * Método que graba el contenido de una plantilla en un fichero
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
