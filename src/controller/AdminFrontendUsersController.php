<?php

namespace PSFS\controller;

use PSFS\base\Security;
use PSFS\base\admin\AdminApiResponse;
use PSFS\base\admin\AdminFrontendCsrf;
use PSFS\base\admin\AdminFormSchemaFactory;
use PSFS\base\config\AdminForm;
use PSFS\base\Request;
use PSFS\base\exception\ApiException;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\Visible;
use PSFS\controller\base\Admin;
use PSFS\services\AdminServices;

/** JSON-only user-management contract consumed by the native Admin 2.0 screen. */
class AdminFrontendUsersController extends Admin
{
    #[HttpMethod('GET')]
    #[Route('/admin/api/v2/users')]
    #[Visible(false)]
    public function index(): string
    {
        $form = $this->adminForm();
        $form->build();

        return $this->json(AdminApiResponse::success([
            'users' => $this->sanitizeUsers($this->admins(), $this->profiles()),
            'form' => (new AdminFormSchemaFactory())->fromForm($form),
            'profiles' => $this->profiles(),
        ]));
    }

    #[HttpMethod('POST')]
    #[Route('/admin/api/v2/users')]
    #[Visible(false)]
    public function create(): string
    {
        AdminFrontendCsrf::assertValid();
        $this->assertSuperAdminWriteAccess();
        $payload = $this->requestPayload();
        $values = $payload['values'] ?? null;
        if (!$this->isRecord($values) || $values === []) {
            return $this->json(AdminApiResponse::failure(t('Invalid user payload'), [
                'payload' => [t('Expected a values object')],
            ]), 422);
        }

        $form = $this->adminForm();
        $form->setMethod('POST')->build();
        $form->setData($values);
        $errors = $this->requiredFieldErrors($values);
        if ($errors !== [] || !$form->isValid()) {
            return $this->json(AdminApiResponse::failure(
                t('Invalid user'),
                $errors + $this->fieldErrors($form)
            ), 422);
        }

        if (!$this->saveUser($form->getData())) {
            return $this->json(AdminApiResponse::failure(
                t('Error while saving administrators, please verify filesystem permissions')
            ), 500);
        }

        return $this->json(AdminApiResponse::success([], t('User created successfully')));
    }

    #[HttpMethod('DELETE')]
    #[Route('/admin/api/v2/users')]
    #[Visible(false)]
    public function delete(): string
    {
        AdminFrontendCsrf::assertValid();
        $this->assertSuperAdminWriteAccess();
        $payload = $this->requestPayload();
        $username = $payload['user'] ?? null;
        if (!is_string($username) || !$this->isValidUsername($username)) {
            return $this->json(AdminApiResponse::failure(
                t('Invalid request payload'),
                ['user' => [t('Invalid request payload')]]
            ), 422);
        }

        $this->deleteUser($username);
        return $this->json(AdminApiResponse::success([], t('User deleted successfully')));
    }

    protected function adminForm(): AdminForm
    {
        return new AdminForm();
    }

    /** @return array<string,mixed> */
    protected function requestPayload(): array
    {
        return Request::getInstance()->getRawData();
    }

    /** @return array<string,array<string,mixed>> */
    protected function admins(): array
    {
        return AdminServices::getInstance()->getAdmins();
    }

    /** @return array<string,string> */
    protected function profiles(): array
    {
        return Security::getProfiles();
    }

    /** @param array<string,mixed> $data */
    protected function saveUser(array $data): bool
    {
        return Security::save($data);
    }

    protected function deleteUser(string $username): void
    {
        Security::getInstance()->deleteUser($username);
    }

    private function assertSuperAdminWriteAccess(): void
    {
        $security = Security::getInstance();
        if (count($security->getAdmins()) > 0 && !$security->isSuperAdmin() && !Security::isTest()) {
            throw new ApiException(t('Restricted area'), 403);
        }
    }

    /** @param mixed $value */
    private function isRecord($value): bool
    {
        return is_array($value) && ($value === [] || array_is_list($value) === false);
    }

    private function isValidUsername(string $username): bool
    {
        return strlen($username) <= 120 && preg_match('/^[A-Za-z0-9._@_\-]+$/', $username) === 1;
    }

    /**
     * @param array<string,array<string,mixed>> $admins
     * @param array<string,string> $profiles
     * @return array<int,array{username:string,role:string,class:string}>
     */
    private function sanitizeUsers(array $admins, array $profiles): array
    {
        $users = [];
        foreach ($admins as $username => $admin) {
            $users[] = [
                'username' => (string) $username,
                'role' => (string) ($profiles[(string) ($admin['profile'] ?? '')] ?? t('User')),
                'class' => (string) ($admin['class'] ?? ''),
            ];
        }

        return $users;
    }

    /** @return array<string,string[]> */
    private function fieldErrors(AdminForm $form): array
    {
        $errors = [];
        foreach ($form->getFields() as $field => $definition) {
            if (is_array($definition) && !empty($definition['error'])) {
                $errors[(string) $field] = [(string) $definition['error']];
            }
        }

        return $errors;
    }

    /** @return array<string,string[]> */
    private function requiredFieldErrors(array $values): array
    {
        $errors = [];
        foreach (['username', 'password', 'profile'] as $field) {
            $value = $values[$field] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === [])) {
                $errors[(string) $field] = [strip_tags(str_replace(
                    '%s',
                    '<strong>' . $field . '</strong>',
                    t('Field %s is required')
                ))];
            }
        }

        return $errors;
    }
}
