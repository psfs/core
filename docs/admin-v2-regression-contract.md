# Contrato de regresión de Admin 2.0

## Configuración

- Los valores con nombre `secret`, `password`, `token` o `hash` no se devuelven al navegador.
- Un secreto vacío significa «conservar el valor almacenado»; no significa borrar la credencial.
- Las nuevas claves se envían como un objeto limpio y el API v2 lo adapta al contrato histórico `label/value` antes de persistirlo.
- El catálogo de sugerencias contiene los parámetros requeridos, opcionales y secretos API de dominios disponibles.

## Usuarios

- El listado sólo publica alias, nombre de rol y clase visual. Nunca publica hashes ni contraseñas.
- La eliminación requiere confirmación explícita, CSRF de sesión y autorización del backend.
- El listado se filtra localmente y se pagina en grupos de veinte para mantener una interfaz utilizable con muchos administradores.

## Formularios y documentación

- Un error de una operación no desmonta el shell Angular ni invalida su navegación.
- Swagger se muestra con Swagger UI nativo de Angular, nunca mediante iframe ni como JSON crudo. El documento se pide al endpoint histórico del dominio, conservando Swagger 2.0 y OpenAPI 3.1.
- El selector de idioma delega en `/admin/locale/{locale}`, que conserva el mecanismo de sesión de PSFS. Los textos de contratos y navegación procedentes del backend se actualizan tras recargar.

## Pruebas

- PHPUnit cubre serialización segura de usuarios, secretos en blanco, parámetros extra y rutas de documentación.
- Angular cubre formularios, confirmación, componentes de documentación y secreto preservable.
- Playwright cubre configuración, navegación tras fallo del generador, usuarios y documentación bajo Basic Auth, todo dentro de Docker.
