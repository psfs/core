<?php
namespace PSFS\base\types\traits\Helper;

/**
 * Trait OptionTrait
 * @package PSFS\base\types\traits\Helper
 */
trait OptionTrait {
    /**
     * @var array
     */
    private $options = [];

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param array $options
     * @return OptionTrait
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Add an option
     *
     * @param string $key
     * @param mixed|null $value
     *
     * @return OptionTrait
     */
    public function addOption($key, $value = NULL)
    {
        $this->options[$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @return OptionTrait
     */
    public function dropOption($key) {
        if(array_key_exists($key, $this->options)) {
            unset($this->options[$key]);
        }
        return $this;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function getOption($key) {
        if(array_key_exists($key, $this->options)) {
            return $this->options[$key];
        }
        return null;
    }

}
