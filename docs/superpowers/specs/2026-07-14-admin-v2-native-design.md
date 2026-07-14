# Admin 2.0 nativo: diseño de transición

## Objetivo

Sustituir las pantallas administrativas AngularJS/Twig por rutas Angular 22
nativas bajo `/admin-v2/`, sin `iframe`, sin HTML Twig como contrato y sin
mezclar ambos frameworks en el mismo DOM.

Legacy permanece disponible bajo `/admin/*`. El selector de versión y
`__front=legacy|v2` siguen siendo el mecanismo de activación y rollback.

## Límites de esta fase

Incluye las áreas principales: configuración, usuarios, generador de módulos,
rutas y documentación. Incluye el shell, bootstrap de sesión y catálogo de
navegación.

No incluye aún el manager API genérico. Se abordará con un módulo PSFS de
prueba proporcionado por el usuario tras cerrar estas áreas.

El perfilado fino de permisos se programa después de que los flujos nativos
funcionen. Ningún endpoint elimina las comprobaciones de seguridad existentes.

## Arquitectura

`AdminFrontendController` deja de ser sólo bootstrap y actúa como fachada JSON
administrativa v2. Extiende `AuthAdminController`; por tanto, toda petición
requiere autenticación administrativa antes de ejecutar lógica de negocio.

Cada adaptador llama a las mismas formas, servicios y validadores que los
controladores legacy, pero devuelve DTOs JSON. No interpreta ni extrae HTML
Twig. Las mutaciones devuelven `ok`, `message`, `data` y `errors` y no emiten
redirecciones o flashes.

Angular implementa `AdminApiService`, formularios basados en metadatos, manejo
de errores y pantallas independientes. Las rutas Angular no hacen carga de
documentos legacy.

## Contratos

| Área | Endpoint | Resultado |
|---|---|---|
| Sesión y menú | `GET /admin/api/v2/bootstrap` | identidad limitada y rutas visibles |
| Configuración | `GET`, `PUT /admin/api/v2/config` | esquema, valores y errores por campo |
| Usuarios | `GET`, `POST`, `PUT`, `DELETE /admin/api/v2/users` | administradores y validación estructurada |
| Módulos | `GET /admin/api/v2/modules/schema`, `POST /admin/api/v2/modules` | formulario y resultado del generador |
| Rutas | `GET /admin/api/v2/routes`, `POST /admin/api/v2/routes/regenerate` | catálogo y resultado de regeneración |
| Documentación | `GET /admin/api/v2/docs`, `GET /admin/api/v2/docs/{domain}` | dominios y definición API |

Los contratos de configuración, usuarios y módulo describen campos mediante
`name`, `label`, `type`, `value`, `required`, `options`, `help` y reglas de
validación. Los valores sensibles nunca se devuelven como texto plano.

## Orden de entrega

1. Eliminar los paneles iframe y crear el cliente Angular común.
2. Migrar rutas y documentación: lectura y bajo riesgo.
3. Migrar configuración y usuarios: formularios y mutaciones controladas.
4. Migrar generador de módulos.
5. Validar por Playwright contra legacy, incluidos Basic Auth, deep-links,
   errores y operaciones persistentes.
6. Crear el módulo de prueba y migrar el manager API genérico.

## Criterios de aceptación

- No existe `iframe` ni navegación a `/admin/*` desde una ruta v2.
- Cada menú principal renderiza una pantalla Angular y usa sólo JSON v2.
- Cada mutación conserva la autorización backend y comunica errores de forma
  estructurada.
- `admin.front.version=legacy` conserva legacy; `v2` y `__front=v2` llevan al
  shell Angular.
- Playwright verifica cada flujo principal en navegador contra los contenedores
  Docker.
