<?php
namespace PSFS\test\examples;

use PSFS\base\Request;
use PSFS\base\Service;

/**
 * Class AuthServiceTest
 * @package PSFS\test\examples
 */
final class AuthServiceTest extends Service {

    public function test($user, $password) {
        $this->setUrl('https://jsonplaceholder.typicode.com/todos');
        $this->addAuthHeader($user, $password);
        $this->addRequestToken($password, 'TEST');
        $this->setDebug(true);
        $this->addOption(CURLINFO_CONTENT_TYPE, 'application/json');
        $this->setIsJson(true);
        $this->setType(Request::VERB_GET);
        $this->callSrv();
    }

}
