<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\types\Form;

class FormSecurityTraitTestForm extends Form
{
    public function getName()
    {
        return 'csrf_test_form';
    }
}

/**
 * @runInSeparateProcess
 */
class FormSecurityTraitTest extends TestCase
{
    private const CSRF_SESSION_TOKEN_KEY = '__PSFS_CSRF_FORM_TOKENS__';
    private const TOKEN_KEY_FIELD = 'csrf_test_form_token_key';

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        global $_SESSION;
        $_SESSION = [];
        $_REQUEST = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        Security::dropInstance();
        Request::dropInstance();
        Request::getInstance()->init();
        Security::getInstance(true);
    }

    public function testGeneratesRandomTokenAndStoresSessionMetadata(): void
    {
        $formA = new FormSecurityTraitTestForm();
        $formA->add('email', ['type' => 'text']);
        $formA->build();
        $tokenA = $formA->getField('csrf_test_form_token')['value'] ?? '';
        $tokenKeyA = $formA->getField(self::TOKEN_KEY_FIELD)['value'] ?? '';

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $tokenA);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $formA->getField(self::TOKEN_KEY_FIELD)['value'] ?? '');

        $stored = Security::getInstance()->getSessionKey(self::CSRF_SESSION_TOKEN_KEY);
        $this->assertIsArray($stored);
        $this->assertArrayHasKey($tokenKeyA, $stored);
        $entry = $stored[$tokenKeyA];
        $this->assertEquals($tokenA, $entry['token'] ?? '');
        $this->assertGreaterThan(time(), (int)($entry['expires_at'] ?? 0));
        $this->assertEquals('csrf_test_form', $entry['form'] ?? '');

        $formB = new FormSecurityTraitTestForm();
        $formB->add('email', ['type' => 'text']);
        $formB->build();
        $tokenB = $formB->getField('csrf_test_form_token')['value'] ?? '';
        $this->assertNotEquals($tokenA, $tokenB);
    }

    public function testBuildPersistsTokenIntoPhpSessionImmediately(): void
    {
        $form = new FormSecurityTraitTestForm();
        $form->add('email', ['type' => 'text']);
        $form->build();
        $token = $form->getField('csrf_test_form_token')['value'] ?? '';
        $tokenKey = $form->getField(self::TOKEN_KEY_FIELD)['value'] ?? '';

        $this->assertArrayHasKey(self::CSRF_SESSION_TOKEN_KEY, $_SESSION);
        $this->assertArrayHasKey($tokenKey, $_SESSION[self::CSRF_SESSION_TOKEN_KEY]);
        $this->assertEquals($token, $_SESSION[self::CSRF_SESSION_TOKEN_KEY][$tokenKey]['token'] ?? '');
    }

    public function testAcceptsValidTokenAndRejectsReplay(): void
    {
        $form = new FormSecurityTraitTestForm();
        $form->add('email', ['type' => 'text']);
        $form->build();
        $token = $form->getField('csrf_test_form_token')['value'] ?? '';
        $tokenKey = $form->getField(self::TOKEN_KEY_FIELD)['value'] ?? '';

        $form->setData([
            'csrf_test_form_token' => $token,
            self::TOKEN_KEY_FIELD => $tokenKey,
            'email' => 'test@example.com',
        ]);
        $this->assertTrue($form->isValid());
        $this->assertFalse($form->isValid());
    }

    public function testBuildKeepsSubmittedTokenOnPostFlow(): void
    {
        $form = new FormSecurityTraitTestForm();
        $form->add('email', ['type' => 'text']);
        $form->build();
        $token = $form->getField('csrf_test_form_token')['value'] ?? '';
        $tokenKey = $form->getField(self::TOKEN_KEY_FIELD)['value'] ?? '';

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_REQUEST = [
            'csrf_test_form' => [
                'csrf_test_form_token' => $token,
                self::TOKEN_KEY_FIELD => $tokenKey,
                'email' => 'post@example.com',
            ],
        ];

        Request::dropInstance();
        Request::getInstance()->init();

        $postForm = new FormSecurityTraitTestForm();
        $postForm->add('email', ['type' => 'text']);
        $postForm->build();
        $postToken = $postForm->getField('csrf_test_form_token')['value'] ?? '';
        $postTokenKey = $postForm->getField(self::TOKEN_KEY_FIELD)['value'] ?? '';
        $this->assertEquals($token, $postToken);
        $this->assertEquals($tokenKey, $postTokenKey);

        $postForm->hydrate();
        $this->assertTrue($postForm->isValid());
    }

    public function testExpiredTokenIsRejected(): void
    {
        $form = new FormSecurityTraitTestForm();
        $form->add('email', ['type' => 'text']);
        $form->build();
        $token = $form->getField('csrf_test_form_token')['value'] ?? '';
        $tokenKey = $form->getField(self::TOKEN_KEY_FIELD)['value'] ?? '';

        Security::getInstance()->setSessionKey(self::CSRF_SESSION_TOKEN_KEY, [
            $tokenKey => [
                'token' => $token,
                'expires_at' => time() - 10,
                'form' => 'csrf_test_form',
            ],
        ]);

        $form->setData([
            'csrf_test_form_token' => $token,
            self::TOKEN_KEY_FIELD => $tokenKey,
            'email' => 'expired@example.com',
        ]);
        $this->assertFalse($form->isValid());
    }
}
