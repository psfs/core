# Base visual de Admin 2.0

## Decisiones

- Admin 2.0 usa una interfaz B2B sobria: cabecera oscura, barra lateral clara y un área de contenido de alto contraste. No incorpora una librería externa ni replica el aspecto de AngularJS.
- La identidad visual se apoya en una paleta limitada: gris azulado para estructura y verde turquesa para las acciones, estados y navegación activa. La tipografía usa la pila del sistema para evitar una dependencia de red.
- Cada ruta nativa se presenta como una página con encabezado, contexto, acción primaria y estado explícito. Tablas, chips, bloques de código, errores y cargas comparten clases reutilizables del stylesheet global.
- La barra lateral conserva el menú dinámico del backend. En escritorio permanece visible; en móvil se convierte en navegación desplegable accesible desde la cabecera.
- La interfaz mantiene los contratos actuales: mismas rutas Angular, llamadas `/admin/api/v2/*`, autenticación backend y cero iframes, Twig o AngularJS embebidos.

## Criterios de aceptación

- `/admin-v2/routes` y `/admin-v2/api/docs` se renderizan como páginas nativas completas con estilos compartidos.
- La acción principal, tablas y controles de dominio tienen estados visuales inequívocos.
- El layout funciona desde 320 px de ancho sin pérdida de navegación.
- La verificación incluye build Angular y Playwright con captura de pantalla real dentro de Docker.
