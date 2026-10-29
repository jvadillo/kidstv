# KidsTV · Videoteca familiar para Smart TV

Aplicación web (Laravel 13) en la que una persona adulta se registra solo con su correo, crea perfiles infantiles y selecciona vídeos propios (MP4), audios (MP3/M4A) y vídeos de YouTube para que sus hijos los vean en la tele, con el mando a distancia y sin salir de lo aprobado.

- **Producción:** https://kidstv.jonvadillo.com
- **App de TV en el navegador:** https://kidstv.jonvadillo.com/tv/
- **Paquete para LG webOS:** `public/downloads/kidstv-webos.ipk` (también descargable desde *Teles* en el panel)
- **Requisitos funcionales de partida:** [`requisitos_videoteca_familiar_claude_code.md`](requisitos_videoteca_familiar_claude_code.md)

## Documentación

| Documento | Contenido |
|---|---|
| [docs/despliegue.md](docs/despliegue.md) | Instalar, configurar, desplegar, administrar, actualizar y hacer copias en el VPS |
| [docs/webos.md](docs/webos.md) | Generar, instalar, probar y actualizar la app en televisores LG webOS |
| [docs/otras-plataformas.md](docs/otras-plataformas.md) | Cómo llevar la app de TV a Samsung Tizen, Android TV y otros |
| [docs/limitaciones.md](docs/limitaciones.md) | Limitaciones conocidas (YouTube, navegadores de Smart TV, servidor) |
| [docs/aceptacion.md](docs/aceptacion.md) | Estado de verificación de cada flujo de aceptación |

## Qué hace

**Panel de adultos** (web, pensado para móvil):

También se puede comenzar desde la TV, con el mando y reproducción provisional sin correo, o continuar en el móvil mediante QR y código. El correo y el PIN se configuran al guardar o entrar por primera vez a la Zona de adultos. [Flujos y comprobaciones](docs/onboarding-tv.md).
- Acceso sin contraseña: código de 6 cifras o enlace de un solo uso por correo; la sesión se mantiene en ese navegador (60 días, renovable) y se puede revocar desde *Cuenta*.
- Primeros pasos sin registrarse (`/empezar`), en tres decisiones y menos de 2 minutos: **para quién** (nombre y etapa de edad: 0–3, 3–6 o 7–12 años), **qué ve** (catálogo de listas públicas con portadas, filtrado ya por su edad y por idioma, que se eligen tocando sin recargar) y **su tele** (vista previa a escala de cómo quedará la app de TV, con «Conectar mi tele» o «Lo haré más tarde»). El borrador va en la sesión; el correo (código de 6 cifras, con las condiciones en la misma pantalla) solo se pide para guardarlo, y con un correo ya registrado se añade a esa familia. Límites, PIN y demás ajustes quedan para después.
- Perfiles infantiles con apodo y avatar predefinido (sin datos personales del menor).
- Biblioteca: **buscador de YouTube** con miniatura, duración, canal y visualizaciones, vista previa y «Añadir» en un clic (búsqueda segura estricta, solo vídeos insertables); con idioma preferido de los resultados (español, inglés, euskera, catalán y otros); o pegar el enlace (comprobación de inserción vía oEmbed); subir MP4 o MP3/M4A por fragmentos con progreso, listas de vídeos, orden, portada, asignación por perfil, ocultar temporalmente, estados *Listo / Procesando / No disponible / Error*.
- **Listas públicas**: listas preparadas por los administradores que cualquier familia añade a su videoteca tal cual (eligiendo qué perfiles las ven) y que se mantienen al día solas.
- Televisores (desde *Cuenta*): vincular por código o QR, renombrar, desvincular. Botón «Probar modo TV» para usar el propio navegador como tele.
- **Límite diario de tele** (*Cuenta → Preferencias*): sin límite, un tiempo compartido entre todos los niños (lo que ve cada uno gasta el de todos) o un límite para cada niño, cada uno con su control deslizante (de 5 min a 5 h). Se suma lo visto en todas las teles, sin contar la Zona de adultos.
- **Actividad**: tiempo de pantalla por día, semana o mes (total, medias, gráfico y navegación a periodos anteriores), lo que más ve cada niño (por tiempo o por veces, hasta el top 10), cuándo lo ha visto y tiempo por listas; para un niño o para todos. Junto al título, «Viendo ahora» si el niño elegido (o cualquiera, con «Todos») está viendo algo en ese momento; se actualiza cada minuto.
- Cuenta: PIN parental, cambio de correo, sesiones abiertas, exportación en JSON y eliminación de la cuenta (con revalidación por correo).

**Panel de administración** (`/admin`, solo cuentas nombradas con `kidstv:admin`):
- Resumen de uso: minutos vistos (hoy, 7 y 30 días, gráfico diario), cuentas nuevas, familias activas, televisores, biblioteca y espacio, vídeos de listas públicas más vistos.
- Usuarios: búsqueda y orden por actividad, uso o espacio; ficha con uso, perfiles, teles y listas; bloquear/desbloquear (sin panel y con las teles sin servicio), dar o quitar permisos de administrador y eliminar la cuenta.
- Listas públicas: crear, publicar una lista existente, editar y dejar de publicar; los vídeos se gestionan desde la biblioteca.

**App de TV** (la misma en `/tv/` y en el paquete webOS):
- Vinculación con código temporal + QR, sin escribir contraseñas en la tele.
- Selección de perfil → catálogo con una fila por lista (carrusel horizontal, como en las plataformas de vídeo; la duración en cada miniatura) → reproductor.
- Informa del tiempo reproducido y del perfil que lo ve, para el límite diario de cada niño y la pantalla *Actividad* (las estadísticas de administración solo usan totales).
- Navegación completa con flechas, OK y Atrás (también puntero/ratón opcional), foco visible y restaurado al volver del reproductor.
- Vídeo y audio propios a pantalla completa con pausa, avance/retroceso de 10 s; YouTube con el reproductor oficial (modo privacidad mejorada).
- Barra de pausa, avance y progreso para YouTube: automática, siempre visible u oculta, configurable para toda la familia desde *Cuenta → Preferencias*.
- Al terminar vuelve al catálogo (reproducción automática solo si el adulto la activa).
- Con límite diario, avisa al niño cuando queda poco tiempo y, al agotarse, muestra una pantalla de descanso animada hasta el día siguiente (el servidor también rechaza reproducir); otro niño con tiempo puede cambiar de perfil, y desde la Zona de adultos se puede seguir viendo.
- Zona de adultos con PIN validado en el servidor, espera progresiva tras fallos y bloqueo por inactividad.
- Pantallas para sin conexión, contenido retirado, error de reproducción y tele desvinculada.

## Arquitectura

```
                 Internet (HTTPS)
                        │
          Caddy compartido del VPS (TLS, kidstv.jonvadillo.com)
                        │ 127.0.0.1:8086
┌───────────────────────┴──────────────────────── docker compose "kidstv" ─┐
│ web (nginx)  ── estáticos, /tv/, X-Accel-Redirect de archivos privados   │
│     │ FastCGI                                                            │
│ app (php-fpm 8.4, Laravel 13) ── panel, API /api/tv, URLs firmadas       │
│ worker (queue:work) ── ffprobe/ffmpeg: validar, faststart, miniaturas     │
│ scheduler (schedule:work) ── limpieza horaria y copia diaria de la BD     │
│                                                                          │
│ volumen "storage": SQLite (WAL), archivos privados, copias, logs         │
└──────────────────────────────────────────────────────────────────────────┘
```

Decisiones con consecuencias para el producto:

- **Sin servicios externos de identidad, datos ni archivos.** Autenticación propia por correo (códigos con hash, un solo uso, 15 min, 5 intentos, límites por IP y correo), SQLite en el propio VPS y archivos en un volumen local. La única dependencia externa es el **envío** de correo (Resend, configurable por SMTP u otro transporte) y YouTube como fuente de contenido (reproductor oficial y, para el buscador del panel, la YouTube Data API v3).
- **SQLite en modo WAL** en lugar de un servidor de BD: suficiente para varias familias, sin un contenedor más en un VPS compartido con poca RAM, y copia de seguridad trivial. Cambiar a PostgreSQL solo requiere variables de entorno.
- **Sin recodificar vídeo**: la CPU del VPS es compartida. Se validan los códecs con ffprobe, se reubica el índice MP4 (`+faststart`) para que empiece a reproducirse al momento y se avisa si el códec no es H.264.
- **Archivos privados**: nunca están en la carpeta pública. La tele recibe URLs firmadas con caducidad (4–8 h) ligadas al dispositivo; en cada petición se vuelve a comprobar que la tele sigue vinculada a la familia propietaria. nginx los sirve con soporte de *Range*.
- **Tele = dispositivo con token**, nunca sesión de adulto. Su API solo permite consultar perfiles/catálogo y reproducir; con PIN, además, ver el catálogo completo o desvincularse. Las rutas del panel no aceptan el token.
- **App de TV en ES5 sin dependencias ni compilación** para funcionar en navegadores de televisores antiguos (webOS 3 / Chromium 38) y poder empaquetarse para otras plataformas con un `config.js` distinto.
- **YouTube mediante página puente** (`/tv/youtube`) servida desde nuestro dominio: el reproductor oficial exige un origen web válido, que una app empaquetada (`file://`) no tiene.

## Estructura

```
app/Http/Controllers/Auth     Acceso por correo, alta, revalidación
app/Http/Controllers/Panel    Panel de adultos (siempre consultando a través de la familia)
app/Http/Controllers/Tv       API del televisor y página puente de YouTube
app/Services                  Verificación por correo, sesiones, YouTube, almacenamiento, catálogo TV
app/Jobs                      Procesado de vídeos/audios subidos
public/tv                     App de TV (HTML/CSS/JS ES5)
public/tv-bridge              Página puente del reproductor de YouTube
tv-apps/                      Empaquetado por plataforma (webos/), build.mjs, iconos
ops/                          Dockerfile, nginx, php, Caddy, scripts de despliegue y copias
tests/Feature                 Tests de los flujos de aceptación, seguridad y aislamiento
```

## Desarrollo local

Necesitas PHP 8.3+ con `pdo_sqlite`, Composer y (opcional) ffmpeg.

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed   # cuenta de ejemplo: familia@example.com
php artisan serve                                              # http://localhost:8000
php artisan kidstv:login-link familia@example.com              # enlace de acceso sin enviar correo
```

Con `MAIL_MAILER=log`, los correos (códigos) se escriben en `storage/logs/`. La app de TV en local se abre desde el panel con «Probar modo TV».

Sin PHP local, con Docker:

```bash
docker run --rm -it -v "$PWD":/app -w /app composer:2 sh -c "composer install && php artisan test"
```

## Tests

```bash
php artisan test
node --test tests/JavaScript/youtube-player.test.cjs
```

Las pruebas JavaScript comprueban los modos de visibilidad, progreso, mando y puntero con la API de YouTube simulada, sin acceso a la red.

49 tests / 472 aserciones cubren alta y acceso por correo, sesión persistente y revocación, aislamiento entre familias (panel, API de TV y archivos), vinculación de televisores, catálogo por perfil, URLs firmadas y su caducidad, PIN con espera progresiva, subida por fragmentos, YouTube, borrado de contenido y de cuenta, y el buscador de YouTube (filtros, caché, duplicados, cuota agotada). Además se hizo un recorrido end-to-end real contra producción con Chrome (ver [docs/aceptacion.md](docs/aceptacion.md)).
