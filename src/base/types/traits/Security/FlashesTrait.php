<?php

namespace PSFS\base\types\traits\Security;

/**
 * Trait FlashesTrait
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
        $flashes = $this->getSessionKey(self::FLASH_MESSAGE_TOKEN);

        return (NULL !== $flashes) ? $flashes : array();
    }

    /**
     * @return $this
     */
    public function clearFlashes()
    {
        $this->setSessionKey(self::FLASH_MESSAGE_TOKEN, NULL);

        return $this;
    }

    /**
     *
     * @param string $key
     * @param mixed $data
     */
    public function setFlash($key, $data = NULL)
    {
        $flashes = $this->getFlashes();
        if (!is_array($flashes)) {
            $flashes = [];
        }
        $flashes[$key] = $data;
        $this->setSessionKey(self::FLASH_MESSAGE_TOKEN, $flashes);
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

        return (NULL !== $key && array_key_exists($key, $flashes)) ? $flashes[$key] : NULL;
    }
}
