# PSFS UI

La UI moderna vive en `ui/` como workspace Angular 22. Cada aplicación genera
un paquete estático autocontenido bajo `src/public/`, apto para copiar, publicar
en un CDN o enlazar desde el document root.

## Desarrollo

La POC base usa `/ui/`. Admin 2.0 usa `/admin-v2/`, con el contenedor Node
24.15.0.

```sh
UI_MODE=watch docker compose up -d ui
```

Para trabajar con Admin 2.0:

```sh
El valor por defecto es Admin 2.0 (`/admin-v2/`). Para levantar la POC histórica en `/ui/`, usa explícitamente `UI_APP=ui`.
docker compose --profile swoole up -d php-swoole
```

Con `ui.path=/ui` o `admin.front.path=/admin-v2` y el perfil Swoole iniciado,
PSFS protege y reenvía las peticiones HTTP al contenedor `ui`. La URL del
navegador se mantiene en el origen PSFS. El proxy no envía las credenciales
Basic ni las cookies de PSFS a Node.

El watcher recompila los cambios. Para `/admin-v2/`, Swoole no abre el bridge
WebSocket: la versión actual de Swoole cae con `SIGSEGV` al proxificar el socket
de Vite. Vite usa su fallback directo a `localhost:4200` para HMR. La carga HTTP
continúa siendo same-origin; el socket de desarrollo no lo es.

## Build

```sh
UI_MODE=build docker compose run --rm ui
```

Para compilar Admin 2.0:

```sh
UI_APP=admin UI_MODE=build docker compose run --rm ui
```

El proceso termina al generar `src/public/<app>/index.html`, sus assets y el
enlace seguro `html/<mount> -> ../src/public/<app>`. No modifica
`config/config.json`. El servidor estático Swoole aplica fallback SPA para el
mount.

## Pruebas E2E

Los navegadores de Playwright no se instalan en la imagen Alpine de desarrollo.
Las pruebas se ejecutan en el servicio `ui-e2e`, que usa la imagen oficial de
Playwright y se conecta a los servicios ya levantados:

```sh
UI_APP=admin UI_MODE=watch docker compose --profile swoole --profile e2e run --rm --no-deps ui-e2e sh -lc 'npm ci && npm run test:e2e:admin'
```

Antes de este comando deben estar activos `ui` y `php-swoole` con el mismo modo
de desarrollo. `test:e2e:admin` ejecuta todas las especificaciones
`admin-v2*.spec.mjs`: rutas, documentación OpenAPI, configuración y usuarios.
La suite cubre el navegador real con Basic Auth; no modifica `config/config.json`.

## Contrato Admin 2.0

- `/admin/*` permanece legacy por defecto.
- `admin.front.version=v2` redirige las navegaciones elegibles a
  `admin.front.path` (por defecto `/admin-v2`).
- `?__front=legacy|v2` tiene precedencia para soporte, comparación y rollback;
  se elimina de la URL final.
- `/admin/api/v2/bootstrap` entrega únicamente identidad administrativa y menú
  visible derivados del router; cada operación continúa autorizándose en backend.
