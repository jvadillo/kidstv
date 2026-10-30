# App instalable (PWA) y «Modo TV» táctil

## Dos aplicaciones instalables

| | Panel | NokoPlay TV |
|---|---|---|
| Manifiesto | `public/manifest.webmanifest` | `public/tv/manifest.webmanifest` |
| `id` / `scope` / `start_url` | `/` | `/tv/` |
| Pantalla | `standalone` | `fullscreen` (en iOS, `standalone`) |
| Para quién | Adultos: biblioteca, perfiles, actividad | Los peques: la videoteca directamente |

- **Panel:** en el *Inicio* aparece un aviso discreto para instalarla (se puede cerrar; no vuelve en 30 días) y en *Cuenta → Aplicación*, siempre. Con `beforeinstallprompt` (Chrome, Edge) se instala con un toque; en iPhone/iPad se explica *Compartir → Añadir a pantalla de inicio*, y en navegadores móviles sin instalación en un toque (Brave, Samsung Internet), el menú del navegador. Si `getInstalledRelatedApps` dice que ya está instalada, no se ofrece.

## Abrir el «Modo TV» desde el panel

`panel.js` pide el token de este navegador con `fetch` (`POST /panel/televisores/este-navegador`, respuesta JSON), lo guarda en `localStorage` (`nokoplay.token`, el mismo origen que `/tv/`) y abre `/tv/`: en el ordenador en otra pestaña, y en el móvil o en la app instalada en la misma ventana (Atrás vuelve al panel). Antes era un POST con `target="_blank"` que redirigía a `/tv/#token=…`; con NokoPlay TV instalada, Android abría la app al ver la redirección y la pestaña se quedaba en la URL del formulario, que el navegador volvía a pedir con GET al recargarla (405). Ahora ese GET redirige a `/tv/`, y sin JavaScript se mantiene el POST con redirección.
- **NokoPlay TV:** desde la Zona de adultos de `/tv/` en un móvil, tableta u ordenador, «Añadir a la pantalla de inicio». Como el ámbito `/tv/` está dentro de `/`, ambas se pueden instalar a la vez. Desde esa misma Zona de adultos, «Abrir el panel de adultos» vuelve al panel.
- Iconos: `public/img/pwa/` (192 y 512 px, normales y *maskable* con zona segura), generados por `node tv-apps/make-icons.mjs` a partir de `logos/isotipoPNG.png`.

## Service worker (`public/sw.js`)

Registrado desde `public/js/panel.js` y, fuera de las teles, desde la app de TV (`core.js`), con ámbito `/`.

- **Páginas y API: siempre a la red.** Llevan datos de la familia y no se guardan. Sin red, el panel muestra `public/offline.html` y la app de TV abre su última portada (`/tv/`), que muestra su propio aviso de conexión y reintenta sola.
- **Recursos del panel con `?v=`:** caché primero (cada versión es inmutable); solo se guarda la última versión de cada archivo.
- **App de TV (`/tv/css|js|img`, `config.js`):** red primero, caché solo sin conexión (no llevan versión en la URL).
- **Imágenes, avatares y fuentes:** caché primero.
- **Nunca pasan por la caché:** peticiones que no son GET, peticiones con `Range` (vídeo y audio), `/api/`, portadas y archivos de `/panel/…` y otros dominios (miniaturas de YouTube).

Al cambiar las estrategias o la lista de precarga, sube `CACHE` (`nokoplay-v1` → `v2`): la activación borra las cachés anteriores. nginx sirve `sw.js`, los manifiestos y `offline.html` con `Cache-Control: no-cache`, así las actualizaciones llegan en la siguiente visita.

## «Modo TV» en móviles, tabletas y ordenadores

La app de TV es la misma en las teles y en el navegador. `core.js` añade la clase `pointer-ui` cuando `platform` es `web` y el agente de usuario no es de una Smart TV (webOS, Tizen, Android TV, Fire TV…). Con ella:

- Las filas (horizontal) y la lista de filas (vertical) usan **desplazamiento nativo**: dedo, rueda del ratón o trackpad, con *scroll snap* suave. En las teles siguen moviéndose con el foco del mando y animación.
- Se recuerda la posición de cada fila y de la lista al volver del reproductor.
- Tras tocar la pantalla no se dibuja el marco de foco; al usar las flechas del teclado vuelve a aparecer.
- Botón en pantalla para salir del reproductor y para volver desde el PIN; tocar el vídeo muestra los controles y tocar la barra de progreso salta a ese punto.
- El **Atrás del sistema** (gesto o botón de Android, Alt+← en el ordenador) equivale a la tecla Atrás fuera de las pantallas principales.
- Tamaño de letra mínimo según el lado corto de la pantalla, y en vertical las pantallas de vinculación, PIN, Zona de adultos y primeros pasos pasan a una columna.

El paquete webOS no cambia de comportamiento (`platform: 'webos'`); no hace falta reinstalarlo.

## Comprobación

- `node --test tests/JavaScript/` (estrategias del service worker y detección de tele/móvil) y `php artisan test --filter=PwaTest` (manifiestos, iconos y enlaces).
- Recorrido con Edge headless por DevTools Protocol y API simulada: móvil vertical 390×844 y horizontal 844×390 con eventos táctiles reales (deslizar filas y lista, tocar perfil, tarjeta, volver, Atrás del sistema, PIN y Zona de adultos), ordenador 1366×768 con rueda horizontal y vertical, y tele 1920×1080 con agente webOS y flechas (filas con transformaciones, sin entradas en el historial). Chrome informó el manifiesto de `/tv/` sin errores e instalable, con el service worker activo.
