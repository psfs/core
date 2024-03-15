<?php

namespace PSFS\base\types\traits\Router;

use PSFS\base\Cache;
use PSFS\base\exception\GeneratorException;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\helpers\RouterHelper;

/**
 * Trait SluggerTrait
 * @package PSFS\base\types\traits\Router
 */
trait SluggerTrait
{
    use RoutingTrait;

    /**
     * @var array
     */
    private $slugs = [];

    /**
     * @return array
     */
    public function getSlugs()
    {
        return $this->slugs;
    }

    /**
     * Parse slugs to create translations
     * @return $this
     */
    private function generateSlugs()
    {
        foreach ($this->routing as $key => &$info) {
            $keyParts = explode('#|#', $key);
            $keyParts = array_key_exists(1, $keyParts) ? $keyParts[1] : $keyParts[0];
            $slug = RouterHelper::slugify($keyParts);
            $this->slugs[$slug] = $key;
            $info['slug'] = $slug;
            // TODO add routes to translations JSON
        }
        return $this;
    }

    /**
     * @return $this
     * @throws GeneratorException
     */
    public function simpatize()
    {
        $this->generateSlugs();
        GeneratorHelper::createDir(CONFIG_DIR);
        Cache::getInstance()->storeData(CONFIG_DIR . DIRECTORY_SEPARATOR . 'urls.json', [$this->routing, $this->getSlugs()], Cache::JSON, TRUE);

        return $this;
    }
}
