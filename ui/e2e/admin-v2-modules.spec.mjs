import { expect, test } from '@playwright/test';

test('carga el generador nativo y rechaza una generación inválida sin escribir módulos', async ({ page }) => {
  const schema = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return url.pathname === '/admin/api/v2/modules/schema' && response.request().method() === 'GET' && response.status() === 200;
  });

  await page.goto('/admin-v2/module');
  await schema;

  await expect(page.getByRole('heading', { name: 'Generador de módulos' })).toBeVisible();
  await expect(page.locator('iframe')).toHaveCount(0);
  await expect(page.locator('select#controllerType')).toBeVisible();
  await expect(page.getByText('Esta operación escribe archivos y genera migraciones.')).toBeVisible();

  await page.locator('input#module').fill('');
  await page.getByRole('button', { name: 'Generar módulo' }).click();
  await expect(page.locator('input#module')).toHaveClass(/ng-invalid/);

  const config = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return url.pathname === '/admin/api/v2/config' && response.request().method() === 'GET' && response.status() === 200;
  });
  await page.locator('a[href="/admin-v2/config"]').click();
  await config;
  await expect(page.getByRole('heading', { name: 'Configuración general' })).toBeVisible();
});
