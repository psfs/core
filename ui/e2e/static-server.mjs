import { createServer, request as httpRequest } from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import { resolve, sep } from 'node:path';

const mimeTypes = {
  '.css': 'text/css; charset=utf-8',
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.woff2': 'font/woff2'
};

function contentType(path) {
  const extension = path.slice(path.lastIndexOf('.'));
  return mimeTypes[extension] ?? 'application/octet-stream';
}

function proxy(request, response, apiOrigin) {
  const target = new URL(request.url ?? '/', apiOrigin);
  const upstream = httpRequest(target, { method: request.method, headers: request.headers }, (upstreamResponse) => {
    response.writeHead(upstreamResponse.statusCode ?? 502, upstreamResponse.headers);
    upstreamResponse.pipe(response);
  });
  upstream.on('error', () => {
    response.writeHead(502, { 'content-type': 'application/json; charset=utf-8' });
    response.end(JSON.stringify({ ok: false, message: 'UI mock API is unavailable.', data: null, errors: {} }));
  });
  request.pipe(upstream);
}

export function createStaticServer({ root, apiOrigin }) {
  const staticRoot = resolve(root);
  const spaEntry = resolve(staticRoot, 'admin-v2', 'index.html');
  const httpServer = createServer(async (request, response) => {
    const pathname = new URL(request.url ?? '/', 'http://static-ui.local').pathname;
    if (pathname.startsWith('/admin/api/v2/') || pathname.startsWith('/CLIENT/api/')) {
      proxy(request, response, apiOrigin);
      return;
    }

    const candidate = resolve(staticRoot, `.${pathname}`);
    const allowed = candidate === staticRoot || candidate.startsWith(`${staticRoot}${sep}`);
    try {
      if (!allowed || !(await stat(candidate)).isFile()) throw new Error('fallback');
      response.writeHead(200, { 'content-type': contentType(candidate), 'cache-control': 'no-store' });
      response.end(await readFile(candidate));
    } catch {
      try {
        response.writeHead(200, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store' });
        response.end(await readFile(spaEntry));
      } catch {
        response.writeHead(404, { 'content-type': 'text/plain; charset=utf-8' });
        response.end('Admin static bundle not found.');
      }
    }
  });
  return {
    server: httpServer,
    async listen(port = 4201) {
      await new Promise((resolvePromise, reject) => httpServer.once('error', reject).listen(port, '127.0.0.1', resolvePromise));
      return httpServer.address();
    },
    close: () => new Promise((resolvePromise, reject) => httpServer.close((failure) => failure ? reject(failure) : resolvePromise()))
  };
}

if (process.argv[1] && new URL(`file://${process.argv[1]}`).href === import.meta.url) {
  const server = createStaticServer({
    root: process.env.PSFS_UI_STATIC_ROOT ?? 'dist',
    apiOrigin: process.env.PSFS_UI_MOCK_ORIGIN ?? 'http://127.0.0.1:4310'
  });
  const address = await server.listen(Number(process.env.PSFS_UI_STATIC_PORT ?? 4201));
  process.stdout.write(`PSFS static Admin UI listening on ${address.port}\n`);
  process.on('SIGTERM', () => server.close().then(() => process.exit(0)));
  process.on('SIGINT', () => server.close().then(() => process.exit(0)));
}
