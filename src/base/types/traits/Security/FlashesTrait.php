<?php

namespace PSFS\base\types\traits\Security;

use PSFS\base\Security;

/**
 * @package PSFS\base\types\traits\Security
 */
trait FlashesTrait
{
    use SessionTrait;

    /**
     * @return mixed
     */
    public function getFlashes()
    {
        $flashes = $this->getSessionKey(Security::FLASH_MESSAGE_TOKEN);

        return (null !== $flashes) ? $flashes : array();
    }

    /**
     * @return $this
     */
    public function clearFlashes()
    {
        $this->setSessionKey(Security::FLASH_MESSAGE_TOKEN, null);

        return $this;
    }

    /**
     *
     * @param string $key
     * @param mixed $data
     */
    public function setFlash($key, $data = null)
    {
        $flashes = $this->getFlashes();
        if (!is_array($flashes)) {
            $flashes = [];
        }
        $flashes[$key] = $data;
        $this->setSessionKey(Security::FLASH_MESSAGE_TOKEN, $flashes);
    }

    /**
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getFlash($key)
    {
        $flashes = $this->getFlashes();

        return (null !== $key && array_key_exists($key, $flashes)) ? $flashes[$key] : null;
    }
}
