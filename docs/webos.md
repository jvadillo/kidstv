# App de KidsTV para televisores LG (webOS)

La app de TV es una aplicación web empaquetada (`.ipk`) que se instala en la tele en **modo desarrollador**. Contiene la interfaz completa (HTML/CSS/JS) y se conecta a `https://kidstv.jonvadillo.com` para el catálogo, la vinculación y los archivos. Los cambios del catálogo se ven sin reinstalar; los cambios de la propia interfaz requieren reinstalar el paquete.

- Paquete ya generado: `public/downloads/kidstv-webos.ipk` (descargable en https://kidstv.jonvadillo.com/downloads/kidstv-webos.ipk y desde *Teles* en el panel).
- Identificador: `com.jonvadillo.kidstv` · Compatible con webOS TV 3.0 o superior (2016+); recomendado webOS 4.0+.

## 1. Generar el paquete

Requisitos: Node.js 18+ (las herramientas de LG se descargan con `npx`).

```bash
bash tv-apps/webos/package.sh                               # servidor por defecto
bash tv-apps/webos/package.sh https://otro-servidor.ejemplo  # otra instalación
```

Pasos que hace:
1. `node tv-apps/build.mjs webos --server <url>` copia `public/tv` y los avatares a `tv-apps/webos/dist/`, escribe un `config.js` con la URL del servidor y añade `appinfo.json` e iconos.
2. `ares-package` (de `@webos-tools/cli`) genera `tv-apps/webos/out/com.jonvadillo.kidstv_<versión>_all.ipk`.
3. Lo copia a `public/downloads/kidstv-webos.ipk` (súbelo al repositorio y despliega para publicarlo).

Para una versión nueva, incrementa `version` en `tv-apps/webos/appinfo.json` antes de empaquetar.

## 2. Preparar la tele (una sola vez)

1. Crea una cuenta gratuita en <https://webostv.developer.lge.com> (botón *Sign in → Create account*).
2. En la tele: abre **LG Content Store**, busca e instala **Developer Mode**.
3. Abre *Developer Mode* e inicia sesión con esa cuenta.
4. Activa **Dev Mode Status** → la tele se reinicia.
5. Vuelve a abrir *Developer Mode* y activa **Key Server**. Anota la **IP** de la tele y la **Passphrase** de 6 caracteres que se muestran.
6. El ordenador y la tele deben estar en la misma red local.

## 3. Instalar

En el ordenador (Windows, macOS o Linux) con Node.js:

```bash
npm install -g @webos-tools/cli          # o anteponer «npx -p @webos-tools/cli» a cada comando

# Registrar la tele (una vez). Responde: add, nombre "salon", IP de la tele, puerto 9922, usuario prisoner, sin contraseña.
ares-setup-device

# Obtener la clave de la tele (pide la Passphrase del paso 2.5)
ares-novacom --device salon --getkey

# Instalar y abrir
ares-install --device salon public/downloads/kidstv-webos.ipk
ares-launch  --device salon com.jonvadillo.kidstv
```

La app aparece entre las aplicaciones de la tele con el icono amarillo de KidsTV.

### Vincular la tele a una familia

1. Al abrir KidsTV se muestra un código de 6 caracteres (p. ej. `MQ6 4RK`) y un QR.
2. En el móvil, entra en el panel → *Teles* → *Conectar tele* (o escanea el QR) y escribe el código y un nombre.
3. En unos segundos la tele muestra los perfiles. El código caduca a los 10 minutos y no se puede reutilizar.

## 4. Probar (lista de verificación en tele real)

Marca cada punto en una LG real tras instalar:

- [ ] Se abre a pantalla completa y muestra el código y el QR de vinculación.
- [ ] La vinculación desde el móvil funciona y aparecen los perfiles de la familia.
- [ ] Flechas mueven el foco de forma predecible; OK selecciona; Atrás vuelve (perfiles ← catálogo ← reproductor).
- [ ] En la pantalla de perfiles, Atrás pregunta si salir; «Salir» cierra la app.
- [ ] Cada perfil ve solo sus contenidos (y las listas públicas que tiene asignadas), en una fila por lista, con la duración en cada miniatura; izquierda/derecha recorren la fila hasta el último vídeo y arriba/abajo cambian de fila recordando la posición de cada una.
- [ ] MP4 propio: se reproduce, OK pausa/reanuda, ← → retroceden/avanzan 10 s, al terminar vuelve al catálogo con el foco en ese vídeo.
- [ ] MP3 propio: portada, barra de progreso y controles.
- [ ] YouTube: se carga el reproductor oficial; Atrás vuelve al catálogo. Si un vídeo no permite inserción, aparece el aviso y se puede seguir navegando.
- [ ] Zona de adultos: pide PIN (teclado en pantalla y teclas numéricas del mando); tras 3 fallos impone espera; con PIN correcto permite ver todo el catálogo y desvincular.
- [ ] Borrar un contenido en el móvil: desaparece de la tele (en ≤ 1 minuto o al volver al catálogo).
- [ ] Desvincular la tele desde el móvil: la tele muestra «Esta tele se ha desvinculado» y vuelve al código.
- [ ] Sin wifi: aparece «Sin conexión a Internet» y se recupera sola al volver la red.
- [ ] Magic Remote (puntero): pasar por encima enfoca y pulsar selecciona.

Depuración: `ares-inspect --device salon --app com.jonvadillo.kidstv --open` abre las herramientas de desarrollo de Chrome conectadas a la app.

## 5. Actualizar la app

1. Genera el nuevo `.ipk` (incrementando la versión).
2. `ares-install --device salon public/downloads/kidstv-webos.ipk` sobre la instalación existente. La vinculación se conserva (se guarda en el almacenamiento local de la app).

Desinstalar: `ares-install --device salon --remove com.jonvadillo.kidstv`.

## 6. Limitaciones de la instalación en modo desarrollador

- **Caducidad:** la sesión de *Developer Mode* dura un número limitado de horas (el contador *Remaining time* aparece en la app; históricamente 50 h, ampliable con **Extend**). Si llega a cero, la tele **desactiva el modo desarrollador y desinstala las apps instaladas así**. Hay que abrir *Developer Mode* y pulsar *Extend* periódicamente, o volver a activar el modo y reinstalar. La vinculación se pierde si se desinstala (se vuelve a vincular con un código nuevo).
- Requiere una cuenta de desarrollador de LG y un ordenador en la misma red para instalar.
- Mientras el modo desarrollador está activo, la tele expone un servicio SSH en la red local (protegido por clave). Desactívalo si dejas de usarlo.
- Publicar en la LG Content Store (fuera del alcance de esta entrega) eliminaría estas limitaciones; requiere revisión de LG, política de privacidad pública y, por dirigirse a menores, revisión normativa previa.

## 7. Alternativa sin instalación

Cualquier tele con navegador (incluido el de LG) puede abrir `https://kidstv.jonvadillo.com/tv/` y vincularse igual. Diferencias: el navegador muestra su barra, la tecla Atrás puede salir de la página y no hay icono propio. Útil para probar o en televisores donde no se quiera activar el modo desarrollador.

## 8. Detalles técnicos relevantes

- `appinfo.json` declara `"disableBackHistoryAPI": true` para que la tecla Atrás (código 461) llegue a la app en lugar de cerrar la página, y `"resolution": "1920x1080"`.
- La interfaz está escrita en ES5 sin `fetch`, sin CSS Grid ni variables CSS: funciona en el Chromium 38 de webOS 3.
- Los vídeos de YouTube se cargan en un iframe que apunta a `https://kidstv.jonvadillo.com/tv/youtube?v=…`, que a su vez carga el reproductor oficial (`youtube-nocookie.com`). Así YouTube recibe un origen web válido aunque la app se ejecute desde `file://`. La app controla la reproducción con `postMessage` y recupera el foco si el puntero pulsa dentro del reproductor.
