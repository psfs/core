<?php

namespace PSFS\tests\examples;

use PSFS\base\Security;
use PSFS\base\Singleton;

class SingletonClassTestExample extends Singleton
{
    /**
     * @Injectable
     * @var \PSFS\base\Security $security
     */
    protected $security;
    /**
     * @Injectable
     * @var \PSFS\tests\examples\NonSingletonClassTestExample $testClass
     */
    protected $testClass;
    /**
     * @var string fieldTest
     */
    protected $fieldTest;
    /**
     * @var integer
     */
    public $publicVariable;
    /**
     * @var integer
     */
    private $privateVariable;
    /**
     * @var string
     */
    public static $staticVariable;

    public function init()
    {
        parent::init();
    }

    public function setSecurity(Security $security)
    {
        $this->security = $security;
    }
}