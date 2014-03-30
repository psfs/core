<?php

namespace PSFS\types;

abstract class Forms{
    /**
     * Variables de formulario
     */
    protected $enctype = "application/x-www-form-urlencoded";
    protected $method = "POST";
    protected $action = "";
    protected $attrs = array();
    protected $fields = array();
    protected $name;

    abstract function getName();

    /**
     * Setters
     */
    public function setEncType($enctype){ $this->enctype = $enctype; }
    public function setAction($action){ $this->action = $action; }
    public function setMethod($method){ $this->method = $method; }
    public function setAttrs(array $attrs){ $this->attrs = $attrs; }
    public function addAttr($name, $value){ $this->attrs[$name] = $value; }

    /**
     * Método genérico que devuelve una propiedad existente de la clase
     * @param $prop
     *
     * @return null
     */
    public function get($prop)
    {
        $return = null;
        if(property_exists($this, $prop)) $return = $this->$prop;
        return $return;
    }

    /**
     * Método que devuelve un campo del formulario
     * @param $name
     *
     * @return null
     */
    public function getField($name){ return (isset($this->fields[$name])) ? $this->fields[$name] : null; }
}