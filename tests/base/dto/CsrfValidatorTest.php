<?php

namespace PSFS\tests\base\dto;

use PHPUnit\Framework\TestCase;
use PSFS\base\dto\CsrfValidator;
use PSFS\base\Request;
use PSFS\base\Security;

class CsrfValidatorTest extends TestCase
{
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
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Request::dropInstance();
        Security::dropInstance();
        Request::getInstance()->init();
        Security::getInstance(true);
    }

    public function testIssueAndValidateSubmissionWithOneTimeToken(): void
    {
        $tokens = CsrfValidator::issueToken('csrf_validator');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $tokens['token']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $tokens['key']);

        $this->assertTrue(CsrfValidator::validateSubmission($tokens['token'], $tokens['key'], 'csrf_validator'));
        $this->assertFalse(CsrfValidator::validateSubmission($tokens['token'], $tokens['key'], 'csrf_validator'));
    }

    public function testRejectsWrongFormAndMalformedPayload(): void
    {
        $tokens = CsrfValidator::issueToken('csrf_validator_wrong_form');
        $this->assertFalse(CsrfValidator::validateSubmission($tokens['token'], $tokens['key'], 'another_form'));
        $this->assertFalse(CsrfValidator::validateSubmission('invalid token', $tokens['key'], 'csrf_validator_wrong_form'));
    }
}

