<?php
namespace PSFS\base\types\traits\Form;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use PSFS\base\config\Config;

/**
 * Trait FormModelTrait
 * @package PSFS\base\types\traits\Form
 */
trait FormModelTrait {
    use FormDataTrait;
    /**
     * @var ActiveRecordInterface
     */
    protected $model;

    /**
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
     * @param string $key
     * @param mixed $value
     * @return void
     */
    private function hydrateModelField($key, $value)
    {
        $setter = 'set' . ucfirst($key);
        $getter = 'get' . ucfirst($key);
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
     * @param array $field
     * @param string|array $val
     * @param array $data
     * @return array
     */
    private function extractRelatedModelFieldValue($field, $val, $data)
    {
        //Extraemos el dato del modelo relacionado si existe el getter
        $method = null;
        if (array_key_exists('class_data', $field) && method_exists($val, 'get' . $field['class_data'])) {
            $classMethod = 'get' . $field['class_data'];
            $class = $val->$classMethod();
            if (array_key_exists('class_id', $field) && method_exists($class, 'get' . $field['class_id'])) {
                $method = 'get' . $field['class_id'];
                $data[] = $class->$method();
            } else {
                $data[] = $class->getPrimaryKey();
            }
        } else {
            $data[] = $val;
        }

        return array($field, $method, $data);
    }


    protected function setModelLocale()
    {
        if (method_exists($this->model, 'setLocale')) {
            $this->model->setLocale(Config::getParam('default.language', 'es_ES'));
        }
    }

    /**
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
            case 'checkbox':
            case 'select':
                $data = array();
                if (null !== $value && count($value) > 0) {
                    foreach ($value as $val) {
                        list($field, $method, $data) = $this->extractRelatedModelFieldValue($field, $val, $data);
                    }
                    unset($method);
                }
                $field['value'] = $data;
                break;
            default:
                $field['value'] = is_array($value) ? implode(', ', $value) : $value;
                break;
        }
        return $field;
    }

    /**
     * @param string $key
     * @param array $field
     * @return array
     */
    private function extractModelFieldValue($key, $field)
    {
        //Extraemos el valor del campo del modelo
        $method = 'get' . ucfirst($key);
        $type = (array_key_exists('type', $field)) ? $field['type'] : 'text';
        //Extraemos los campos del modelo
        if (method_exists($this->model, $method)) {
            $value = $this->model->$method();
            //En caso de ser un objeto tenemos una lógica especial
            if (is_object($value)) {
                //Si es una relación múltiple
                if ($value instanceof ObjectCollection) {
                    $field = $this->computeModelFieldValue($field, $value, $type);
                } else { //O una relación unitaria
                    if (method_exists($value, '__toString')) {
                        $field['value'] = $value;
                    } elseif ($value instanceof \DateTime) {
                        $field['value'] = $value->format('Y-m-d H:i:s');
                    } else {
                        $field['value'] = $value->getPrimaryKey();
                    }
                }
            } else {
                $field['value'] = $value;
            }
        }
        //Si tenemos un campo tipo select o checkbox, lo forzamos a que siempre tenga un valor array
        if ((array_key_exists('value', $field) && !is_array($field['value']))
            && in_array($type, ['select', 'checkbox'])
        ) {
            $field['value'] = [$field['value']];
        }
        return $field;
    }
}