<?php
    namespace PSFS\base\dto;

    class JsonResponse extends Dto
    {
        /**
         * Response result
         * @var bool $success
         */
        public $success = false;
        /**
         * Data of response
         * @var array $data
         */
        public $data = null;

        public function __construct(array $data, $result)
        {
            $this->data = $data;
            $this->success = $result;
        }
    }