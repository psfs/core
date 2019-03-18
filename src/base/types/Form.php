<?php
namespace PSFS\base\types;

use PSFS\base\exception\FormException;
use PSFS\base\Logger;
use PSFS\base\Singleton;
use PSFS\base\types\interfaces\FormType;
use PSFS\base\types\traits\Form\FormCustomizationTrait;
use PSFS\base\types\traits\Form\FormModelTrait;
use PSFS\base\types\traits\Form\FormSecurityTrait;

/**
 * Class Form
 * @package PSFS\base\types
 */
abstract class Form extends Singleton implements FormType
{
    use FormModelTrait;
    use FormCustomizationTrait;
    use FormSecurityTrait;

    public function __construct($model = null)
    {
        parent::__construct();
        if (null !== $model) {
            $this->model = $model;
        }
    }

    /**
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
     * @return bool
     * @throws FormException
     */
    public function save()
    {
        if (null === $this->model) {
            throw new FormException(t('No se ha asociado ningún modelo al formulario'));
        }
        $this->model->fromArray(array($this->getData()));
        try {
            $model = $this->getHydratedModel();
            $model->save();
            $save = true;
            Logger::log(get_class($this->model) . ' guardado con id ' . $this->model->getPrimaryKey(), LOG_INFO);
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
            throw new FormException($e->getMessage(), $e->getCode(), $e);
        }
        return $save;
    }
}
