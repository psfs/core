<?php

namespace PSFS\types;

abstract class Form{

    /**
     * Constantes de uso general
     */
    const SEPARATOR = "__SEPARATOR__";
    const VALID_NUMBER = "^[0-9]+$";
    const VALID_ALPHANUMERIC = "[a-zA-Z0-9]+";
    const VALID_DATETIME = "/([0-2][0-9]{3})\-([0-1][0-9])\-([0-3][0-9])T([0-5][0-9])\:([0-5][0-9])\:([0-5][0-9])(Z|([\-\+]([0-1][0-9])\:00))/";
    const VALID_DATE = "(?:19|20)[0-9]{2}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1[0-9]|2[0-9])|(?:(?!02)(?:0[1-9]|1[0-2])-(?:30))|(?:(?:0[13578]|1[02])-31))"; //YYYY-MM-DD
    const VALID_COLOR = "^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$";
    const VALID_IPV4 = "((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$";
    const VALID_IPV6 = "((^|:)([0-9a-fA-F]{0,4})){1,8}$";
    const VALID_GEO = "-?\d{1,3}\.\d+";
    const VALID_PASSWORD = "^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?!.*\s).*$";
    const VALID_PASSWORD_STRONG = "(?=^.{8,}$)((?=.*\d)|(?=.*\W+))(?![.\n])(?=.*[A-Z])(?=.*[a-z]).*$";

    /**
     * Variables de formulario
     */
    protected $enctype = "application/x-www-form-urlencoded";
    protected $method = "POST";
    protected $action = "";
    protected $attrs = array();
    protected $fields = array();
    protected $crfs;
    protected $errors;

    abstract function getName();

    /**
     * Setters
     */
    public function setEncType($enctype){ $this->enctype = $enctype; return $this; }
    public function setAction($action){ $this->action = $action; return $this; }
    public function setMethod($method){ $this->method = $method; return $this; }
    public function setAttrs(array $attrs){ $this->attrs = $attrs; return $this; }
    public function add($name, array $value = array()){
        $this->fields[$name] = $value;
        $this->fields[$name]['name'] = $this->getName() . "[{$name}]";
        $this->fields[$name]['id'] = $this->getName() . '_' . $name;
        $this->fields[$name]['placeholder'] = ($value['placeholder']) ?: $name;
        return $this;
    }

    public function getEncType(){ return $this->enctype; }
    public function getAction(){ return $this->action; }
    public function getMethod(){ return $this->method; }
    public function getFields(){ return $this->fields; }
    public function getAttrs(){ return $this->attrs; }

    /**
     * Método que genera un CRFS token para los formularios
     */
    private function genCrfsToken()
    {
        $hash_orig = '';
        if(!empty($this->fields)) foreach($this->fields as $field => $value)
        {
            if($field !== self::SEPARATOR) $hash_orig .= $field;
        }
        if(!empty($hash_orig))
        {
            $this->crfs = sha1($hash_orig);
            $this->add($this->getName() . '_token', array(
                "type" => "hidden",
                "value" => $this->crfs,
            ));
        }
        return $this;
    }

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

    /**
     * Método que construye el formulario asignando
     */
    public function build()
    {
        if(strtoupper($this->method) === 'POST') $this->genCrfsToken();
        return $this;
    }

    /**
     * Método que valida si un formulario es o no válido
     * @return bool
     */
    public function isValid()
    {
        $valid = true;
        $token_field = $this->getName() . '_token';
        //Controlamos CSRF token
        if($this->method === 'POST' && (empty($this->fields[$token_field]["value"]) || $this->crfs != $this->fields[$token_field]["value"]))
        {
            $this->errors[$token_field] = _('Formulario no válido');
            $this->fields[$token_field]["error"] = $this->errors[$token_field];
            $valid = false;
        }
        //Validamos los campos del formulario
        if($valid)
        {
            if(!empty($this->fields)) foreach($this->fields as $key => &$field)
            {
                if($key  === self::SEPARATOR) continue;
                //Verificamos si es obligatorio
                if((!isset($field["required"]) || false !== (bool)$field["required"]) && empty($field["value"]))
                {
                    $this->errors[$key] = str_replace('%s', "<strong>{$key}</strong>", _("El campo %s es oligatorio"));
                    $field["error"] = $this->errors[$key];
                    $valid = false;
                }
                //Validamos en caso de tener validaciones
                if(!isset($field[$key]["error"]) && isset($field["pattern"]) && !empty($field["value"]))
                {
                    if(preg_match("/".$field["pattern"]."/", $field["value"]) == 0)
                    {
                        $this->errors[$key] = str_replace('%s', "<strong>{$key}</strong>", _("El campo %s no tiene un formato válido"));
                        $field["error"] = $this->errors[$key];
                        $valid = false;
                    }
                }
            }
        }
        return $valid;
    }

    /**
     * Método que extrae los datos del formulario
     * @return $this
     */
    public function hydrate()
    {
        $data = (\PSFS\base\Request::getInstance()->get($this->getName())) ?: null;
        //Hidratamos los campos conlo que venga del formulario
        if(!empty($data)) foreach($this->fields as $key => &$field)
        {
            if(isset($data[$key])) $field["value"] = $data[$key];
        }
        return $this;
    }
}