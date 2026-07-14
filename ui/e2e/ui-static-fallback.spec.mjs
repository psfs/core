import { expect, test } from '@playwright/test';

test('sirve el index estático para una ruta cliente bajo el mount UI', async ({ page }) => {
  await page.goto('/ui/orders/42');

  await expect(page).toHaveURL(/\/ui\/orders\/42$/);
  await expect(page.getByText('PSFS UI POC · HMR verificado')).toBeVisible();
});

test('protege el fallback estático con credenciales administrativas', async ({ browser }) => {
  const anonymous = await browser.newContext({
    httpCredentials: { username: 'anonymous', password: 'invalid' }
  });
  const page = await anonymous.newPage();

  const response = await page.goto('/ui/orders/42');

  expect(response?.status()).toBe(401);
  await anonymous.close();
});
