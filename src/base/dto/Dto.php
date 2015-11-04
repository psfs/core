<?php
    namespace PSFS\base\dto;

    use PSFS\base\Singleton;

    /**
     * Class Dto
     * @package PSFS\base\dto
     */
    class Dto extends Singleton
    {
        /**
         * Convert dto to array representation
         * @return array
         */
        public function __toArray()
        {
            $dto = array();
            $reflectionClass = new \ReflectionClass($this);
            $properties = $reflectionClass->getProperties();
            if(count($properties) > 0) {
                /** @var \ReflectionProperty $property */
                foreach($properties as $property) {
                    $dto[$property->getName()] = $property->getValue($this);
                }
            }
            return $dto;
        }

        /**
         * Convert to string representation
         * @return string
         */
        public function __toString()
        {
            return get_class($this);
        }

        /**
         * Hydrate object from array
         * @param array $object
         */
        public function fromArray(array $object = array())
        {
            if(count($object) > 0) {
                foreach($object as $key => $value) {
                    if(property_exists($this, $key)) {
                        $this->$key = $value;
                    }
                }
            }
        }
    }