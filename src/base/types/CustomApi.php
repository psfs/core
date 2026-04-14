<?php

namespace PSFS\base\types;

/**
 * @package PSFS\base\types
 */
abstract class CustomApi extends Api
{

    public function getModelTableMap()
    {
        return $this->unsupportedActionResult();
    }

    public function get($pk)
    {
        return $this->unsupportedActionResult();
    }

    public function delete($pk = null)
    {
        return $this->unsupportedActionResult();
    }

    public function post()
    {
        return $this->unsupportedActionResult();
    }

    public function put($pk)
    {
        return $this->unsupportedActionResult();
    }

    public function admin()
    {
        return $this->unsupportedActionResult();
    }

    public function bulk()
    {
        return $this->unsupportedActionResult();
    }

    protected function unsupportedActionResult()
    {
        return null;
    }
}
