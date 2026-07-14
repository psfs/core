<?php

namespace PSFS\controller;

use PSFS\base\Security;
use PSFS\base\Router;
use PSFS\base\admin\AdminApiResponse;
use PSFS\base\admin\AdminFrontendCsrf;
use PSFS\base\admin\AdminFormSchemaFactory;
use PSFS\base\config\Config;
use PSFS\base\config\ConfigForm;
use PSFS\base\exception\ConfigException;
use PSFS\base\Request;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\Visible;
use PSFS\controller\base\Admin;

/** JSON configuration contract consumed by the native Admin 2.0 screen. */
class AdminFrontendConfigController extends Admin
{
    #[HttpMethod('GET')]
    #[Route('/admin/api/v2/config')]
    #[Visible(false)]
    public function show(): string
    {
        $form = $this->configForm();
        $form->build();

        return $this->json(AdminApiResponse::success([
            'form' => (new AdminFormSchemaFactory())->fromForm($form),
            'suggestions' => $this->suggestions(),
        ]));
    }

    #[HttpMethod('PUT')]
    #[Route('/admin/api/v2/config')]
    #[Visible(false)]
    public function update(): string
    {
        AdminFrontendCsrf::assertValid();
        $this->assertSuperAdminConfigWriteAccess();
        $payload = $this->requestPayload();
        $values = $payload['values'] ?? null;
        $extra = $payload['extra'] ?? null;

        if (!$this->isValueRecord($values) || !$this->isRecord($extra)) {
            return $this->json(AdminApiResponse::failure(
                t('Invalid configuration payload'),
                ['payload' => [t('Expected values and extra objects')]]
            ), 422);
        }

        $form = $this->configForm();
        // API clients authenticate independently; legacy form CSRF fields do not belong in this JSON contract.
        $form->setMethod('PUT')->build();
        $values = $this->retainExistingMaskedSecrets($form, $values);
        $form->setData($values);
        $errors = $this->requiredFieldErrors($form);

        if ($errors !== [] || !$form->isValid()) {
            return $this->json(AdminApiResponse::failure(
                t('Invalid configuration'),
                $errors + $this->fieldErrors($form)
            ), 422);
        }

        if (!$this->save($form->getData(), $this->normalizeExtra($extra))) {
            return $this->json(AdminApiResponse::failure(
                t('Error while saving configuration, please verify filesystem permissions')
            ), 500);
        }

        return $this->json(AdminApiResponse::success([
            'changed' => array_values(array_unique(array_merge(array_keys($values), array_keys($extra)))),
        ], t('Configuration updated successfully')));
    }

    protected function configForm(): ConfigForm
    {
        return new ConfigForm(
            '/admin/api/v2/config',
            Config::$required,
            Config::$optional,
            Config::getInstance()->dumpConfig()
        );
    }

    /** @return array<string,mixed> */
    protected function requestPayload(): array
    {
        return Request::getInstance()->getRawData();
    }

    /** @param array<string,mixed> $values @param array<string,mixed> $extra */
    protected function save(array $values, array $extra): bool
    {
        return Config::save($values, $extra);
    }

    protected function assertSuperAdminConfigWriteAccess(): void
    {
        $security = Security::getInstance();
        if (count($security->getAdmins()) > 0 && !$security->isSuperAdmin() && !Security::isTest()) {
            throw new ConfigException(t('Restricted area'));
        }
    }

    /** @param mixed $value */
    private function isRecord($value): bool
    {
        return is_array($value) && ($value === [] || array_is_list($value) === false);
    }

    /** @param mixed $value */
    private function isValueRecord($value): bool
    {
        return $this->isRecord($value) && $value !== [];
    }

    /** @param array<string,mixed> $values @return array<string,mixed> */
    private function retainExistingMaskedSecrets(ConfigForm $form, array $values): array
    {
        $existing = Config::getInstance()->dumpConfig();
        foreach ($form->getFields() as $field => $definition) {
            if (!preg_match('/(?:secret|password|token|hash)/i', (string) $field)) {
                continue;
            }

            if (array_key_exists($field, $values) && $values[$field] === '') {
                if (array_key_exists($field, $existing)) {
                    $values[$field] = $existing[$field];
                } else {
                    unset($values[$field]);
                }
            }
        }

        return $values;
    }

    /** @return string[] */
    private function suggestions(): array
    {
        $suggestions = array_merge(Config::$required, Config::$optional);
        foreach (array_keys(Router::getInstance()->getDomains()) as $domain) {
            $normalized = strtolower(str_replace(['@', '/', '\\'], '', (string) $domain));
            if ($normalized !== '' && $normalized !== 'root') {
                $suggestions[] = $normalized . '.api.secret';
            }
        }

        $suggestions = array_values(array_unique(array_filter($suggestions, 'is_string')));
        sort($suggestions, SORT_NATURAL | SORT_FLAG_CASE);
        return $suggestions;
    }

    /**
     * The legacy Config persistence contract uses parallel label/value arrays.
     * The v2 API deliberately accepts a normal object, then adapts it here.
     *
     * @param array<string,mixed> $extra
     * @return array{label:array<int,string>,value:array<int,mixed>}
     */
    private function normalizeExtra(array $extra): array
    {
        $labels = [];
        $values = [];
        foreach ($extra as $key => $value) {
            $name = trim((string) $key);
            if ($name === '' || !is_scalar($value)) {
                continue;
            }
            $labels[] = $name;
            $values[] = $value;
        }

        return ['label' => $labels, 'value' => $values];
    }

    /** @return array<string,string[]> */
    private function fieldErrors(ConfigForm $form): array
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
    private function requiredFieldErrors(ConfigForm $form): array
    {
        $errors = [];
        foreach ($form->getFields() as $field => $definition) {
            if (!is_array($definition) || ($definition['required'] ?? false) !== true) {
                continue;
            }

            $value = $definition['value'] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === [])) {
                $errors[(string) $field] = [strip_tags(str_replace('%s', '<strong>' . $field . '</strong>', t('Field %s is required')))];
            }
        }

        return $errors;
    }
}
