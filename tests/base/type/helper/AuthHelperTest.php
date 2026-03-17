<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\base\Security;

class AuthHelperTest extends TestCase {
    const TEXT_EXAMPLE = 'Lorem fistrum me cago en tus muelas no puedor a gramenawer. Diodenoo papaar papaar pupita a peich. Al ataquerl sexuarl diodenoo apetecan está la cosa muy malar se calle ustée te voy a borrar el cerito ese hombree al ataquerl. Ese que llega a peich a gramenawer amatomaa va usté muy cargadoo pecador la caidita condemor ese hombree tiene musho peligro amatomaa. Qué dise usteer se calle ustée por la gloria de mi madre llevame al sircoo hasta luego Lucas llevame al sircoo.';
    public function testEncryptionFunctions() {
        $key = uniqid('', true);
        $encrypted_data = AuthHelper::encrypt(self::TEXT_EXAMPLE, $key);
        $this->assertNotEquals(self::TEXT_EXAMPLE, $encrypted_data, 'Same original data than encrypted');
        $this->assertNotEquals($key, $encrypted_data, 'Same encrypted data as key');
        $this->assertStringStartsWith(AuthHelper::CRYPTO_VERSION_PREFIX, $encrypted_data);
        $decrypted_data = AuthHelper::decrypt($encrypted_data, $key);
        $this->assertEquals(self::TEXT_EXAMPLE, $decrypted_data, 'Something happens when decrypt the data');
    }

    public function testLegacyEncryptionBackwardCompatibility(): void
    {
        $key = uniqid('', true);
        $encryptedData = self::legacyEncrypt(self::TEXT_EXAMPLE, $key);
        $decryptedData = AuthHelper::decrypt($encryptedData, $key);
        $this->assertEquals(self::TEXT_EXAMPLE, $decryptedData, 'Legacy encrypted payload is no longer supported');
    }

    public function testTokenGeneration() {
        $fake_user = 'test_user';
        $fake_password = uniqid('', true);
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

    public function testLegacyTokenBackwardCompatibility(): void
    {
        $fakeUser = 'legacy_user';
        $fakePassword = uniqid('pwd', true);
        $hashPassword = sha1($fakeUser . $fakePassword);
        $timestamp = (new \DateTime('now', new \DateTimeZone('UTC')))->format(AuthHelper::EXPIRATION_TIMESTAMP_FORMAT);
        $legacySecret = $fakeUser . Security::LOGGED_USER_TOKEN . $timestamp . Security::LOGGED_USER_TOKEN . 'psfs';
        $legacyToken = self::legacyEncrypt($legacySecret, $hashPassword);

        [$decodedUser, $decodedTimestamp, $decodedUserAgent] = AuthHelper::decodeToken($legacyToken, $hashPassword);
        $this->assertEquals($fakeUser, $decodedUser);
        $this->assertEquals($timestamp, $decodedTimestamp);
        $this->assertEquals('psfs', $decodedUserAgent);
    }

    public function testCheckBasicAuthSupportsLegacyAndModernHashes(): void
    {
        $user = 'compat_user';
        $pass = 'compat_pass';
        $legacyHash = sha1($user . $pass);
        [$legacyUser, $legacyToken] = AuthHelper::checkBasicAuth($user, $pass, [
            $user => ['hash' => $legacyHash],
        ]);
        $this->assertEquals($user, $legacyUser);
        $this->assertEquals($legacyHash, $legacyToken);

        $modernHash = password_hash($user . $pass, PASSWORD_BCRYPT);
        [$modernUser, $modernToken] = AuthHelper::checkBasicAuth($user, $pass, [
            $user => ['hash' => $modernHash],
        ]);
        $this->assertEquals($user, $modernUser);
        $this->assertEquals($modernHash, $modernToken);
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
