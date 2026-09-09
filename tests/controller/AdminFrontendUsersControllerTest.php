<?php

namespace PSFS\tests\controller;

use PHPUnit\Framework\TestCase;
use PSFS\base\Security;
use PSFS\base\config\AdminForm;
use PSFS\controller\AdminFrontendUsersController;

class AdminFrontendUsersControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Security::setTest(true);
    }

    protected function tearDown(): void
    {
        Security::setTest(false);
        Security::dropInstance();
    }

    public function testIndexReturnsSanitizedUsersAndCreationSchema(): void
    {
        $response = json_decode((new AdminFrontendUsersControllerProbe())->index(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($response['ok'], json_encode($response));
        self::assertSame('alice', $response['data']['users'][0]['username']);
        self::assertArrayNotHasKey('password', $response['data']['users'][0]);
        self::assertSame('889a3a791b3875cfae413574b53da4bb8a90d53e', $response['data']['users'][0]['profile']);
        self::assertSame('Administrator', $response['data']['users'][0]['role']);
        self::assertArrayHasKey('username', $response['data']['form']['fields']);
        self::assertSame(['889a3a791b3875cfae413574b53da4bb8a90d53e' => 'Administrator'], $response['data']['profiles']);
    }

    public function testCreateRejectsInvalidPayloadBeforeSaving(): void
    {
        $controller = new AdminFrontendUsersControllerProbe(['values' => ['username' => '']]);
        $response = json_decode($controller->create(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $controller->statusCode);
        self::assertFalse($response['ok']);
        self::assertNotEmpty($response['errors']);
        self::assertFalse($controller->saved);
    }

    public function testDeleteRejectsInvalidPayloadBeforeDeleting(): void
    {
        $controller = new AdminFrontendUsersControllerProbe(['user' => 'bad user!']);
        $response = json_decode($controller->delete(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $controller->statusCode);
        self::assertFalse($response['ok']);
        self::assertFalse($controller->deleted);
    }

    public function testDeleteAcceptsTheUnderscoreAliasesThatUserCreationAlreadyAllows(): void
    {
        $controller = new AdminFrontendUsersControllerProbe(['user' => 'temporary_user_2026']);
        $response = json_decode($controller->delete(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($response['ok'], json_encode($response));
        self::assertTrue($controller->deleted, json_encode($response));
    }

    public function testUpdateRejectsAnAliasMismatchBeforeSaving(): void
    {
        $controller = new AdminFrontendUsersControllerProbe([
            'values' => [
                'username' => 'other-admin',
                'password' => 'new-password',
                'profile' => '889a3a791b3875cfae413574b53da4bb8a90d53e',
            ],
        ]);

        $response = json_decode($controller->update('alice'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $controller->statusCode);
        self::assertFalse($response['ok']);
        self::assertFalse($controller->saved);
    }

    public function testUpdateReusesTheExistingUserPersistenceForTheRouteAlias(): void
    {
        $controller = new AdminFrontendUsersControllerProbe([
            'values' => [
                'password' => 'new-password',
                'profile' => '889a3a791b3875cfae413574b53da4bb8a90d53e',
            ],
        ]);

        $response = json_decode($controller->update('alice'), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($response['ok'], json_encode($response));
        self::assertTrue($controller->saved);
        self::assertSame('alice', $controller->savedValues['username']);
    }
}

class AdminFrontendUsersControllerProbe extends AdminFrontendUsersController
{
    public int $statusCode = 200;
    public bool $saved = false;
    public bool $deleted = false;
    /** @var array<string,mixed> */
    public array $savedValues = [];

    /** @param array<string,mixed> $payload */
    public function __construct(private readonly array $payload = [])
    {
    }

    public function json($response, $statusCode = 200): string
    {
        $this->statusCode = $statusCode;
        return (string) json_encode($response, JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string,mixed> */
    protected function requestPayload(): array
    {
        return $this->payload;
    }

    protected function adminForm(): AdminForm
    {
        return new AdminForm();
    }

    /** @return array<string,array<string,string>> */
    protected function admins(): array
    {
        return ['alice' => ['profile' => '889a3a791b3875cfae413574b53da4bb8a90d53e', 'password' => 'must-not-leak', 'class' => 'warning']];
    }

    /** @return array<string,string> */
    protected function profiles(): array
    {
        return ['889a3a791b3875cfae413574b53da4bb8a90d53e' => 'Administrator'];
    }

    protected function saveUser(array $data): bool
    {
        $this->saved = true;
        $this->savedValues = $data;
        return true;
    }

    protected function deleteUser(string $username): void
    {
        $this->deleted = true;
    }
}
