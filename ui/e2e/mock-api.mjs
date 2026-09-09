import { createServer } from 'node:http';

const csrfToken = 'ui-e2e-csrf';

const menu = [{
  module: 'PSFS',
  items: [
    { label: 'General configuration', icon: 'cog', path: '/config' },
    { label: 'User management', icon: 'users', path: '/setup' },
    { label: 'Generate module', icon: 'layer', path: '/module' },
    { label: 'System routes', icon: 'routes', path: '/routes' },
    { label: 'API documentation', icon: 'book', path: '/api/docs' }
  ]
}];

const configurationForm = {
  name: 'config',
  title: 'General configuration',
  fields: {
    'db.host': { name: 'db.host', label: 'Database host', value: 'localhost', required: true },
    'db.password': { name: 'db.password', label: 'Database password', type: 'password', value: '', preserveIfEmpty: true },
    debug: { name: 'debug', label: 'Debug mode', type: 'checkbox', value: false }
  }
};

const usersForm = {
  name: 'users',
  title: 'New user',
  fields: {
    username: { name: 'username', label: 'Username', required: true },
    password: { name: 'password', label: 'Password', type: 'password', required: true },
    role: { name: 'role', label: 'Role', type: 'select', value: 'admin', options: { admin: 'Administrator' } }
  }
};

const modulesForm = {
  name: 'modules',
  title: 'Module generator',
  fields: {
    module: { name: 'module', label: 'Module', required: true },
    controllerType: { name: 'controllerType', label: 'Controller type', type: 'select', value: 'api', options: { api: 'API controller', web: 'Web controller' } }
  }
};

function envelope(data, message = null) {
  return { ok: true, message, data, errors: {} };
}

function error(message, errors = {}) {
  return { ok: false, message, data: null, errors };
}

function response(reply, status, body) {
  reply.writeHead(status, { 'content-type': 'application/json; charset=utf-8', 'cache-control': 'no-store' });
  reply.end(JSON.stringify(body));
}

async function bodyOf(request) {
  let content = '';
  for await (const chunk of request) content += chunk;
  return content ? JSON.parse(content) : {};
}

export function createMockApiServer() {
  const state = { users: [{ username: 'admin', role: 'Administrator', class: 'admin' }] };
  const server = createServer(async (request, reply) => {
    const url = new URL(request.url ?? '/', 'http://ui-e2e.local');
    const path = url.pathname;
    const method = request.method ?? 'GET';
    const locale = request.headers['x-api-lang'] === 'es_ES' ? 'es_ES' : 'en_US';
    const mutating = ['POST', 'PUT', 'DELETE'].includes(method);

    if (!path.startsWith('/admin/api/v2/')) {
      response(reply, 404, error('Unknown UI mock route.'));
      return;
    }
    if (mutating && request.headers['x-psfs-csrf'] !== csrfToken) {
      response(reply, 403, error('Invalid CSRF token.'));
      return;
    }

    if (method === 'GET' && path === '/admin/api/v2/bootstrap') {
      response(reply, 200, { identity: { username: 'admin', role: 'Administrator' }, locale, locales: ['en_US', 'es_ES'], menu, csrfToken });
      return;
    }
    if (method === 'PUT' && /^\/admin\/api\/v2\/locale\/[a-z]{2}_[A-Z]{2}$/.test(path)) {
      response(reply, 200, envelope({ locale: path.split('/').at(-1) }));
      return;
    }
    if (method === 'GET' && path === '/admin/api/v2/routes') {
      response(reply, 200, envelope({ routes: [{ slug: 'admin-v2', route: '/admin-v2/routes' }, { slug: 'api-v2', route: '/admin/api/v2/bootstrap' }] }));
      return;
    }
    if (method === 'POST' && path === '/admin/api/v2/routes/regenerate') {
      response(reply, 200, envelope({ regenerated: true }, locale === 'es_ES' ? 'Rutas regeneradas.' : 'Routes generated successfully'));
      return;
    }
    if (method === 'GET' && path === '/admin/api/v2/config') {
      response(reply, 200, envelope({ form: configurationForm, suggestions: ['custom.runtime.flag'] }));
      return;
    }
    if (method === 'PUT' && path === '/admin/api/v2/config') {
      response(reply, 200, envelope({ changed: ['db.host'] }, 'Configuración actualizada.'));
      return;
    }
    if (method === 'GET' && path === '/admin/api/v2/docs') {
      response(reply, 200, envelope({ domains: ['client'], documentPaths: { client: '/CLIENT/api/doc' } }));
      return;
    }
    if (method === 'GET' && path === '/admin/api/v2/modules/schema') {
      response(reply, 200, envelope({ form: modulesForm }));
      return;
    }
    if (method === 'POST' && path === '/admin/api/v2/modules') {
      const payload = await bodyOf(request);
      const module = payload.values?.module ?? '';
      if (!module) {
        response(reply, 422, error('Invalid module.', { module: ['Required'] }));
      } else {
        response(reply, 200, envelope({ module }, `Module ${module} generated.`));
      }
      return;
    }
    if (method === 'GET' && path === '/admin/api/v2/users') {
      response(reply, 200, envelope({ users: state.users, form: usersForm, profiles: { admin: 'Administrator' } }));
      return;
    }
    if (method === 'POST' && path === '/admin/api/v2/users') {
      const payload = await bodyOf(request);
      const username = payload.values?.username?.trim();
      if (!username || !payload.values?.password) {
        response(reply, 422, error('Invalid user.', { username: !username ? ['Required'] : [], password: !payload.values?.password ? ['Required'] : [] }));
      } else {
        state.users.push({ username, role: 'Administrator', class: 'admin' });
        response(reply, 200, envelope({}, 'Usuario creado correctamente.'));
      }
      return;
    }
    if (method === 'DELETE' && path === '/admin/api/v2/users') {
      const payload = await bodyOf(request);
      state.users = state.users.filter((user) => user.username !== payload.user);
      response(reply, 200, envelope({}, 'Usuario eliminado correctamente.'));
      return;
    }

    response(reply, 404, error('Unknown Admin v2 contract.'));
  });

  return {
    async listen(port = 4310) {
      await new Promise((resolve, reject) => server.once('error', reject).listen(port, '127.0.0.1', resolve));
      return server.address();
    },
    close: () => new Promise((resolve, reject) => server.close((failure) => failure ? reject(failure) : resolve()))
  };
}

if (process.argv[1] && new URL(`file://${process.argv[1]}`).href === import.meta.url) {
  const api = createMockApiServer();
  const address = await api.listen(Number(process.env.PSFS_UI_MOCK_PORT ?? 4310));
  process.stdout.write(`PSFS UI mock API listening on ${address.port}\n`);
  process.on('SIGTERM', () => api.close().then(() => process.exit(0)));
  process.on('SIGINT', () => api.close().then(() => process.exit(0)));
}
