import assert from 'node:assert/strict';
import { mkdtemp, rm, writeFile, mkdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { after, before, test } from 'node:test';
import { createStaticServer } from './static-server.mjs';

let root;
let server;
let baseUrl;

before(async () => {
  root = await mkdtemp(join(tmpdir(), 'psfs-static-ui-'));
  await mkdir(join(root, 'admin-v2'), { recursive: true });
  await writeFile(join(root, 'admin-v2', 'index.html'), '<main>Admin static</main>');
  await writeFile(join(root, 'admin-v2', 'main.js'), 'console.log("bundle")');
  server = createStaticServer({ root, apiOrigin: 'http://127.0.0.1:9' });
  const address = await server.listen(0);
  baseUrl = `http://127.0.0.1:${address.port}`;
});

after(async () => {
  await server.close();
  await rm(root, { recursive: true, force: true });
});

test('serves built assets and falls back to the Admin SPA entry point', async () => {
  const asset = await fetch(`${baseUrl}/admin-v2/main.js`);
  const deepLink = await fetch(`${baseUrl}/admin-v2/routes`);

  assert.equal(await asset.text(), 'console.log("bundle")');
  assert.equal(await deepLink.text(), '<main>Admin static</main>');
});
