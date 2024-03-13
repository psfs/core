<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\SecurityHelper;

class SecurityHelperTest extends TestCase
{
    /**
     * @param string|null $secretOK
     * @param string|null $secretKO
     * @param string|null $moduleOK
     * @param string|null $moduleKO
     */
    #[DataProvider('getBatteryTest')]
    public function testToken(string $secretOK = null, string $secretKO = null, string $moduleOK = null, string $moduleKO = null)
    {
        $secretOK = $secretOK ?: uniqid('ok', false);
        $secretKO = $secretKO ?: uniqid('fail', false);
        $moduleOK = $moduleOK ?: 'testOK';
        $moduleKO = $moduleKO ?: 'testKO';

        $token = SecurityHelper::generateToken($secretOK, $moduleOK);
        $this->assertNotNull($token, 'Something happens trying to generate a new token');
        $this->assertNotEmpty($token, 'The token is empty');

        $this->assertTrue(SecurityHelper::checkToken($token, $secretOK, $moduleOK), 'Verification OK failed');
        $this->assertFalse(SecurityHelper::checkToken($token, $secretOK, $moduleKO), 'Verification KO with different module failed');
        $this->assertFalse(SecurityHelper::checkToken($token, $secretKO, $moduleOK), 'Verification KO with different secret failed');
        $this->assertFalse(SecurityHelper::checkToken($token, $secretKO, $moduleKO), 'Verification KO with different secret and module failed');
    }

    /**
     * @return array
     */
    public static function getBatteryTest(): array
    {
        $batch = [];
        for ($i = 0, $ct = rand(10, 100); $i < $ct; $i++) {
            $batch[] = [
                uniqid('ok'),
                uniqid('fail'),
                uniqid('testOK'),
                uniqid('testKO'),
            ];
        }
        return $batch;
    }
}
