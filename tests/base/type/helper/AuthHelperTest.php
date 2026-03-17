<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\AuthHelper;

class AuthHelperTest extends TestCase {
    const TEXT_EXAMPLE = 'Lorem fistrum me cago en tus muelas no puedor a gramenawer. Diodenoo papaar papaar pupita a peich. Al ataquerl sexuarl diodenoo apetecan está la cosa muy malar se calle ustée te voy a borrar el cerito ese hombree al ataquerl. Ese que llega a peich a gramenawer amatomaa va usté muy cargadoo pecador la caidita condemor ese hombree tiene musho peligro amatomaa. Qué dise usteer se calle ustée por la gloria de mi madre llevame al sircoo hasta luego Lucas llevame al sircoo.';
    public function testEncryptionFunctions() {
        $key = uniqid();
        $encrypted_data = AuthHelper::encrypt(self::TEXT_EXAMPLE, $key);
        $this->assertNotEquals(self::TEXT_EXAMPLE, $encrypted_data, 'Same original data than encrypted');
        $this->assertNotEquals($key, $encrypted_data, 'Same encrypted data as key');
        $this->assertStringStartsWith(AuthHelper::CRYPTO_VERSION_PREFIX, $encrypted_data);
        $decrypted_data = AuthHelper::decrypt($encrypted_data, $key);
        $this->assertEquals(self::TEXT_EXAMPLE, $decrypted_data, 'Something happens when decrypt the data');
    }

    public function testLegacyEncryptionBackwardCompatibility(): void
    {
        $key = uniqid();
        $encryptedData = self::legacyEncrypt(self::TEXT_EXAMPLE, $key);
        $decryptedData = AuthHelper::decrypt($encryptedData, $key);
        $this->assertEquals(self::TEXT_EXAMPLE, $decryptedData, 'Legacy encrypted payload is no longer supported');
    }

    public function testTokenGeneration() {
        $fake_user = 'test_user';
        $fake_password = uniqid();
        $hash_password = sha1($fake_user . $fake_password);
        // Generating a first encrypted token
        $first_token = AuthHelper::generateToken($fake_user, $fake_password);
        $this->assertNotEmpty($first_token, 'Something wrong generating');
        list($decoded_user, $decoded_timestamp, $decoded_user_agent) = AuthHelper::decodeToken($first_token, $hash_password);
        $this->assertEquals($fake_user, $decoded_user, 'Something wrong decoding');
        $this->assertNotEmpty($decoded_user_agent, 'Missing decoded user agent');
        $datetime = \DateTime::createFromFormat(AuthHelper::EXPIRATION_TIMESTAMP_FORMAT, $decoded_timestamp);
        $this->assertNotFalse($datetime, 'Wrong decoded timestamp');

        // Generating a second encrypted token, but waiting 1 second...
        sleep(1);
        $second_token = AuthHelper::generateToken($fake_user, $fake_password);
        // The new token has to be different than the first one
        $this->assertNotEquals($first_token, $second_token, 'Wrong generation token');
    }

    private static function legacyEncrypt(string $data, string $key): string
    {
        $data = base64_encode($data);
        $encryptedData = '';
        for ($i = 0, $j = 0, $iMax = strlen($data); $i < $iMax; $i++, $j++) {
            if ($j === strlen($key)) {
                $j = 0;
            }
            $encryptedData .= $data[$i] ^ $key[$j];
        }
        return base64_encode($encryptedData);
    }
}
