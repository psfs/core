# PSFS UI POC

La UI moderna vive en `ui/`. El build genera un paquete estático autocontenido
en `src/public/ui/`, apto para copiar, publicar en un CDN o enlazar desde el
document root que decida el proyecto consumidor.

## Desarrollo

La POC Angular 22 usa el mount `/ui/` y el contenedor Node 24.15.0.

```sh
UI_MODE=watch docker compose up -d ui
```

Con `ui.path=/ui` y el perfil Swoole iniciado, PSFS protege y reenvía las
peticiones HTTP de `http://localhost:8011/ui/` al contenedor `ui`. La URL del
navegador se mantiene en el origen PSFS. El proxy no envía las credenciales
Basic ni las cookies de PSFS a Node.

El watcher recompila los cambios. HMR y live reload están desactivados hasta
disponer de un proxy WebSocket transparente y verificable en el origen PSFS.

## Build

```sh
UI_MODE=build docker compose run --rm ui
```

El proceso termina al generar `src/public/ui/index.html` y los assets.
No modifica `config/config.json`.

## Límite actual

Esta POC no instala un fallback SPA de producción, no migra pantallas AngularJS
y no define todavía un montaje configurable por framework. Es solo la base
Angular + build estático + proxy HTTP autenticado durante desarrollo.
