<?php

namespace PSFS\tests\base\type\helper;

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\types\helpers\AuthHelper;

class AuthHelperTest extends TestCase
{
    const TEXT_EXAMPLE = 'Long sample text for encryption round-trip checks. This payload intentionally includes enough entropy and length to validate compatibility between modern and legacy crypto paths.';

    private array $serverBackup = [];
    private array $cookieBackup = [];
    private array $getBackup = [];
    private array $requestBackup = [];
    private array $filesBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->cookieBackup = $_COOKIE;
        $this->getBackup = $_GET;
        $this->requestBackup = $_REQUEST;
        $this->filesBackup = $_FILES;
        AuthHelper::resetLegacyFallbackTelemetry();
        $this->bootstrapRequest();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_COOKIE = $this->cookieBackup;
        $_GET = $this->getBackup;
        $_REQUEST = $this->requestBackup;
        $_FILES = $this->filesBackup;
        Request::dropInstance();
        AuthHelper::resetLegacyFallbackTelemetry();
    }

    public function testEncryptionFunctions(): void
    {
        $key = uniqid('', true);
        $encryptedData = AuthHelper::encrypt(self::TEXT_EXAMPLE, $key);
        $this->assertNotEquals(self::TEXT_EXAMPLE, $encryptedData, 'Same original data than encrypted');
        $this->assertNotEquals($key, $encryptedData, 'Same encrypted data as key');
        $this->assertStringStartsWith(AuthHelper::CRYPTO_VERSION_PREFIX, $encryptedData);
        $decryptedData = AuthHelper::decrypt($encryptedData, $key);
        $this->assertEquals(self::TEXT_EXAMPLE, $decryptedData, 'Something happens when decrypt the data');
    }

    public function testLegacyEncryptionBackwardCompatibility(): void
    {
        $key = uniqid('', true);
        $encryptedData = self::legacyEncrypt(self::TEXT_EXAMPLE, $key);
        $decryptedData = AuthHelper::decrypt($encryptedData, $key);
        $this->assertEquals(self::TEXT_EXAMPLE, $decryptedData, 'Legacy encrypted payload is no longer supported');
    }

    public function testTokenGeneration(): void
    {
        $fakeUser = 'test_user';
        $fakePassword = uniqid('', true);
        $hashPassword = sha1($fakeUser . $fakePassword);
        $firstToken = AuthHelper::generateToken($fakeUser, $fakePassword);
        $this->assertNotEmpty($firstToken, 'Something wrong generating');
        [$decodedUser, $decodedTimestamp, $decodedUserAgent] = AuthHelper::decodeToken($firstToken, $hashPassword);
        $this->assertEquals($fakeUser, $decodedUser, 'Something wrong decoding');
        $this->assertNotEmpty($decodedUserAgent, 'Missing decoded user agent');
        $datetime = \DateTime::createFromFormat(AuthHelper::EXPIRATION_TIMESTAMP_FORMAT, $decodedTimestamp);
        $this->assertNotFalse($datetime, 'Wrong decoded timestamp');

        sleep(1);
        $secondToken = AuthHelper::generateToken($fakeUser, $fakePassword);
        $this->assertNotEquals($firstToken, $secondToken, 'Wrong generation token');
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

    public function testLegacyFallbackTelemetryForTokenDecode(): void
    {
        $fakeUser = 'legacy_telemetry_user';
        $fakePassword = uniqid('pwd', true);
        $hashPassword = sha1($fakeUser . $fakePassword);
        $timestamp = (new \DateTime('now', new \DateTimeZone('UTC')))->format(AuthHelper::EXPIRATION_TIMESTAMP_FORMAT);
        $legacySecret = $fakeUser . Security::LOGGED_USER_TOKEN . $timestamp . Security::LOGGED_USER_TOKEN . 'psfs';
        $legacyToken = self::legacyEncrypt($legacySecret, $hashPassword);

        AuthHelper::decodeToken($legacyToken, $hashPassword);

        $telemetry = AuthHelper::getLegacyFallbackTelemetry();
        $this->assertArrayHasKey('token_payload_delimited', $telemetry);
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

    public function testCheckBasicAuthPrefersHeaderCredentialsOverCookieFallback(): void
    {
        $headerUser = 'header_user';
        $headerPass = 'header_pass';
        $cookieUser = 'cookie_user';
        $cookiePass = 'cookie_pass';
        $this->bootstrapRequest(
            [
                'PHP_AUTH_USER' => $headerUser,
                'PHP_AUTH_PW' => $headerPass,
            ],
            [
                AuthHelper::generateProfileHash() => AuthHelper::encrypt($cookieUser . ':' . $cookiePass, AuthHelper::SESSION_TOKEN),
            ]
        );

        $admins = [
            $headerUser => ['hash' => sha1($headerUser . $headerPass)],
            $cookieUser => ['hash' => sha1($cookieUser . $cookiePass)],
        ];

        [$user, $token] = AuthHelper::checkBasicAuth(null, null, $admins);
        $this->assertSame($headerUser, $user);
        $this->assertSame(sha1($headerUser . $headerPass), $token);
    }

    public function testCheckBasicAuthReadsLegacyCookieFallbackWithAdminToken(): void
    {
        $cookieUser = 'legacy_cookie_user';
        $cookiePass = 'legacy_cookie_pass';
        $this->bootstrapRequest([], [
            AuthHelper::generateProfileHash() => AuthHelper::encrypt($cookieUser . ':' . $cookiePass, AuthHelper::ADMIN_ID_TOKEN),
        ]);

        [$user, $hash] = AuthHelper::checkBasicAuth(null, null, [
            $cookieUser => ['hash' => sha1($cookieUser . $cookiePass)],
        ]);

        $this->assertSame($cookieUser, $user);
        $this->assertSame(sha1($cookieUser . $cookiePass), $hash);
        $telemetry = AuthHelper::getLegacyFallbackTelemetry();
        $this->assertArrayHasKey('cookie_key_admin_token', $telemetry);
    }

    public function testCheckComplexAuthRejectsMalformedAuthorizationHeader(): void
    {
        $this->bootstrapRequest([
            'HTTP_AUTHORIZATION' => 'Token malformed',
            'HTTP_USER_AGENT' => 'phpunit-auth-helper',
        ]);
        [$user, $token] = AuthHelper::checkComplexAuth([
            'admin' => ['hash' => sha1('admin:secret')],
        ]);
        $this->assertNull($user);
        $this->assertNull($token);
    }

    public function testDecodeTokenInvalidPayloadReturnsNullTuple(): void
    {
        [$user, $timestamp, $userAgent] = AuthHelper::decodeToken('not_a_valid_token', 'invalid_key');
        $this->assertNull($user);
        $this->assertNull($timestamp);
        $this->assertNull($userAgent);
    }

    public function testCheckJwtAuthInvalidBearerReturnsNullTuple(): void
    {
        $this->bootstrapRequest([
            'HTTP_AUTHORIZATION' => 'Bearer invalid.jwt.token',
        ]);
        [$user, $hash] = AuthHelper::checkJwtAuth([
            'admin' => ['hash' => sha1('admin:secret')],
        ]);
        $this->assertNull($user);
        $this->assertNull($hash);
    }

    public function testCheckComplexAuthReturnsAdminForValidToken(): void
    {
        $admin = 'complex_admin';
        $hash = sha1($admin . 'secret');
        $exp = (new \DateTime('now'))->modify('+10 minutes')->format(AuthHelper::EXPIRATION_TIMESTAMP_FORMAT);
        $token = AuthHelper::encrypt(json_encode([
            'sub' => $admin,
            'exp' => $exp,
            'ua' => 'phpunit-auth-helper',
        ]), $hash);

        $this->bootstrapRequest([
            'HTTP_AUTHORIZATION' => 'Basic ' . $token,
            'HTTP_USER_AGENT' => 'phpunit-auth-helper',
        ]);
        [$user, $tokenHash] = AuthHelper::checkComplexAuth([
            $admin => ['hash' => $hash],
        ]);

        $this->assertSame($admin, $user);
        $this->assertSame($hash, $tokenHash);
    }

    public function testCheckComplexAuthHandlesInvalidExpirationTimestamp(): void
    {
        $admin = 'complex_invalid_exp';
        $hash = sha1($admin . 'secret');
        $token = AuthHelper::encrypt(json_encode([
            'sub' => $admin,
            'exp' => 'invalid-exp',
            'ua' => 'phpunit-auth-helper',
        ]), $hash);

        $this->bootstrapRequest([
            'HTTP_AUTHORIZATION' => 'Basic ' . $token,
            'HTTP_USER_AGENT' => 'phpunit-auth-helper',
        ]);
        [$user, $tokenHash] = AuthHelper::checkComplexAuth([
            $admin => ['hash' => $hash],
        ]);

        $this->assertNull($user);
        $this->assertNull($tokenHash);
    }

    public function testDecodeTokenCoversNonArrayJsonAndUnknownFormats(): void
    {
        $key = 'decode_key';
        [$u1, $t1, $ua1] = AuthHelper::decodeToken(AuthHelper::encrypt('true', $key), $key);
        $this->assertNull($u1);
        $this->assertNull($t1);
        $this->assertNull($ua1);

        [$u2, $t2, $ua2] = AuthHelper::decodeToken(AuthHelper::encrypt('plain-secret', $key), $key);
        $this->assertNull($u2);
        $this->assertNull($t2);
        $this->assertNull($ua2);
    }

    public function testCheckJwtAuthCoversMissingSubjectAndHashCases(): void
    {
        $jwtKey = str_repeat('k', 32);
        $token = JWT::encode(['sub' => 'jwt-user', 'iat' => time() - 5, 'exp' => time() + 30], $jwtKey, 'HS256');

        $this->bootstrapRequest(['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        [$user, $hash] = AuthHelper::checkJwtAuth(['other-user' => ['hash' => $jwtKey]]);
        $this->assertNull($user);
        $this->assertNull($hash);

        [$user, $hash] = AuthHelper::checkJwtAuth(['jwt-user' => ['hash' => '']]);
        $this->assertNull($user);
        $this->assertNull($hash);
    }

    public function testCheckJwtAuthCoversNotYetValidAndExpiredBranches(): void
    {
        $subject = 'jwt-timing-user';
        $secret = sha1($subject . ':pwd');

        $futureToken = JWT::encode([
            'sub' => $subject,
            'iat' => time() + 3600,
            'exp' => time() + 7200,
        ], $secret, 'HS256');
        $this->bootstrapRequest(['HTTP_AUTHORIZATION' => 'Bearer ' . $futureToken]);
        [$userFuture, $hashFuture] = AuthHelper::checkJwtAuth([$subject => ['hash' => $secret]]);
        $this->assertNull($userFuture);
        $this->assertNull($hashFuture);

        $expiredToken = JWT::encode([
            'sub' => $subject,
            'iat' => time() - 7200,
            'exp' => time() - 3600,
        ], $secret, 'HS256');
        $this->bootstrapRequest(['HTTP_AUTHORIZATION' => 'Bearer ' . $expiredToken]);
        [$userExpired, $hashExpired] = AuthHelper::checkJwtAuth([$subject => ['hash' => $secret]]);
        $this->assertNull($userExpired);
        $this->assertNull($hashExpired);
    }

    public function testAuthFlowPrecedenceBasicThenCookieThenJwt(): void
    {
        $basicUser = 'basic_user';
        $basicPass = 'basic_pass';
        $cookieUser = 'cookie_user';
        $cookiePass = 'cookie_pass';
        $jwtUser = 'jwt_user';
        $jwtSecret = sha1($jwtUser . '_secret');
        $jwtToken = $this->createJwtToken($jwtUser, $jwtSecret);
        $admins = [
            $basicUser => ['hash' => sha1($basicUser . $basicPass)],
            $cookieUser => ['hash' => sha1($cookieUser . $cookiePass)],
            $jwtUser => ['hash' => $jwtSecret],
        ];

        $this->bootstrapRequest(
            [
                'PHP_AUTH_USER' => $basicUser,
                'PHP_AUTH_PW' => $basicPass,
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken,
            ],
            [
                AuthHelper::generateProfileHash() => AuthHelper::encrypt($cookieUser . ':' . $cookiePass, AuthHelper::SESSION_TOKEN),
            ]
        );
        [$user] = $this->resolveAuthFlowLikeSecurity($admins);
        $this->assertSame($basicUser, $user);

        $this->bootstrapRequest(
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken,
            ],
            [
                AuthHelper::generateProfileHash() => AuthHelper::encrypt($cookieUser . ':' . $cookiePass, AuthHelper::SESSION_TOKEN),
            ]
        );
        [$user] = $this->resolveAuthFlowLikeSecurity($admins);
        $this->assertSame($cookieUser, $user);

        $this->bootstrapRequest([
            'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken,
        ]);
        [$user] = $this->resolveAuthFlowLikeSecurity($admins);
        $this->assertSame($jwtUser, $user);
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

    private function bootstrapRequest(array $server = [], array $cookie = []): void
    {
        $_SERVER = array_merge([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'localhost:8080',
            'HTTP_USER_AGENT' => 'phpunit-auth-helper',
        ], $server);
        $_COOKIE = $cookie;
        $_GET = [];
        $_REQUEST = [];
        $_FILES = [];
        Request::dropInstance();
        Request::getInstance()->init();
    }

    private function resolveAuthFlowLikeSecurity(array $admins): array
    {
        [$user, $token] = AuthHelper::checkBasicAuth(null, null, $admins);
        if (empty($user)) {
            [$user, $token] = AuthHelper::checkComplexAuth($admins);
        }
        if (empty($user)) {
            [$user, $token] = AuthHelper::checkJwtAuth($admins);
        }
        return [$user, $token];
    }

    private function createJwtToken(string $subject, string $secret): string
    {
        $now = time();
        $payload = [
            'sub' => $subject,
            'iat' => $now - 5,
            'exp' => $now + 600,
        ];
        return JWT::encode($payload, $secret, 'HS256');
    }
}
