# Llevar la app de TV a otras plataformas

La app de TV está diseñada para reutilizarse casi entera en cualquier Smart TV con motor web:

| Capa | Dónde | ¿Cambia por plataforma? |
|---|---|---|
| Interfaz, navegación con mando, reproductores, zona de adultos | `public/tv/` (HTML, CSS, JS ES5) | No |
| Textos | `public/tv/js/i18n.js` | No (se añaden idiomas) |
| Servidor y plataforma | `config.js` generado por `tv-apps/build.mjs` | Sí: `apiBase`, `platform` |
| Manifiesto e iconos | `tv-apps/<plataforma>/` | Sí |
| Teclas especiales y salir de la app | `public/tv/js/core.js` (`KEYS`, `K.exitApp`) | Ya contempla webOS, Tizen y Android TV |
| API, vinculación, catálogo, archivos | Servidor (`/api/tv`) | No |

El servidor ya acepta `platform` = `webos`, `tizen`, `androidtv` o `web` al pedir un código de vinculación (se muestra en el panel junto a cada tele).

## Pasos genéricos para una plataforma nueva

1. Crea `tv-apps/<plataforma>/` con su manifiesto e iconos (`build.mjs` copia `appinfo.json`, `config.xml`, `icon.png` y `largeIcon.png` si existen).
2. `node tv-apps/build.mjs <plataforma> --server https://kidstv.jonvadillo.com` → `tv-apps/<plataforma>/dist/`.
3. Empaqueta `dist/` con la herramienta del fabricante.
4. Revisa en `core.js` los códigos de tecla del mando (Atrás, multimedia) y la forma de cerrar la app.
5. Pasa la lista de verificación de [webos.md](webos.md#4-probar-lista-de-verificación-en-tele-real) en un dispositivo real.

## Samsung Tizen

- Proyecto *Web Application* de Tizen Studio con `config.xml`; copia en él el contenido de `dist/` (generado con `build.mjs tizen`).
- Privilegios en `config.xml`: `http://tizen.org/privilege/internet` y `<access origin="*" subdomains="true"/>` (la API y YouTube son remotos).
- La tecla Atrás (10009) ya está mapeada; para recibir teclas multimedia hay que registrarlas con `tizen.tvinputdevice.registerKeyBatch(['MediaPlayPause','MediaPlay','MediaPause','MediaStop','MediaFastForward','MediaRewind'])` al arrancar (añadir en `core.js` si `window.tizen` existe).
- `K.exitApp()` ya usa `tizen.application.getCurrentApplication().exit()`.
- Firma con certificado de Samsung e instalación en modo desarrollador con `sdb` / Device Manager.

## Android TV / Google TV

- Aplicación Android mínima (Kotlin) con una `Activity` a pantalla completa y un `WebView` que cargue `file:///android_asset/index.html` (el contenido de `dist/` en `assets/`) o directamente `https://kidstv.jonvadillo.com/tv/`.
- Ajustes del WebView: `javaScriptEnabled`, `domStorageEnabled`, `mediaPlaybackRequiresUserGesture = false`; `WebChromeClient` para vídeo a pantalla completa.
- Mapear el botón Atrás: en `onBackPressed` / `OnBackPressedCallback`, inyectar un `KeyboardEvent` con `keyCode` 27 en el WebView (la app ya lo trata como Atrás) y, para salir, exponer una interfaz JS que llame a `finish()`.
- Declarar `android.software.leanback` y `LEANBACK_LAUNCHER` en el manifiesto con un banner de 320×180.

## Otros (Fire TV, Hisense VIDAA, navegadores)

Cualquier dispositivo con un navegador moderno puede usar `https://kidstv.jonvadillo.com/tv/` directamente. Fire TV admite el mismo enfoque que Android TV.

## Qué no hace falta tocar

La lógica de seguridad (token de dispositivo, URLs firmadas, PIN validado en el servidor, aislamiento por familia) vive en el servidor, de modo que una plataforma nueva hereda las mismas garantías sin código adicional.
