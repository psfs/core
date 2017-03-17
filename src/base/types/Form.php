<?php
namespace PSFS\base\types;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use PSFS\base\config\Config;
use PSFS\base\exception\FormException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Singleton;
use PSFS\base\types\interfaces\FormType;

abstract class Form extends Singleton implements FormType
{

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
    protected $buttons;
    protected $extra;
    protected $model;
    protected $logo;

    public function __construct($model = null)
    {
        parent::__construct();
        if (null !== $model) {
            $this->model = $model;
        }
    }

    /**
     * Setters
     * @param string $enctype
     * @return Form
     */
    public function setEncType($enctype)
    {
        $this->enctype = $enctype;
        return $this;
    }

    public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }

    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    public function setAttrs(array $attrs)
    {
        $this->attrs = $attrs;
        return $this;
    }

    public function add($name, array $value = array())
    {
        $this->fields[$name] = $value;
        $this->fields[$name]['name'] = $this->getName() . "[{$name}]";
        $this->fields[$name]['id'] = $this->getName() . '_' . $name;
        $this->fields[$name]['placeholder'] = (array_key_exists('placeholder', $value)) ? $value['placeholder'] : $name;
        $this->fields[$name]['hasLabel'] = (array_key_exists('hasLabel', $value)) ? $value['hasLabel'] : true;
        return $this;
    }

    public function setLogo($logo)
    {
        $this->logo = $logo;
        return $this;
    }

    public function getEncType()
    {
        return $this->enctype;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function getAttrs()
    {
        return $this->attrs;
    }

    public function getButtons()
    {
        return $this->buttons;
    }

    public function getLogo()
    {
        return $this->logo;
    }

    /**
     * Método que genera un CRFS token para los formularios
     * @return Form
     */
    private function genCrfsToken()
    {
        $hash_orig = '';
        if (!empty($this->fields)) {
            foreach ($this->fields as $field => $value) {
                if ($field !== self::SEPARATOR) {
                    $hash_orig .= $field;
                }
            }
        }
        if ('' !== $hash_orig) {
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
     * @param string $prop
     *
     * @return mixed|null
     */
    public function get($prop)
    {
        $return = null;
        if (property_exists($this, $prop)) {
            $return = $this->$prop;
        }
        return $return;
    }

    /**
     * Método que devuelve un campo del formulario
     * @param string $name
     *
     * @return array|null
     */
    public function getField($name)
    {
        return (null !== $name && array_key_exists($name, $this->fields)) ? $this->fields[$name] : null;
    }

    /**
     * Método que devuelve el valor de un campo
     * @param string $name
     *
     * @return mixed|null
     */
    public function getFieldValue($name)
    {
        $value = null;
        if (null !== $this->getField($name)) {
            $field = $this->getField($name);
            $value = (array_key_exists('value', $field) && null !== $field['value']) ? $field['value'] : null;
        }
        return $value;
    }

    /**
     * Método que construye el formulario asignando
     * @return Form
     */
    public function build()
    {
        if (strtoupper($this->method) === 'POST') {
            $this->genCrfsToken();
        }
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
        if (!$this->existsFormToken($token_field)) {
            $this->errors[$token_field] = _('Formulario no válido');
            $this->fields[$token_field]["error"] = $this->errors[$token_field];
            $valid = false;
        }
        //Validamos los campos del formulario
        if ($valid && count($this->fields) > 0) {
            foreach ($this->fields as $key => &$field) {
                if ($key === $token_field || $key === self::SEPARATOR) continue;
                list($field, $valid) = $this->checkFieldValidation($field, $key);
            }
        }
        return $valid;
    }

    /**
     * Método que añade un error para un campo del formulario
     * @param string $field
     * @param string $error
     *
     * @return Form
     */
    public function setError($field, $error = "Error de validación")
    {
        $this->fields[$field]["error"] = $error;
        $this->errors[$field] = $error;
        return $this;
    }

    /**
     * Método que devuelve el error de un campo
     * @param string $field
     *
     * @return string
     */
    public function getError($field)
    {
        return array_key_exists($field, $this->errors) ? $this->errors[$field] : '';
    }

    /**
     * Método que extrae los datos del formulario
     * @return Form
     */
    public function hydrate()
    {
        $data = Request::getInstance()->getData() ?: [];
        //Hidratamos los campos con lo que venga del formulario
        $form_name = $this->getName();
        if (array_key_exists($form_name, $data)) {
            foreach ($this->fields as $key => &$field) {
                list($data, $field) = $this->hydrateField($data, $form_name, $key, $field);
            }
            //Limpiamos los datos
            unset($data[$form_name]);
        }
        //Cargamos los campos extras
        $this->extra = $data;
        return $this;
    }

    /**
     * Método que devuelve un array con los datos del formulario
     * @return array
     */
    public function getData()
    {
        $data = array();
        if (count($this->fields) > 0) {
            foreach ($this->fields as $key => $field) {
                if (self::SEPARATOR !== $key && $key !== ($this->getName() . "_token")) {
                    $data[$key] = (array_key_exists('value', $field)) ? $field["value"] : null;
                }
            }
        }
        return $data;
    }

    /**
     * Método que devuelve los datos extras que vienen en la petición
     * @return array
     */
    public function getExtraData()
    {
        return $this->extra ?: array();
    }

    /**
     * Mëtodo que pre hidrata el formulario para su modificación
     *
     * @param array $data
     * @return void
     * @throws FormException
     */
    public function setData($data)
    {
        if (count($this->fields) === 0) {
            throw new FormException('Se tienen que configurar previamente los campos del formulario', 500);
        }
        /** @var $field array */
        foreach ($this->fields as $key => &$field) {
            if (array_key_exists($key, $data)) {
                $field['value'] = $data[$key];
            }
        }
    }

    /**
     * Método que añade un botón al formulario
     * @param string $id
     * @param string $value
     * @param string $type
     * @param array|null $attrs
     * @return Form
     */
    public function addButton($id, $value = 'Guardar', $type = 'submit', $attrs = array())
    {
        $this->buttons[$id] = array(
            "value" => $value,
            "type" => $type,
            "id" => $id,
        );
        if (count($attrs) > 0) {
            foreach ($attrs as $key => $attr) {
                $this->buttons[$id][$key] = $attr;
            }
        }
        return $this;
    }

    /**
     * Método que elimina un botón del formulario
     * @param string $id
     *
     * @return Form
     */
    public function dropButton($id)
    {
        if (array_key_exists($id, $this->buttons)) {
            unset($this->buttons[$id]);
        }
        return $this;
    }

    /**
     * Método que hidrate un modelo de datos asociado a un formulario
     * @return ActiveRecordInterface
     */
    public function getHydratedModel()
    {
        $this->setModelLocale();
        foreach ($this->getData() as $key => $value) {
            $this->hydrateModelField($key, $value);
        }
        return $this->model;
    }

    /**
     * Método para setear los valores de los campos del formulario automáticamente desde el modelo que los guarda en BD
     * @return void
     */
    public function hydrateFromModel()
    {
        $this->setModelLocale();
        foreach ($this->fields as $key => &$field) {
            $field = $this->extractModelFieldValue($key, $field);
        }
    }

    /**
     * Método que guarda los datos del formulario en el modelo de datos asociado al formulario
     * @return bool
     * @throws FormException
     */
    public function save()
    {
        if (null === $this->model) {
            throw new FormException("No se ha asociado ningún modelo al formulario");
        }
        $this->model->fromArray(array($this->getData()));
        $save = false;
        try {
            $model = $this->getHydratedModel();
            $model->save();
            $save = true;
            Logger::getInstance()->infoLog(get_class($this->model) . " guardado con id " . $this->model->getPrimaryKey());
        } catch (\Exception $e) {
            Logger::getInstance()->errorLog($e->getMessage());
            throw new FormException($e->getMessage(), $e->getCode(), $e);
        }
        return $save;
    }

    /**
     * Checks if the value is empty
     * @param mixed $value
     * @return bool
     */
    protected function checkEmpty($value)
    {
        $isEmpty = false;
        // NULL check
        if (null === $value) {
            $isEmpty = true;
            // Empty Array check
        } else if (is_array($value) && 0 === count($value)) {
            $isEmpty = true;
            // Empty string check
        } else if (0 === strlen(preg_replace('/(\ |\r|\n)/m', '', $value))) {
            $isEmpty = true;
        }

        return $isEmpty;
    }

    /**
     * Método que valida un campo
     * @param array $field
     * @param string $key
     * @return array
     */
    private function checkFieldValidation($field, $key)
    {
        //Verificamos si es obligatorio
        $valid = true;
        if ((!array_key_exists('required', $field) || false !== (bool)$field["required"]) && $this->checkEmpty($field['value'])) {
            $this->setError($key, str_replace('%s', "<strong>{$key}</strong>", _("El campo %s es oligatorio")));
            $field["error"] = $this->getError($key);
            $valid = false;
        }
        //Validamos en caso de tener validaciones
        if (array_key_exists('pattern', $field)
            && array_key_exists($key, $field)
            && !array_key_exists('error', $field[$key])
            && !empty($field["value"])
            && preg_match('/' . $field['pattern'] . '/', $field['value']) === 0
        ) {
            $this->setError($key, str_replace('%s', "<strong>{$key}</strong>", _("El campo %s no tiene un formato válido")));
            $field["error"] = $this->getError($key);
            $valid = false;
        }
        return array($field, $valid);
    }

    /**
     * Método que hidrata los campos del formulario
     * @param array $data
     * @param string $form_name
     * @param string $key
     * @param string|array $field
     * @return array
     */
    private function hydrateField($data, $form_name, $key, $field)
    {
        if (array_key_exists($key, $data[$form_name])) {
            if (preg_match("/id/i", $key) && ($data[$form_name][$key] === 0 || $data[$form_name][$key] === "%" || $data[$form_name][$key] === "")) {
                $field["value"] = null;
            } else {
                $field["value"] = $data[$form_name][$key];
            }
        } else {
            unset($field["value"]);
        }
        return array($data, $field);
    }

    /**
     * Método que hidrata los campos de un formulario desde un modelo
     * @param string $key
     * @param mixed $value
     * @return void
     */
    private function hydrateModelField($key, $value)
    {
        $setter = "set" . ucfirst($key);
        $getter = "get" . ucfirst($key);
        if (method_exists($this->model, $setter)) {
            if (method_exists($this->model, $getter)) {
                $tmp = $this->model->$getter();
                if ($tmp instanceof Collection && is_object($tmp) && gettype($value) !== gettype($tmp)) {
                    $collection = new Collection();
                    $collection->append($value);
                    $value = $collection;
                }
            }
            $this->model->$setter($value);
        }
    }

    /**
     * Método que extrae los valores de un campo de un modelo relacionado con el principal del formulario
     * @param array $field
     * @param string|array $val
     * @param array $data
     * @return array
     */
    private function extractRelatedModelFieldValue($field, $val, $data)
    {
        //Extraemos el dato del modelo relacionado si existe el getter
        $method = null;
        if (array_key_exists('class_data', $field) && method_exists($val, "get" . $field["class_data"])) {
            $class_method = "get" . $field["class_data"];
            $class = $val->$class_method();
            if (array_key_exists('class_id', $field) && method_exists($class, "get" . $field["class_id"])) {
                $method = "get" . $field["class_id"];
                $data[] = $class->$method();
            } else {
                $data[] = $class->getPrimaryKey();
            }
        } else {
            $data[] = $val;
        }

        return array($field, $method, $data);
    }

    /**
     * Método que extrae el valor de un campo del modelo
     * @param string $key
     * @param array $field
     * @return array
     */
    private function extractModelFieldValue($key, $field)
    {
        //Extraemos el valor del campo del modelo
        $method = "get" . ucfirst($key);
        $type = (array_key_exists('type', $field)) ? $field["type"] : "text";
        //Extraemos los campos del modelo
        if (method_exists($this->model, $method)) {
            $value = $this->model->$method();
            //En caso de ser un objeto tenemos una lógica especial
            if (is_object($value)) {
                //Si es una relación múltiple
                if ($value instanceof ObjectCollection) {
                    $field = $this->computeModelFieldValue($field, $value, $type);
                } else { //O una relación unitaria
                    if (method_exists($value, "__toString")) {
                        $field["value"] = $value;
                    } elseif ($value instanceof \DateTime) {
                        $field["value"] = $value->format("Y-m-d H:i:s");
                    } else {
                        $field["value"] = $value->getPrimaryKey();
                    }
                }
            } else {
                $field["value"] = $value;
            }
        }
        //Si tenemos un campo tipo select o checkbox, lo forzamos a que siempre tenga un valor array
        if ((array_key_exists('value', $field) && !is_array($field["value"]))
            && in_array($type, array("select", "checkbox"))
        ) {
            $field["value"] = array($field["value"]);
        }
        return $field;
    }

    /**
     * @param $token_field
     * @return bool
     */
    protected function existsFormToken($token_field)
    {
        if ($this->method !== 'POST') {
            return true;
        } elseif (null === $token_field
            || !array_key_exists($token_field, $this->fields)
        ) {
            return false;
        } elseif (array_key_exists('value', $this->fields[$token_field])
            && $this->crfs === $this->fields[$token_field]['value']
        ) {
            return true;
        }
        return false;
    }

    /**
     * Método que actualiza el idioma de los modelos con i18n
     */
    protected function setModelLocale()
    {
        if (method_exists($this->model, "setLocale")) {
            $this->model->setLocale(Config::getInstance()->get('default_language'));
        }
    }

    /**
     * Método que extrae el valor de un campo del formulario del modelo
     * @param $field
     * @param $value
     * @param $type
     * @return mixed
     */
    private function computeModelFieldValue($field, $value, $type)
    {
        $value = $value->getData();
        //Extraemos los datos en función del tipo de input
        switch ($type) {
            case "checkbox":
            case "select":
                $data = array();
                if (null !== $value && count($value) > 0) {
                    foreach ($value as $val) {
                        list($field, $method, $data) = $this->extractRelatedModelFieldValue($field, $val, $data);
                    }
                    unset($method);
                }
                $field["value"] = $data;
                break;
            default:
                $field["value"] = (is_array($value)) ? implode(", ", $value) : $value;
                break;
        }
        return $field;
    }
}
