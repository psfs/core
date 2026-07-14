import { expect, test } from '@playwright/test';

test('traduce el shell y la configuración visibles al cambiar de idioma', async ({ page }) => {
  await page.goto('/admin-v2/config');
  await expect(page.locator('.locale-picker select')).toHaveValue('en_US');
  await expect(page.locator('h1')).toHaveText('General configuration');
  await expect(page.locator('a[href="/admin-v2/config"]')).toContainText('General configuration');

  const localeChanged = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return url.pathname === '/admin/api/v2/locale/es_ES' && response.status() === 200;
  });
  const bootstrapInSpanish = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return url.pathname === '/admin/api/v2/bootstrap'
      && response.request().headers()['x-api-lang'] === 'es_ES'
      && response.status() === 200;
  });
  await page.locator('.locale-picker select').selectOption('es_ES');
  await localeChanged;
  await bootstrapInSpanish;
  await expect(page.locator('.locale-picker select')).toHaveValue('es_ES');
  await expect(page.locator('h1')).toHaveText('Configuración general');
  await expect(page.locator('a[href="/admin-v2/config"]')).toContainText('Configuración general');
  await expect.poll(() => page.evaluate(() => localStorage.getItem('psfs.admin-v2.locale'))).toBe('es_ES');

  const bootstrapHeaders = [];
  page.on('request', (request) => {
    if (new URL(request.url()).pathname === '/admin/api/v2/bootstrap') {
      bootstrapHeaders.push(request.headers()['x-api-lang'] ?? '');
    }
  });
  const reloadedBootstrap = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return url.pathname === '/admin/api/v2/bootstrap' && response.status() === 200;
  });
  await page.reload();
  const response = await reloadedBootstrap;
  const request = response.request();
  expect(request.headers()['x-api-lang']).toBe('es_ES');
  expect((await response.json()).locales).toEqual(['en_US', 'es_ES']);
  await expect.poll(() => page.evaluate(() => localStorage.getItem('psfs.admin-v2.locale'))).toBe('es_ES');
  await expect.poll(() => bootstrapHeaders.length).toBe(1);
  expect(bootstrapHeaders).toEqual(['es_ES']);
  await expect(page.locator('.locale-picker select')).toHaveValue('es_ES');
  await expect(page.locator('h1')).toHaveText('Configuración general');
});
