# QA visual: PSFS Admin flat Nordic

## Evidencia

- Fuente visual: `/Users/fran.lopez/.codex/generated_images/01a12157-b3f6-7283-876e-eb941d21e775/exec-3c5bc425-6c22-4983-b805-e408c5c8adfe.png`.
- Implementación integrada: `http://localhost:8080/admin-v2/`.
- Captura de implementación: inspeccionada inline con Codex In-app Browser; la herramienta no entregó una ruta de archivo local.
- `localhost:4200/admin-v2/` sirve el watcher Angular aislado y no tiene el backend PSFS; para revisar la aplicación integrada usar `localhost:8080/admin-v2/`.
- Vista de referencia: escritorio, objetivo 1440 × 1024 px.
- Vista capturada: panel estrecho de Codex; una emulación temporal informó `window.innerWidth=1440`, pero la captura visible quedó recortada al ancho del panel. No se usa para comparación de densidad o proporciones.
- Estado en `localhost:8080`: el bootstrap funciona y monta el launcher administrativo. La imagen de referencia muestra una tabla de módulos; el frontend actual tiene un generador de módulos, no ese listado.

## Revisión disponible

- Geometría: comprobación en navegador de `border-radius: 0px` para sidebar y contenido; controles, paneles, botones y tablas usan esquinas rectas en la hoja de estilos.
- Tokens y color: sidebar comprobada en `rgb(20, 33, 46)`, con contenido claro y acento teal.
- Comportamiento visible: menú responsive abre y cierra; el estado abierto conserva navegación legible.
- Iconos: cinco iconos SVG Tabler aparecen en la navegación del build integrado; el botón móvil cambia entre menú y cierre.
- Tipografía, contenido de la tabla, alineación de escritorio y comparación completa de la composición: sin validar porque la ruta de contenido no carga y la captura disponible está recortada.

## Hallazgos

- La tabla de módulos del mock es ilustrativa y no corresponde a una ruta existente; el launcher integrado sí carga correctamente.

## Checklist

- [x] Aplicar el tema plano al shell y a los componentes administrativos existentes.
- [x] Eliminar radios, sombras y fondos con gradiente del admin.
- [x] Compilar el frontend de admin y ejecutar sus tests unitarios.
- [ ] Comparar una página administrativa funcional a 1440 × 1024 px.

final result: partial
