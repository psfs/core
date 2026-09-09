import { expect, test } from '@playwright/test';

test('consulta un manager mediante la capa API sin habilitar mutaciones', async ({ page }) => {
  const records = page.waitForResponse((response) => new URL(response.url()).pathname === '/CLIENT/api/Related' && response.status() === 200);
  await page.goto('/admin-v2/CLIENT/Related');
  await records;

  await expect(page.getByRole('heading', { name: 'CLIENT / Related' })).toBeVisible();
  await expect(page.getByText('Related fixture')).toBeVisible();
  await expect(page.getByText(/modo consulta/)).toBeVisible();
  await expect(page.getByRole('button', { name: 'Ver detalle' })).toBeVisible();
});
