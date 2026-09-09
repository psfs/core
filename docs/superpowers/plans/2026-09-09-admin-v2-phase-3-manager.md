# Admin 2.0: fase 3 — frontera de lectura del manager

## Objetivo

Separar la pantalla Angular de los detalles de las APIs generadas de PSFS sin
ampliar permisos ni abrir CRUD v2.

## Decisiones

- `ManagerApiService` es la única capa Angular que conoce los metadatos v2 y
  los endpoints generados de consulta.
- `ManagerPageComponent` sólo consume esa abstracción; no compone rutas ni
  realiza llamadas HTTP directamente.
- El contrato de metadatos conserva `mutation.supported=false`. Crear, editar o
  borrar registros queda fuera de esta fase hasta disponer de adaptadores v2
  con autorización y CSRF de servidor.
- Vite reenvía el endpoint de lectura del fixture al mock API. Playwright
  comprueba la tabla y el aviso de sólo consulta sin necesitar PHP.

## Verificación

1. Vitest garantiza paginación y filtro a través de `ManagerApiService`.
2. Playwright abre `/admin-v2/CLIENT/Related`, recibe su lista paginada y no
   ofrece mutaciones.
3. PHPUnit existente mantiene validación de segmentos y 404 del metadato v2.
