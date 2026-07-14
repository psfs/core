import { expect, test } from '@playwright/test';

function responseFor(pathname) {
  return (response) => {
    const url = new URL(response.url());
    return url.pathname === pathname && response.status() === 200;
  };
}

test('renderiza el catálogo de rutas de Admin 2.0 sin contenido legacy embebido', async ({ page }) => {
  const bootstrap = page.waitForResponse(responseFor('/admin/api/v2/bootstrap'));
  const routes = page.waitForResponse(responseFor('/admin/api/v2/routes'));

  await page.goto('/admin-v2/routes');
  await Promise.all([bootstrap, routes]);

  await expect(page.getByRole('heading', { name: 'Rutas del sistema' })).toBeVisible();
  await expect(page.locator('iframe')).toHaveCount(0);
  await expect(page.locator('table tbody tr')).not.toHaveCount(0);
});

test('abre el explorador interactivo de documentación desde Admin 2.0', async ({ page }) => {
  const bootstrap = page.waitForResponse(responseFor('/admin/api/v2/bootstrap'));
  const domains = page.waitForResponse(responseFor('/admin/api/v2/docs'));

  await page.goto('/admin-v2/api/docs');
  await Promise.all([bootstrap, domains]);

  await expect(page.getByRole('heading', { name: 'Documentación API' })).toBeVisible();

  await page.getByRole('button', { name: 'client', exact: true }).click();
  await expect(page.getByRole('link', { name: 'Abrir explorador API' })).toBeVisible();
  await expect(page.locator('pre')).toHaveCount(0);
});

test('presenta las pantallas nativas con el sistema visual compartido', async ({ page }) => {
  const bootstrap = page.waitForResponse(responseFor('/admin/api/v2/bootstrap'));
  const routes = page.waitForResponse(responseFor('/admin/api/v2/routes'));

  await page.goto('/admin-v2/routes');
  await Promise.all([bootstrap, routes]);

  await expect(page.locator('.admin-app')).toBeVisible();
  await expect(page.locator('.admin-sidebar')).toBeVisible();
  await expect(page.locator('.page-header')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Regenerar rutas' })).toHaveClass(/button--primary/);
  await expect(page.locator('.data-table')).toBeVisible();
  await expect(page.locator('.page-header')).toHaveScreenshot('admin-v2-routes-header.png');
});

test('regenera rutas desde Admin 2.0 sin responder con error de servidor', async ({ page }) => {
  await page.goto('/admin-v2/routes');
  await expect(page.getByRole('button', { name: 'Regenerar rutas' })).toBeVisible();

  const regenerated = page.waitForResponse((response) => new URL(response.url()).pathname === '/admin/api/v2/routes/regenerate');
  await page.getByRole('button', { name: 'Regenerar rutas' }).click();
  const response = await regenerated;

  expect(response.status()).toBe(200);
  await expect(page.locator('.notice--success')).toContainText(/^(Rutas regeneradas\.|Routes generated successfully)$/);
});

test('mantiene una configuración usable y estilizada en móvil', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const config = page.waitForResponse(responseFor('/admin/api/v2/config'));
  await page.goto('/admin-v2/config');
  await config;

  const navigationToggle = page.getByRole('button', { name: /^(Abrir navegación|Open navigation)$/ });
  await expect(navigationToggle).toBeVisible();
  await navigationToggle.click();
  await expect(page.getByRole('button', { name: /^(Cerrar navegación|Close navigation)$/ })).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('.admin-sidebar')).toHaveClass(/admin-sidebar--open/);
  await page.getByRole('button', { name: /^(Cerrar navegación|Close navigation)$/ }).click();
  await expect(page.getByRole('button', { name: /^(Abrir navegación|Open navigation)$/ })).toHaveAttribute('aria-expanded', 'false');

  await expect(page.locator('.page')).toBeVisible();
  await expect(page.locator('.page-header')).toBeVisible();
  await expect(page.locator('form.dynamic-form')).toBeVisible();
  await expect(page.locator('.form-field .form-control').first()).toBeVisible();
  await expect(page.locator('.form-actions .button--primary')).toBeVisible();
  await expect(page.locator('.form-field .form-control').first()).toHaveCSS('border-radius', '8.32px');
  await expect(page.locator('form.dynamic-form')).toHaveCSS('grid-template-columns', /[0-9.]+px/);
});
