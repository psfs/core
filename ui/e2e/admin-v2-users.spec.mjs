import { expect, test } from '@playwright/test';

test('carga usuarios de forma nativa y rechaza un alta inválida sin persistir', async ({ page }) => {
  const users = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return url.pathname === '/admin/api/v2/users' && response.request().method() === 'GET' && response.status() === 200;
  });

  await page.goto('/admin-v2/setup');
  await users;

  await expect(page.getByRole('heading', { name: 'Gestión de usuarios' })).toBeVisible();
  await expect(page.locator('iframe')).toHaveCount(0);
  await expect(page.locator('table tbody tr').first()).toBeVisible();
  await expect(page.locator('table tbody tr').first()).not.toContainText(/[a-f0-9]{40}/i);
  const before = await page.locator('table tbody tr').count();

  await page.locator('input#username').fill('');
  await page.locator('input#password').fill('');
  await page.getByRole('button', { name: 'Crear usuario' }).click();
  await expect(page.locator('input#username')).toHaveClass(/ng-invalid/);
  await expect(page.locator('table tbody tr')).toHaveCount(before);

  await page.getByRole('button', { name: 'Eliminar' }).first().click();
  await expect(page.getByRole('alertdialog', { name: 'Eliminar usuario' })).toBeVisible();
  await page.getByRole('button', { name: 'Cancelar' }).click();
  await expect(page.getByRole('alertdialog', { name: 'Eliminar usuario' })).toHaveCount(0);
});

test('crea y elimina una cuenta temporal mediante el diálogo de confirmación', async ({ page }) => {
  const username = `e2e_delete_${Date.now()}`;
  await page.goto('/admin-v2/setup');
  await expect(page.locator('input#username')).toBeVisible();

  const created = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return url.pathname === '/admin/api/v2/users' && response.request().method() === 'POST';
  });
  await page.locator('input#username').fill(username);
  await page.locator('input#password').fill('Temporary-e2e-password-2026');
  await page.getByRole('button', { name: 'Crear usuario' }).click();
  const createResponse = await created;
  await expect(createResponse.status(), await createResponse.text()).toBe(200);

  await page.getByPlaceholder('Buscar usuario o rol').fill(username);
  await expect(page.locator('table tbody tr')).toHaveCount(1);
  await expect(page.locator('table tbody tr')).toContainText(username);

  const deleted = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return url.pathname === '/admin/api/v2/users' && response.request().method() === 'DELETE';
  });
  await page.getByRole('button', { name: 'Eliminar' }).click();
  await page.getByRole('alertdialog', { name: 'Eliminar usuario' }).getByRole('button', { name: 'Eliminar', exact: true }).click();
  await expect((await deleted).status()).toBe(200);
  await expect(page.locator('table tbody tr')).toHaveCount(0);
});
