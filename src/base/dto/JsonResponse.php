<?php
namespace PSFS\base\dto;

class JsonResponse extends Dto
{
    /**
     * Response message
     * @var string $message
     */
    public $message = 'No message';
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
    /**
     * Number of total results
     * @var int total
     */
    public $total = 0;
    /**
     * Number of pages availables
     * @var int pages
     */
    public $pages = 1;

    /**
     * @param array $data
     * @param bool|FALSE $result
     * @param int $total
     * @param int $pages
     * @param string $message
     */
    public function __construct($data = array(), $result = false, $total = null, $pages = 1, $message = null)
    {
        parent::__construct();
        $this->data = $data;
        $this->success = $result;
        $this->total = $total ?: count($data);
        $this->pages = $pages;
        if(null !== $message) {
            $this->message = $message;
        }
    }
}