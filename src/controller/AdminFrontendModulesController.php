<?php

namespace PSFS\controller;

use Exception;
use PSFS\base\admin\AdminApiResponse;
use PSFS\base\admin\AdminFrontendCsrf;
use PSFS\base\admin\AdminFormSchemaFactory;
use PSFS\base\config\ModuleForm;
use PSFS\base\Request;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\Visible;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\controller\base\Admin;
use PSFS\services\GeneratorService;

/** JSON-only module-generator contract consumed by the native Admin 2.0 screen. */
class AdminFrontendModulesController extends Admin
{
    #[HttpMethod('GET')]
    #[Route('/admin/api/v2/modules/schema')]
    #[Visible(false)]
    public function schema(): string
    {
        $form = $this->moduleForm();
        $form->build();

        return $this->json(AdminApiResponse::success([
            'form' => (new AdminFormSchemaFactory())->fromForm($form),
        ]));
    }

    #[HttpMethod('POST')]
    #[Route('/admin/api/v2/modules')]
    #[Visible(false)]
    public function create(): string
    {
        AdminFrontendCsrf::assertValid();
        $payload = $this->requestPayload();
        $values = $payload['values'] ?? null;
        if (!$this->isRecord($values) || $values === []) {
            return $this->json(AdminApiResponse::failure(t('Invalid module payload'), [
                'payload' => [t('Expected a values object')],
            ]), 422);
        }

        $form = $this->moduleForm();
        $form->setMethod('POST')->build();
        $form->setData($values);
        $errors = $this->requiredFieldErrors($form);
        if (trim((string) ($values['module'] ?? '')) === '') {
            $errors['module'] = [strip_tags(str_replace(
                '%s',
                '<strong>module</strong>',
                t('Field %s is required')
            ))];
        }
        if ($errors !== [] || !$form->isValid()) {
            return $this->json(AdminApiResponse::failure(
                t('Invalid module'),
                $errors + $this->fieldErrors($form)
            ), 422);
        }

        $module = strtoupper((string) $form->getFieldValue('module'));
        $type = (string) preg_replace('/normal/i', '', (string) $form->getFieldValue('controllerType'));
        $apiClass = (string) $form->getFieldValue('api');
        $module = (string) preg_replace('/[\\\\\\/]/', '/', $module);
        $module = (string) preg_replace('/^\\//', '', $module);

        try {
            GeneratorHelper::checkCustomNamespaceApi($apiClass);
            $this->generateModule($module, $type, $apiClass);
        } catch (Exception $exception) {
            return $this->json(AdminApiResponse::failure($exception->getMessage()), 422);
        }

        return $this->json(AdminApiResponse::success([
            'module' => $module,
        ], str_replace('%s', $module, t('Module %s generated successfully'))));
    }

    protected function moduleForm(): ModuleForm
    {
        return new ModuleForm();
    }

    /** @return array<string,mixed> */
    protected function requestPayload(): array
    {
        return Request::getInstance()->getRawData();
    }

    protected function generateModule(string $module, string $type, string $apiClass): void
    {
        GeneratorService::getInstance()->createStructureModule($module, false, $type, $apiClass);
    }

    /** @param mixed $value */
    private function isRecord($value): bool
    {
        return is_array($value) && ($value === [] || array_is_list($value) === false);
    }

    /** @return array<string,string[]> */
    private function fieldErrors(ModuleForm $form): array
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
    private function requiredFieldErrors(ModuleForm $form): array
    {
        $errors = [];
        foreach ($form->getFields() as $field => $definition) {
            if (!is_array($definition) || ($definition['required'] ?? false) !== true) {
                continue;
            }

            $value = $definition['value'] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
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
