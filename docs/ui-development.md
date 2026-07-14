# UI moderna: estado validado

## Base estable

- Código fuente Angular 22: `ui/`.
- Imagen de desarrollo: Node `24.15.0-alpine` en el servicio `ui`.
- `UI_MODE=watch` expone Admin 2.0 en `http://localhost:4200/admin-v2/` con HMR. La POC `/ui/` requiere `UI_APP=ui`.
- El runtime Swoole usa `WebSocket\\Server` (compatible con HTTP) y reenvía
  los frames HMR en ambos sentidos. La autorización PSFS se comprueba antes
  de abrir el WebSocket hacia Node; las cookies y Basic Auth no se reenvían.
- `UI_MODE=build` deja el paquete estático en `src/public/ui/`.
- `ui.path` es una clave opcional de configuración PSFS. Con `/ui`, el runtime
  Swoole intercepta solo `/ui` y sus hijos antes de los assets y del Dispatcher.
- El proxy HTTP solo se habilita con un mount válido y `UI_DEV_UPSTREAM` válido
  (por defecto, `http://ui:4200`). Conserva método, ruta, query y cuerpo;
  no reenvía `Authorization`, `Cookie` ni cabeceras hop-by-hop.
- La entrada por PSFS mantiene la autenticación administrativa. Sin ella,
  responde `401`; si Node no está disponible, responde `502`.
- Ninguno de estos flujos escribe ni regenera `config/config.json`.

## Publicación estática

El build genera `src/public/ui/`. Para producción, copia su contenido a
`html/ui/` o crea el enlace `html/ui -> ../src/public/ui`. Sin
`UI_DEV_UPSTREAM`, PSFS sirve esos assets y entrega `html/ui/index.html` como
fallback SPA para rutas cliente bajo `ui.path`.

## Verificación ejecutable

```sh
docker compose --profile swoole up -d php-swoole ui
docker exec psfs-ui-1 npm run build
docker exec psfs-php-swoole-1 php vendor/bin/phpunit tests/runtime/swoole
docker compose --profile swoole --profile e2e run --rm ui-e2e

# Prueba la entrega estática sin el upstream Node.
UI_DEV_UPSTREAM= docker compose --profile swoole up -d --force-recreate php-swoole
docker compose --profile swoole --profile e2e run --rm --no-deps ui-e2e-static
# Recupera el modo de desarrollo.
docker compose --profile swoole up -d --force-recreate php-swoole ui
```

Con `ui.path=/ui` ya configurado, abrir
`http://admin:admin@localhost:8011/ui/`. La URL debe conservar el puerto
`8011` y renderizar `PSFS UI POC · HMR verificado`.

La suite `ui-e2e` usa la imagen oficial de Playwright y cubre carga autenticada,
rechazo con credenciales inválidas y HMR sin recarga completa.

## Pendiente de cierre

1. Generalizar el contrato de mount/build para Vue y React. Ahora es una POC
   Angular fija en `/ui/`.
2. Migrar una pantalla administrativa real manteniendo permisos, APIs y
   pruebas de paridad frente a AngularJS.
