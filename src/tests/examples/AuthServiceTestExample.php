<?php

namespace PSFS\tests\examples;

use PSFS\base\Request;
use PSFS\base\Service;

/**
 * Class AuthServiceTest
 * @package PSFS\tests\examples
 */
class AuthServiceTestExample extends Service
{

    public function test($user, $password)
    {
        $this->setUrl('https://jsonplaceholder.typicode.com/todos');
        $this->addAuthHeader($user, $password);
        $this->addRequestToken($password, 'TEST');
        $this->setDebug(true);
        $this->setIsJson();
        $this->setType(Request::VERB_GET);
        $this->callSrv();
    }

}
