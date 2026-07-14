import { expect, test } from '@playwright/test';

test('carga la configuración segura, conserva el secreto vacío y bloquea un requerido inválido', async ({ page }) => {
  const config = page.waitForResponse((response) => new URL(response.url()).pathname === '/admin/api/v2/config' && response.request().method() === 'GET' && response.status() === 200);
  await page.goto('/admin-v2/config');
  await config;

  await expect(page.getByRole('heading', { name: 'Configuración general' })).toBeVisible();
  await expect(page.locator('iframe')).toHaveCount(0);
  await expect(page.locator('input[type="password"]')).toHaveCount(1);
  await expect(page.locator('input[type="password"]')).toHaveValue('');
  await page.getByRole('button', { name: 'Añadir parámetro' }).click();
  const extraKey = page.locator('input[list="config-suggestions"]');
  const extraValue = page.getByLabel('Valor del parámetro');
  await expect(extraKey).toBeVisible();
  await extraKey.fill('custom.runtime.flag');
  await expect(extraKey).toHaveValue('custom.runtime.flag');
  await extraValue.fill('enabled');
  await expect(extraValue).toHaveValue('enabled');
  await expect(extraValue).toBeFocused();

  await page.locator('input#db\\.host').fill('');
  await page.getByRole('button', { name: 'Guardar configuración' }).click();
  await expect(page.locator('input#db\\.host')).toHaveClass(/ng-invalid/);
});
