<?php
namespace PSFS\base\types\traits\Helper;

/**
 * Trait ParameterTrait
 * @package PSFS\base\types\traits\Helper
 */
trait ParameterTrait {
    /**
     * Curl query/raw params
     * @var array
     */
    private $params;

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params?: [];
    }

    /**
     * @param array|string $params
     * @return ParameterTrait
     */
    public function setParams($params): static
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Add a param
     *
     * @param $key
     * @param mixed|null $value
     *
     * @return ParameterTrait
     */
    public function addParam($key, $value = NULL)
    {
        $this->params[$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @return ParameterTrait
     */
    public function dropParam($key) {
        if(array_key_exists($key, $this->params)) {
            unset($this->params[$key]);
        }
        return $this;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function getParam($key) {
        if(array_key_exists($key, $this->params)) {
            return $this->params[$key];
        }
        return null;
    }

}
