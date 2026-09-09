# Admin 2.0: fase 4 — entrega estática verificable

## Objetivo

Demostrar que Admin 2.0 se puede entregar como bundle compilado sin Vite,
Swoole ni PHP en el carril de UI.

## Diseño

- La configuración `e2e-static` compila Admin en `dist/admin-v2` del workspace
  efímero de pruebas; no actualiza assets versionados ni el checkout.
- Un servidor Node mínimo entrega el bundle, aplica fallback SPA para
  deep-links y reenvía exclusivamente las rutas de API al mock Node.
- Playwright ejecuta la misma matriz funcional que Vite sobre ese servidor,
  incluyendo el deep-link estático y el manager de consulta.

## Criterio de salida

`npm run test:e2e:admin:static` pasa sin arrancar servicios PHP, Swoole o Vite.
Los assets de producción se publican por el instalador de PSFS en una línea de
trabajo independiente, conservando su validación de reemplazo atómico.
