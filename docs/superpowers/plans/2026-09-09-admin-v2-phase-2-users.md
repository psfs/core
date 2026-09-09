# Admin 2.0: fase 2 — edición segura de usuarios

## Objetivo

Completar la paridad de la pantalla nativa de usuarios sin crear lógica de
identidad nueva: listar, crear, editar la contraseña/rol y eliminar usando los
servicios PSFS existentes.

## Contrato

- `GET /admin/api/v2/users` entrega alias, rol visible, clase y el identificador
  de perfil necesario para preseleccionar el formulario; nunca hash ni
  contraseña.
- `PUT /admin/api/v2/users/{username}` conserva el alias de la ruta, exige CSRF
  y superadmin, valida con `AdminForm` y persiste mediante `Security::save()`.
- La contraseña permanece write-only y es obligatoria en cada actualización.
- Un alias inexistente recibe 404; un alias distinto en el body recibe 422.

## UI

La tabla ofrece una edición explícita. El alias se precarga y no se permite
renombrarlo; el formulario reinicia sus controles sólo al seleccionar o
cancelar una edición, nunca durante la escritura del usuario.

## Verificación

1. PHPUnit con doubles de controlador para no tocar `admins.json`.
2. Vitest de la página para comprobar la llamada `PUT` y la invariabilidad del
   alias.
3. Playwright contra Vite y el mock API aislado: editar un usuario, comprobar
   `PUT /admin/api/v2/users/admin` y el mensaje de éxito.
