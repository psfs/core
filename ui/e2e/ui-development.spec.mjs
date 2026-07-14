import { expect, test } from '@playwright/test';
import { readFile, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const componentPath = resolve(process.cwd(), 'src/app/app.component.ts');
const initialText = 'PSFS UI POC · HMR verificado';
const updatedText = 'PSFS UI POC · HMR E2E verificado';

test('protege y sirve la UI desde el mismo origen PSFS', async ({ page }) => {
  await page.goto('/ui/');

  await expect(page).toHaveURL(/\/ui\/$/);
  await expect(page.getByText(initialText)).toBeVisible();
});

test('rechaza la UI sin autenticación administrativa', async ({ browser }) => {
  const anonymous = await browser.newContext({
    httpCredentials: { username: 'anonymous', password: 'invalid' }
  });
  const page = await anonymous.newPage();

  const response = await page.goto('/ui/');

  expect(response?.status()).toBe(401);
  await anonymous.close();
});

test('actualiza el DOM con HMR sin abandonar el origen PSFS', async ({ page }) => {
  const original = await readFile(componentPath, 'utf8');
  const navigationCount = await page.goto('/ui/').then(() => page.evaluate(() => performance.getEntriesByType('navigation').length));

  try {
    await writeFile(componentPath, original.replace(initialText, updatedText));
    await expect(page.getByText(updatedText)).toBeVisible({ timeout: 15_000 });
    await expect(page).toHaveURL(/\/ui\/$/);
    await expect(page.evaluate(() => performance.getEntriesByType('navigation').length)).resolves.toBe(navigationCount);
  } finally {
    await writeFile(componentPath, original);
    await expect(page.getByText(initialText)).toBeVisible({ timeout: 15_000 });
  }
});
