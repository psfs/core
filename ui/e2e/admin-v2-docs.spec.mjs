import { expect, test } from '@playwright/test';

test('abre documentación mediante el explorador interactivo y no presenta JSON crudo', async ({ page }) => {
  await page.goto('/admin-v2/api/docs');
  await expect(page.getByRole('heading', { name: 'Documentación API' })).toBeVisible();

  const domain = page.locator('.chip').first();
  await expect(domain).toBeVisible();
  await domain.click();
  await expect(page.getByRole('link', { name: 'Abrir explorador API' })).toBeVisible();

  await page.getByRole('link', { name: 'Abrir explorador API' }).click();
  await expect(page.locator('#swagger-ui')).toBeVisible();
  await expect(page.locator('pre')).toHaveCount(0);
});
