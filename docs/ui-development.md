# UI moderna: estado validado

## Base estable

- Código fuente Angular 22: `ui/`.
- Imagen de desarrollo: Node `24.15.0-alpine` en el servicio `ui`.
- `UI_MODE=watch` expone Angular en `http://localhost:4200/ui/` y recompila
  cambios; HMR y live reload están desactivados de forma explícita.
- `UI_MODE=build` deja el paquete estático en `src/public/ui/`.
- `ui.path` es una clave opcional de configuración PSFS. Con `/ui`, el runtime
  Swoole intercepta solo `/ui` y sus hijos antes de los assets y del Dispatcher.
- El proxy HTTP solo se habilita con un mount válido y `UI_DEV_UPSTREAM` válido
  (por defecto, `http://ui:4200`). Conserva método, ruta, query y cuerpo;
  no reenvía `Authorization`, `Cookie` ni cabeceras hop-by-hop.
- La entrada por PSFS mantiene la autenticación administrativa. Sin ella,
  responde `401`; si Node no está disponible, responde `502`.
- Ninguno de estos flujos escribe ni regenera `config/config.json`.

## Verificación ejecutable

```sh
docker compose --profile swoole up -d php-swoole ui
docker exec psfs-ui-1 npm run build
docker exec psfs-php-swoole-1 php vendor/bin/phpunit tests/runtime/swoole
```

Con `ui.path=/ui` ya configurado, abrir
`http://admin:admin@localhost:8011/ui/`. La URL debe conservar el puerto
`8011` y renderizar `PSFS UI POC · proxy HTTP activo`.

## Pendiente de cierre

1. Proxy WebSocket bidireccional para HMR en el mismo origen PSFS. Se retiró
   porque no actualizaba el navegador de forma fiable y obligaba a cambiar el
   servidor HTTP de Swoole.
2. Suite Playwright reproducible dentro de Docker: carga mismo origen, `401`,
   `502`, conservación de query/cuerpo y prueba HMR cuando exista el puente
   WebSocket válido.
3. Fallback SPA y publicación estática para producción en el document root
   final (`html/ui` o equivalente del proyecto consumidor).
4. Generalizar el contrato de mount/build para Vue y React. Ahora es una POC
   Angular fija en `/ui/`.
5. Migrar una pantalla administrativa real manteniendo permisos, APIs y
   pruebas de paridad frente a AngularJS.
