<?php
namespace PSFS\test\examples;

use PSFS\base\Security;
use PSFS\base\Singleton;

class SingletonClassTest extends Singleton {
    /**
     * @Inyectable
     * @var \PSFS\base\Security $security
     */
    protected $security;
    /**
     * @Inyectable
     * @var \PSFS\test\examples\NonSingletonClassTest $testClass
     */
    protected $testClass;
    /**
     * @var string fieldTest
     */
    protected $fieldTest;

    public function init() {
        parent::init();
    }

    public function setSecurity(Security $security) {
        $this->security = $security;
    }
}