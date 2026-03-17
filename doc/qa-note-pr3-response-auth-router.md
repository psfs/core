# QA Note PR-3 (non-blocking)

Fecha: 2026-03-17  
Ámbito revisado: `ResponseHelper`, `AuthHelper`, `Router`  
Tipo: revisión de seguridad/calidad/performance, sin bloquear entrega

## Hallazgos y ajustes mínimos recomendados

1. `Router::executeCachedRoute` mezcla params con prioridad final de query string.
Recomendación: evitar que `Request::getQueryParams()` sobreescriba params de ruta críticos (`id`, `slug`, etc.) en acciones sensibles.  
Ajuste mínimo: merge con prioridad de ruta (`array_merge(query, actionDefaults, routeParams)` o allowlist de override).

2. `AuthHelper::checkComplexAuth` valida token Basic con `str_contains`.
Recomendación: usar validación de prefijo estricto (`preg_match('/^Basic\\s+/i', ...)`) para evitar matches parciales ambiguos.  
Impacto: endurecimiento de parsing, sin romper contrato funcional esperado.

3. `AuthHelper` mantiene fallback legacy criptográfico y de hashes.
Estado: correcto para compatibilidad legacy controlada.  
Recomendación: añadir contador/telemetría agregada por contexto (no por request) para planificar retirada progresiva sin ruido de logs.

## Estado general

- No se detectan regressions bloqueantes en los cambios revisados.
- Las recomendaciones anteriores son incrementales y de bajo riesgo.

