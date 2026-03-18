<?php

namespace PSFS\base\dto;

class JsonResponse extends Dto
{
    /**
     * @var string
     */
    public $message = 'No message';
    /**
     * @var bool
     */
    public $success = false;
    /**
     * @var array
     */
    public $data = null;
    /**
     * @var int
     */
    public $total = 0;
    /**
     * @var int
     */
    public $pages = 1;

    private function parseData($data)
    {
        if ($data instanceof \Generator) {
            $generatedData = [];
            foreach ($data as $datum) {
                $generatedData[] = $datum;
            }
        } else {
            $generatedData = $data;
        }
        return $generatedData;
    }

    /**
     * @param array $data
     * @param bool $result
     * @param integer $total
     * @param int $pages
     * @param string $message
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function __construct($data = [], $result = false, $total = null, $pages = 1, $message = null)
    {
        parent::__construct(false);
        $this->data = $this->parseData($data);
        $this->success = $result;
        $this->total = $total ?: (is_array($this->data) ? count($this->data) : ($total ?? 0));
        $this->pages = $pages;
        if (null !== $message) {
            $this->message = $message;
        }
    }
}
