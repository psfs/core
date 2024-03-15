<?php

namespace PSFS\base\types\traits\Form;

/**
 * Trait FormCustomizationTrait
 * @package PSFS\base\types\traits\Form
 */
trait FormCustomizationTrait
{
    /**
     * @var array
     */
    protected $buttons = [];
    /**
     * @var string
     */
    protected $logo = '';

    public function setLogo($logo)
    {
        $this->logo = $logo;
        return $this;
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
     * @param string $buttonId
     * @param string $value
     * @param string $type
     * @param array|null $attrs
     * @return FormSchemaTrait
     */
    public function addButton($buttonId, $value = 'Guardar', $type = 'submit', $attrs = array())
    {
        $this->buttons[$buttonId] = [
            'value' => $value,
            'type' => $type,
            'id' => $buttonId,
        ];
        if (count($attrs) > 0) {
            foreach ($attrs as $key => $attr) {
                $this->buttons[$buttonId][$key] = $attr;
            }
        }
        return $this;
    }

    /**
     * @param string $buttonId
     *
     * @return FormSchemaTrait
     */
    public function dropButton($buttonId)
    {
        if (array_key_exists($buttonId, $this->buttons)) {
            unset($this->buttons[$buttonId]);
        }
        return $this;
    }
}
