import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { createMockApiServer } from './mock-api.mjs';

let api;
let baseUrl;

before(async () => {
  api = createMockApiServer();
  const address = await api.listen(0);
  baseUrl = `http://127.0.0.1:${address.port}`;
});

after(() => api.close());

test('expone contratos Admin v2 sin depender de PSFS', async () => {
  const response = await fetch(`${baseUrl}/admin/api/v2/bootstrap`);
  const body = await response.json();

  assert.equal(response.status, 200);
  assert.equal(body.identity.username, 'admin');
  assert.equal(typeof body.csrfToken, 'string');
  assert.ok(Array.isArray(body.menu));
});

test('mantiene las mutaciones en memoria y exige CSRF', async () => {
  const rejected = await fetch(`${baseUrl}/admin/api/v2/routes/regenerate`, { method: 'POST' });
  assert.equal(rejected.status, 403);

  const accepted = await fetch(`${baseUrl}/admin/api/v2/routes/regenerate`, {
    method: 'POST',
    headers: { 'x-psfs-csrf': 'ui-e2e-csrf' }
  });
  const body = await accepted.json();

  assert.equal(accepted.status, 200);
  assert.equal(body.data.regenerated, true);
});
