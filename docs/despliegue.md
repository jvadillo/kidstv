# Despliegue y operación en el VPS

KidsTV se ejecuta en el VPS compartido (`deploy@2.28.118.113`) como un proyecto de Docker Compose independiente, detrás del Caddy compartido que ya sirve las demás aplicaciones. No necesita `sudo`.

| Elemento | Valor |
|---|---|
| Código | `~/projects/kidstv` (clon de `git@github.com:jvadillo/kidstv.git`, rama `main`) |
| Configuración secreta | `~/projects/kidstv/.env.production` (permisos 600, fuera de git) |
| Contenedores | `kidstv-app-1`, `kidstv-worker-1`, `kidstv-scheduler-1`, `kidstv-web-1` |
| Puerto interno | `127.0.0.1:8086` (solo accesible desde el propio VPS) |
| Sitio Caddy | `~/projects/gateway/sites/kidstv.caddy` → `kidstv.jonvadillo.com` |
| Datos | volumen Docker `kidstv_storage` (BD SQLite, archivos, copias, logs) |
| Límites | app 384 MB / worker 320 MB / scheduler 128 MB / web 64 MB de RAM |

## 1. Instalación desde cero

Requisitos: Docker con Compose v2, acceso de `deploy` a GitHub por SSH, el Caddy compartido funcionando (`~/projects/gateway`) y un registro DNS `A` de `kidstv.jonvadillo.com` hacia la IP del VPS.

```bash
cd ~/projects
git clone git@github.com:jvadillo/kidstv.git
cd kidstv

# Configuración
cp ops/env.production.example .env.production
chmod 600 .env.production
KEY=$(docker run --rm php:8.4-cli-alpine php -r "echo 'base64:'.base64_encode(random_bytes(32));")
sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" .env.production
nano .env.production        # correo (ver sección 3) y límites de almacenamiento

# Construir, migrar y arrancar
bash ops/deploy.sh --no-pull

# Publicar en Internet (copia el sitio a Caddy, valida y recarga sin cortar las demás webs)
bash ops/install-site.sh
```

Caddy obtiene el certificado TLS automáticamente en cuanto el DNS apunta al VPS. Comprobación: `curl -I https://kidstv.jonvadillo.com/up` → `200`.

> **Importante:** guarda una copia de `APP_KEY` fuera del servidor. Sin ella no se pueden descifrar las sesiones ni las vinculaciones pendientes de una copia de seguridad.

## 2. Actualizar a una nueva versión

```bash
cd ~/projects/kidstv
bash ops/deploy.sh
```

El script hace `git pull --ff-only`, construye las imágenes etiquetadas con el commit (`kidstv-app:<sha>`), ejecuta las migraciones, recrea los contenedores, reinicia la cola, comprueba `/up` y borra imágenes antiguas (conserva las dos últimas).

**Volver a la versión anterior:**

```bash
git log --oneline -5                 # localiza el commit anterior
git checkout <sha> && bash ops/deploy.sh --no-pull
git checkout main                    # cuando se publique la corrección
```

Las migraciones de KidsTV solo añaden estructura; si una versión futura incluye una migración destructiva se indicará en su commit.

Los cambios en la app de TV (`public/tv`) llegan a los navegadores y a la pantalla `/tv/` al desplegar. La app **instalada** en televisores LG lleva su propia copia del código: ver [webos.md](webos.md#actualizar-la-app) para reinstalarla. El catálogo, en cambio, se actualiza siempre sin reinstalar.

## 3. Correo (códigos de acceso)

El acceso depende de que los correos lleguen. Opciones en `.env.production`:

**Resend (recomendado, el mismo proveedor que usa erronk2d):**
```env
MAIL_MAILER=resend
RESEND_API_KEY=re_xxxxxxxx
MAIL_FROM_ADDRESS=no-reply@kidstv.jonvadillo.com
MAIL_FROM_NAME=KidsTV
```
Configurado en producción el 25/10/2026 con una clave de Resend **solo de envío** autorizada para el dominio `kidstv.jonvadillo.com` (no sirve para `jonvadillo.com` ni para otros subdominios). La clave vive únicamente en `.env.production` del servidor; si se cambia, actualízala ahí y redespliega.

**SMTP genérico:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.ejemplo.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
```

**Sin proveedor (solo uso personal o pruebas):** `MAIL_MAILER=log`. Los correos se escriben en `storage/logs/mail-AAAA-MM-DD.log`:
```bash
docker exec kidstv-app-1 sh -c 'grep -h "digo: " storage/logs/mail-*.log | tail -3'
```

Tras cambiar `.env.production`: `bash ops/deploy.sh --no-pull` (o `docker compose -f compose.production.yml up -d --force-recreate app worker scheduler`).

## 3 bis. Buscador de YouTube (opcional)

El panel incluye un buscador de YouTube que usa la **YouTube Data API v3** de Google. Sin clave, la función se oculta y los adultos añaden vídeos pegando el enlace.

Obtener la clave (cuenta de Google, gratis):
1. Entra en <https://console.cloud.google.com/>, crea un proyecto (p. ej. «KidsTV»).
2. *APIs y servicios → Biblioteca* → busca **YouTube Data API v3** → **Habilitar**.
3. *APIs y servicios → Credenciales → Crear credenciales → Clave de API*.
4. Edita la clave y **restríngela**: *Restricciones de API* → solo «YouTube Data API v3»; *Restricciones de aplicación* → «Direcciones IP» → `2.28.118.113`.
5. Añádela al servidor y redespliega:
   ```bash
   sed -i "s|^YOUTUBE_API_KEY=.*|YOUTUBE_API_KEY=AIza...|" .env.production || echo "YOUTUBE_API_KEY=AIza..." >> .env.production
   bash ops/deploy.sh --no-pull
   ```

**Cuota:** el plan gratuito da 10 000 unidades diarias y cada búsqueda (o página de resultados) gasta unas 101, es decir, **unas 95 búsquedas al día** para todo el servidor. Los resultados se guardan en caché una hora y cada adulto puede hacer como máximo 40 búsquedas por hora. Si se agota, el panel lo explica y se puede seguir pegando enlaces; Google permite solicitar más cuota desde la consola.

## 4. Administración diaria

Todos los comandos desde `~/projects/kidstv`; abrevia con `alias kc='docker compose -f compose.production.yml'`.

| Tarea | Comando |
|---|---|
| Estado de los contenedores | `kc ps` |
| Logs de la aplicación | `docker exec kidstv-app-1 sh -c 'tail -f storage/logs/laravel-*.log'` |
| Logs del procesado de vídeos | `kc logs -f worker` |
| Logs de acceso HTTP | `kc logs -f web` |
| Entrar sin correo (cuenta existente) | `kc exec app php artisan kidstv:login-link correo@ejemplo.com` |
| Limpiar códigos/sesiones/subidas caducadas | `kc exec app php artisan kidstv:prune` (automático cada hora) |
| Copia de la base de datos | `kc exec app php artisan kidstv:backup` (automático a las 03:40) |
| Trabajos fallidos | `kc exec app php artisan queue:failed` / `queue:retry all` |
| Consola | `kc exec app php artisan tinker` |
| Espacio usado por archivos | `kc exec app du -sh storage/app/private` |
| Reiniciar todo | `kc restart` |

**Eliminar los datos de una familia a petición** (si no puede hacerlo ella desde *Cuenta → Eliminar cuenta*):
```bash
kc exec app php artisan tinker --execute='$u=App\Models\User::where("email","correo@ejemplo.com")->firstOrFail(); app(App\Services\MediaLibrary::class)->deleteFamilyFiles($u->family); $u->family->delete();'
```

### Límites de almacenamiento

El disco del VPS es compartido (unos 6 GB libres en el momento del despliegue). La aplicación rechaza subidas que superen:

| Variable | Valor por defecto | Significado |
|---|---|---|
| `KIDSTV_MAX_VIDEO_MB` | 500 | Tamaño máximo de un vídeo |
| `KIDSTV_MAX_AUDIO_MB` | 60 | Tamaño máximo de un audio |
| `KIDSTV_FAMILY_QUOTA_MB` | 3072 | Espacio total por familia |
| `KIDSTV_MIN_FREE_DISK_MB` | 1536 | Espacio libre que debe quedar en el disco tras una subida |

El último protege a las demás aplicaciones del VPS: aunque una familia tenga cuota, no se acepta un archivo si dejaría el disco por debajo de ese margen.

## 5. Copias de seguridad y restauración

- **Automática:** cada noche `kidstv:backup` crea `storage/backups/kidstv-<fecha>.sqlite` (copia consistente con `VACUUM INTO`) y conserva las 7 últimas. Está en el mismo disco: protege frente a errores, no frente a la pérdida del servidor.
- **Manual / fuera del contenedor:**
  ```bash
  bash ops/backup.sh            # BD → ~/backups/kidstv/kidstv-<fecha>.sqlite
  bash ops/backup.sh --media    # además, archivos → ~/backups/kidstv/kidstv-media-<fecha>.tar.gz
  ```
  Copia después `~/backups/kidstv` a otro equipo (`scp`/`rsync`) junto con `.env.production`.

**Restaurar la base de datos:**
```bash
kc stop app worker scheduler
docker run --rm -v kidstv_storage:/s -v ~/backups/kidstv:/b alpine \
  sh -c 'cp /b/kidstv-AAAAMMDD-HHMMSS.sqlite /s/database/kidstv.sqlite && rm -f /s/database/kidstv.sqlite-wal /s/database/kidstv.sqlite-shm && chown 82:82 /s/database/kidstv.sqlite'
kc start app worker scheduler
```

**Restaurar archivos:**
```bash
docker run --rm -v kidstv_storage:/s -v ~/backups/kidstv:/b alpine \
  sh -c 'tar xzf /b/kidstv-media-AAAAMMDD-HHMMSS.tar.gz -C /s/app && chown -R 82:82 /s/app/private'
```

## 6. Seguridad aplicada

- TLS y HSTS en Caddy; CSP estricta, `X-Frame-Options`, `nosniff` y `Referrer-Policy` desde la aplicación/nginx.
- Contenedores sin capacidades (`cap_drop: ALL`), `no-new-privileges`, nginx con sistema de archivos de solo lectura, límites de memoria/CPU/procesos.
- Sin puertos públicos: solo `127.0.0.1:8086`, accesible a través de Caddy.
- Códigos y tokens almacenados como hash; cookies `Secure`, `HttpOnly`, `SameSite=Lax`.
- Límites de peticiones: alta/acceso (5/min y 30/h por IP, 6/h por correo), códigos (5 intentos), vinculación, PIN (espera progresiva 30 s → 15 min), API de TV.
- `robots.txt` y cabeceras `noindex` en todas las páginas y archivos.

## 7. Problemas frecuentes

| Síntoma | Causa probable / solución |
|---|---|
| No llegan los códigos | `MAIL_MAILER`/`RESEND_API_KEY` incorrectos o dominio sin verificar; revisa `storage/logs/laravel-*.log`. Mientras tanto, `kidstv:login-link`. |
| Vídeo en «Procesando» indefinidamente | El worker está parado: `kc ps`, `kc logs worker`, `kc restart worker`. |
| «El servidor no tiene espacio suficiente» | Disco del VPS por debajo de `KIDSTV_MIN_FREE_DISK_MB`. Libera espacio (`docker image prune`, logs) o reduce el margen. |
| 502 en la web | `kc ps`; si `app` reinicia en bucle, `kc logs app` (p. ej. `APP_KEY` vacía). |
| Certificado no emitido | DNS aún no apunta al VPS; `docker logs web-gateway-caddy-1 --tail 50`. |
