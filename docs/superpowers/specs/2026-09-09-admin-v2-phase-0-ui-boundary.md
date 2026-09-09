# Admin 2.0: fase 0 — frontera de migración UI

## Decisión

Admin 2.0 es una migración de presentación. Angular 22 sustituye Twig y
AngularJS en `/admin-v2/`; no reescribe el dominio PSFS, la persistencia ni las
APIs generadas de cada módulo.

Los controladores `AdminFrontend*` ya existentes son adaptadores de interfaz:
pueden serializar formularios y estado administrativo para Angular, pero deben
delegar reglas de negocio, validación de dominio, permisos y persistencia en
los componentes PSFS existentes. Esta fase no crea endpoints CRUD v2 para los
managers generados.

## Alcance de la fase 0

1. Establecer el contrato operativo entre Angular, los adaptadores de admin y
   las rutas legacy que siguen siendo necesarias.
2. Registrar diferencias de paridad que bloquean la activación de v2 por
   defecto.
3. Definir criterios verificables para que cada fase posterior cambie una sola
   superficie funcional.

No incluye cambios de producción, eliminación de legacy, cambios de cookies o
autenticación, creación de managers CRUD v2, ni modificación de `config.json`.

## Frontera de responsabilidades

| Capa | Responsabilidad permitida | No permitida en esta migración |
|---|---|---|
| Angular 22 | Navegación, presentación, formularios, errores y confirmaciones | Autorizar, persistir o reinterpretar reglas de dominio |
| `AdminFrontend*` | Bootstrap, esquema de formulario seguro y envoltura JSON | Duplicar servicios de negocio o crear un manager CRUD paralelo |
| Formularios/servicios PSFS | Validación, normalización y efectos actuales | Ser sustituidos por validación exclusiva del navegador |
| API generada de módulos | Lectura temporal desde el manager nativo | Mutaciones desde Angular sin una decisión posterior explícita |
| `/admin/*` legacy | Fallback y rollback | Retirada sin aprobación humana explícita |

## Matriz contractual vigente

| Flujo UI | Ruta | Método | Contrato consumido | Estado |
|---|---|---:|---|---|
| Shell | `/admin/api/v2/bootstrap` | GET | Identidad limitada, locale, CSRF y menú | V2 existente |
| Idioma | `/admin/api/v2/locale/{locale}` | PUT | `{ ok, message, data, errors }` | V2 existente |
| Configuración | `/admin/api/v2/config` | GET, PUT | Esquema seguro y `{ values, extra }` | V2 existente; paridad de efectos pendiente |
| Usuarios | `/admin/api/v2/users` | GET, POST, DELETE | Lista saneada y formulario | V2 existente; edición no disponible |
| Módulos | `/admin/api/v2/modules/schema`, `/admin/api/v2/modules` | GET, POST | Esquema y resultado | V2 existente |
| Rutas | `/admin/api/v2/routes`, `/admin/api/v2/routes/regenerate` | GET, POST | Catálogo y regeneración | V2 existente; protección de mutación pendiente |
| Documentación | `/admin/api/v2/docs`, `/admin/api/v2/docs/{domain}` | GET | Catálogo y documento | V2 existente |
| Manager | `/admin/api/v2/managers/{domain}/{api}` + `/{DOMAIN}/api/{API}` | GET | Metadatos v2 y lectura legacy | Puente temporal de sólo lectura |

Para todos los adaptadores de mutación, Angular envía `X-PSFS-CSRF` y el
backend conserva su autorización actual. La respuesta de mutación y de error
es `AdminEnvelope`: `{ ok, message, data, errors }`. El bootstrap es una
excepción intencionada: se consume como estado inicial del shell.

## Reglas de compatibilidad

- Los campos que contengan `secret`, `password`, `token` o `hash` se entregan
  vacíos y `preserveIfEmpty=true`; un vacío conserva el secreto almacenado.
- Angular no navega a HTML legacy desde una ruta `/admin-v2/`.
- El manager puede consultar una API generada sólo para listar o ver detalle.
  No muestra acciones de alta, edición o borrado.
- `admin.front.version=legacy` mantiene el flujo actual. `v2` y
  `__front=v2` activan el shell; `__front=legacy` permite rollback.
- La autenticación y las cookies v2 siguen el contrato PSFS vigente. Un estado
  de autenticación inválido detiene el flujo; no hay degradación anónima.

## Bloqueadores de paridad a resolver en fases posteriores

1. Configuración v2 persiste, pero no reproduce todavía el refresco de caché
   ni la limpieza condicional del document root del flujo legacy.
2. La regeneración de rutas es mutación administrativa y debe tener una
   política explícita de CSRF y privilegio antes de habilitar v2 por defecto.
3. No existe edición de usuarios en v2; no debe prometerse hasta acordar el
   contrato y probar su equivalencia.
4. Swagger UI depende de CDN; el empaquetado local o una política CSP explícita
   es requisito antes de un despliegue restringido.
5. El bridge WebSocket de HMR para Admin 2.0 no es estable; producción se
   valida por entrega estática, no por el socket de desarrollo.

## Criterios de salida de fase 0

- La matriz anterior coincide con las anotaciones de ruta y el cliente Angular.
- Cada pendiente tiene dueño de fase: configuración/rutas en fase 1, usuarios
  en fase 2, manager en fase 3 y entrega operativa en fase 4.
- El equipo acepta que el manager sigue siendo un puente de lectura legacy y
  que no hay trabajo de dominio backend autorizado por esta migración.
- La rama conserva `/admin/*` como fallback y no cambia la configuración que
  selecciona la versión por defecto.

## Criterio de no retorno

No activar `admin.front.version=v2` por defecto ni retirar `/admin/*` hasta
que las fases 1 a 4 hayan demostrado paridad, autenticación, rollback y
operación estática en navegador real.
