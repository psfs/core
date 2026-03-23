# Frontend Legacy UX + A11y

Fiabilidad: 85%. Fuente principal: revisión del código vendorizado en `src/public/*` y documentación oficial actual de Bootstrap, jQuery, Bootbox y AngularJS.

## Cambios aplicados

- `v1/base.html.twig`: skip link, landmark `main`, `aria-label` en navegación y `back-to-top`.
- `top.menu.html.twig`: atributos básicos para toggle y dropdown.
- `dialog-a11y.js`: shim conservador para Bootbox con `aria-modal`, `aria-hidden` y `Esc` por defecto.
- `styles.scss` y `styles.css`: foco visible, estilos mínimos del overlay y reintroducción del foco en botones.
- `index.html.twig`: alt más descriptivo y copy corto para el launcher.

## Compatibilidad

- El stack actual sigue siendo Bootstrap 3.4.1 + jQuery 3.7.1 + AngularJS 1.8.2 + Bootbox 6.
- Bootstrap 3 está EOL. La documentación oficial lo indica como finalizado.
- Bootbox 6.0.4 es la última versión publicada y declara soporte desde Bootstrap 5; en este repo se usa solo como capa de diálogo, no como dependencia visual fuerte.
- AngularJS 1.8.3 es la última versión publicada, pero AngularJS está EOL; no hay una ruta segura de parche menor que elimine el riesgo de producto. La ruta seria es migración.

## Recomendaciones seguras

- `jQuery`: mantener o subir a `3.7.1` si no está ya fijado así.
- `Bootbox`: fijar en `6.0.4` solo si el smoke test de modales pasa en este stack; si no, mantener la versión actual.
- `Bootstrap`: no subir dentro de 3.x; la línea está cerrada. No compensa inventar una upgrade party con este parque arqueológico.
- `AngularJS`: planificar migración, no parche cosmético.

## Rollback

- El rollback es mecánico:
  - retirar `src/public/js/dialog-a11y.js` del bundle en `v1/base.html.twig`;
  - revertir los cambios de `v1/base.html.twig`, `top.menu.html.twig`, `index.html.twig`, `styles.scss` y `styles.css`;
  - limpiar caché de Twig si aplica.
- No hay cambios de datos ni de backend.
