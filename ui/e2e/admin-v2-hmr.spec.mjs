import { expect, test } from '@playwright/test';
import { readFile, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const componentPath = resolve(process.cwd(), 'projects/admin/src/app/admin-shell.component.ts');
const initialText = 'PSFS <strong>Admin</strong>';
const updatedText = 'PSFS <strong>Admin HMR E2E</strong>';

test('actualiza Admin 2.0 mediante HMR a través de Swoole sin recargar', async ({ page }) => {
  const original = await readFile(componentPath, 'utf8');
  await page.route('**/admin/api/v2/bootstrap', (route) => route.fulfill({
    contentType: 'application/json',
    body: JSON.stringify({
      identity: { username: 'admin', role: 'Administrator' },
      locale: 'en_US',
      locales: ['en_US'],
      csrfToken: 'hmr-fixture-token',
      menu: []
    })
  }));
  await page.route('**/admin/api/v2/routes', (route) => route.fulfill({
    contentType: 'application/json',
    body: JSON.stringify({ ok: true, message: null, data: { routes: [] }, errors: {} })
  }));
  const navigations = await page.goto('/admin-v2/routes').then(() => page.evaluate(() => performance.getEntriesByType('navigation').length));

  try {
    await expect(page.locator('.brand')).toContainText('PSFS Admin');
    await writeFile(componentPath, original.replace(initialText, updatedText));
    await expect(page.locator('.brand')).toContainText('PSFS Admin HMR E2E', { timeout: 15_000 });
    await expect(page.evaluate(() => performance.getEntriesByType('navigation').length)).resolves.toBe(navigations);
  } finally {
    await writeFile(componentPath, original);
    await expect(page.locator('.brand')).toContainText('PSFS Admin', { timeout: 15_000 });
  }
});
