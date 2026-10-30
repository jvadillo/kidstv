# Rediseño del panel parental de NokoPlay

Implementado el 30 de octubre de 2026 a partir de las cuatro capturas de referencia. El rediseño no requiere migraciones de datos ni cambios de configuración de producción.

## Fases

1. Tema propio del panel en `public/css/panel.css`: tokens, Fredoka y Nunito locales con sus licencias, tarjetas, botones, foco y tamaños táctiles.
2. Cabecera con el logotipo original y Modo TV. Cinco secciones; Administración pasa a Cuenta y conserva su permiso de administrador.
3. Inicio: saludo, conexión de televisión, tres accesos y perfiles compactos.
4. Biblioteca: búsqueda por lista o contenido, títulos completos, imágenes reales, filtros, estados, aviso de asignación, menú Añadir y asa de ordenación. El asa admite arrastre y un menú con formularios Subir/Bajar para teclado o sin JavaScript.
5. Perfiles: contador, estado vacío, CTA de asignación y un aviso por nombre duplicado. El contador incluye las listas públicas asignadas.
6. Cuenta: preferencias agrupadas, interruptor nativo con `role="switch"`, campos altos y botones actualizados. Se conservan todas las opciones anteriores.

## Diferencias respecto a las capturas

| Pantalla | Diferencia y motivo |
| --- | --- |
| Global | Logo y avatares originales. Las fuentes se sirven desde el proyecto. El tema parental es claro incluso si el sistema usa modo oscuro, siguiendo la referencia. |
| Accesibilidad | El azul `#1F6FE0` se conserva en botones. Sobre fondos azul suave, el texto usa una mezcla con el marino: el azul original solo alcanza 4,06:1. Los bordes de campos también tienen una variante de mayor contraste. Las líneas decorativas conservan el token indicado. |
| Inicio | Mantiene últimos contenidos, televisores, espacio usado y aviso de PIN debajo del bloque principal; eliminarlos ocultaría información existente. La edad se conserva cuando está informada. |
| Biblioteca | Todas/Públicas/Privadas sigue disponible solo para administradores, conforme a la lógica anterior. Los chips de perfil aparecen si hay varios perfiles. La búsqueda devuelve las listas que contienen coincidencias, incluido el grupo Sin lista. Se desactiva la ordenación al filtrar, como antes. |
| Perfiles | Los números, nombres, edades y estados proceden de los datos. El aviso de duplicados se muestra una vez por nombre y no modifica los perfiles. |
| Cuenta | No aparece Cambiar contraseña porque el acceso es por código de correo. Se mantienen PIN parental, cambio de correo, televisores, límites diarios, sesiones, exportación y eliminación; por eso la pantalla es más larga. |
| Escritorio | A partir de 1000 px, la navegación se sitúa arriba. Los perfiles aprovechan dos columnas; Cuenta limita el ancho del formulario para facilitar su lectura. |

## Verificación

- Suite PHP en entorno aislado, con SQLite en memoria: **101 pruebas, 1200 aserciones**, todas correctas.
- Generación de diez vistas mediante las plantillas reales y datos ficticios: diez respuestas correctas; compilación de Blade correcta.
- Navegador Edge/Chromium: **40 combinaciones** de vista y ancho, a **320, 390, 760 y 1280 px**, sin desbordamientos horizontales. Incluye las cuatro pantallas, Actividad, estados vacíos y títulos largos.
- axe-core sobre las diez vistas a 390 y 1280 px: sin infracciones detectadas de WCAG A/AA en las reglas automáticas ejecutadas. Esta comprobación no sustituye una auditoría manual completa con lectores de pantalla.
- Comprobación de objetivos táctiles, fuentes y logo; navegación de menús por teclado, cierre con Escape y foco; interruptor por Espacio; ordenación por ratón y eventos táctiles. El guardado y los permisos de ordenación también se comprueban en las pruebas PHP.
- JavaScript sin errores de ejecución y sin errores de sintaxis. `git diff --check` correcto.

Las capturas de la revisión están en `.qa/ui-screenshots/` (carpeta local de QA, excluida de Git), con variantes a 390 y 1280 px. Los archivos `*-viewport.png` muestran la primera pantalla y los demás la página completa. No representan datos de una familia real.

| Pantalla | Móvil (390 px) | Escritorio (1280 px) |
| --- | --- | --- |
| Inicio | [Captura](../.qa/ui-screenshots/home-390-viewport.png) | [Captura](../.qa/ui-screenshots/home-1280.png) |
| Biblioteca | [Captura](../.qa/ui-screenshots/library-390-viewport.png) | [Captura](../.qa/ui-screenshots/library-1280.png) |
| Perfiles | [Captura](../.qa/ui-screenshots/profiles-390-viewport.png) | [Captura](../.qa/ui-screenshots/profiles-1280.png) |
| Cuenta | [Página completa](../.qa/ui-screenshots/account-390.png) | [Página completa](../.qa/ui-screenshots/account-1280.png) |
