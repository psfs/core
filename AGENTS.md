# AGENTS Instructions

## Regla de ejecucion del proyecto

Este proyecto se ejecuta siempre con Docker Compose.
La version de PHP para ejecucion y validacion es **8.3**.

- Levantar servicios: `docker compose up -d`
- Para ejecutar cualquier comando del proyecto, usar siempre `docker exec <nombre_del_contenedor> <comando>`

## Ejemplos

- `docker exec <nombre_del_contenedor> php -v`
- `docker exec <nombre_del_contenedor> php vendor/bin/phpunit`
- `docker exec <nombre_del_contenedor> composer test`

## Obtener el nombre del contenedor PHP

Si no conoces el nombre del contenedor:

- `docker compose ps`
- o `docker ps --format '{{.Names}}'`

Evitar ejecutar comandos de PHP/Composer/Test directamente en el host local.

## Contrato de seguridad y autenticacion (coordinacion)

- Auth/cookies versionadas en **v2** con fallback legado solo lectura.
- El retiro de fallbacks solo se permite con OK explicito del usuario.
- Resultado esperado para auth invalida: `null/null` y el flujo debe cortarse.
- Cookies objetivo:
  - `HttpOnly=true`
  - `Secure=true` en HTTPS
  - `SameSite=Lax` o `SameSite=Strict`
  - `Path=/`
  - `Domain` coherente
  - TTL alineado con la sesion

## Politica de commits

- No commitear automaticamente cambios de agentes.
- Esperar validacion humana explicita antes de cualquier commit.
