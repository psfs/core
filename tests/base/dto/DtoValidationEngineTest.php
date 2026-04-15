<?php

namespace PSFS\tests\base\dto;

use PHPUnit\Framework\TestCase;
use PSFS\base\dto\CsrfValidator;
use PSFS\base\dto\Dto;
use PSFS\base\types\helpers\attributes\CsrfField;
use PSFS\base\types\helpers\attributes\CsrfProtected;
use PSFS\base\types\helpers\attributes\DefaultValue;
use PSFS\base\types\helpers\attributes\Length;
use PSFS\base\types\helpers\attributes\Max;
use PSFS\base\types\helpers\attributes\Min;
use PSFS\base\types\helpers\attributes\Nullable;
use PSFS\base\types\helpers\attributes\Pattern;
use PSFS\base\types\helpers\attributes\Required;
use PSFS\base\types\helpers\attributes\Values;
use PSFS\base\types\helpers\attributes\VarType;
use PSFS\base\Request;
use PSFS\base\Security;

class DtoValidationEngineTest extends TestCase
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
        $_SERVER['REQUEST_URI'] = '/admin/setup';
        Request::dropInstance();
        Security::dropInstance();
        Request::getInstance()->init();
        Security::getInstance(true);
    }

    public function testValidateRejectsUnknownFieldsAndRuleViolations(): void
    {
        $dto = new DemoValidationDto(false);
        $dto->fromArray([
            'name' => 'A',
            'age' => 16,
            'status' => 'X',
            'unknown' => 'boom',
        ]);

        $result = $dto->validate();
        $this->assertFalse($result->isValid());
        $codes = array_column($result->getErrors(), 'code');
        $this->assertContains('unknown_field', $codes);
        $this->assertContains('invalid_length', $codes);
        $this->assertContains('min_value', $codes);
        $this->assertContains('invalid_enum', $codes);
    }

    public function testValidateAppliesDefaultsAndAcceptsValidPayload(): void
    {
        $dto = new DemoValidationDto(false);
        $dto->fromArray([
            'name' => 'Alice',
            'age' => 33,
            'status' => 'active',
        ]);

        $result = $dto->validate();
        $this->assertTrue($result->isValid());
        $this->assertSame('guest', $dto->role);
        $this->assertNull($dto->comment);
    }

    public function testCsrfProtectedDtoValidatesTokenAndRejectsReplay(): void
    {
        $tokens = CsrfValidator::issueToken('dto_csrf_demo');

        $dto = new DemoCsrfDto(false);
        $dto->fromArray([
            'user' => 'admin',
            '_csrf' => $tokens['token'],
            '_csrf_key' => $tokens['key'],
        ]);
        $first = $dto->validate();
        $this->assertTrue($first->isValid());

        $dtoReplay = new DemoCsrfDto(false);
        $dtoReplay->fromArray([
            'user' => 'admin',
            '_csrf' => $tokens['token'],
            '_csrf_key' => $tokens['key'],
        ]);
        $replay = $dtoReplay->validate();
        $this->assertFalse($replay->isValid());
        $this->assertContains('invalid_csrf', array_column($replay->getErrors(), 'code'));
    }
}

class DemoValidationDto extends Dto
{
    #[Required]
    #[VarType('string')]
    #[Length(min: 2, max: 60)]
    #[Pattern('/^[A-Za-z ]+$/')]
    public ?string $name = null;

    #[Required]
    #[VarType('int')]
    #[Min(18)]
    #[Max(120)]
    public ?int $age = null;

    #[Values(['active', 'inactive'])]
    public ?string $status = null;

    #[DefaultValue('guest')]
    public ?string $role = null;

    #[Nullable]
    public ?string $comment = null;
}

#[CsrfProtected(formKey: 'dto_csrf_demo')]
#[CsrfField('_csrf', '_csrf_key')]
class DemoCsrfDto extends Dto
{
    #[Required]
    #[VarType('string')]
    public ?string $user = null;
}

