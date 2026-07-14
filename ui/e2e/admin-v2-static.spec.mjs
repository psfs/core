import { expect, test } from '@playwright/test';

test('sirve Admin 2.0 compilado para un deep-link sin depender de Vite', async ({ page }) => {
  await page.route('**/admin/api/v2/bootstrap', (route) => route.fulfill({
    contentType: 'application/json',
    body: JSON.stringify({
      identity: { username: 'admin', role: 'Administrator' },
      locale: 'en_US',
      locales: ['en_US'],
      csrfToken: 'static-fixture-token',
      menu: []
    })
  }));
  await page.route('**/admin/api/v2/routes', (route) => route.fulfill({
    contentType: 'application/json',
    body: JSON.stringify({ ok: true, message: null, data: { routes: [] }, errors: {} })
  }));

  const response = await page.goto('/admin-v2/routes');

  expect(response?.status()).toBe(200);
  await expect(page.locator('script[src="/@vite/client"]')).toHaveCount(0);
  await expect(page.locator('script[type="module"][src^="main-"]')).toHaveCount(1);
  await expect(page.getByRole('heading', { name: 'Rutas del sistema' })).toBeVisible();
});
