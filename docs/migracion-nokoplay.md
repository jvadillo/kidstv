# Migración a NokoPlay 2.0

El código, los recursos de marca, los comandos `nokoplay:*`, las variables
`NOKOPLAY_*` y el paquete `com.jonvadillo.nokoplay` usan ya la nueva identidad.
El destino configurado es **https://nokoplay.jonvadillo.com**. Los cambios del
repositorio no crean DNS, no verifican Resend y no migran por sí solos el VPS.

## Datos y servidor

Antes del primer despliegue, guarda una copia consistente de la base de datos,
los archivos privados y `.env.production`. Conserva `APP_KEY`. El nuevo nombre
de Docker Compose utiliza **otro volumen** (`nokoplay_storage`): arrancarlo
sin copiar los datos produciría una instalación vacía.

1. Anota el nombre del proyecto Compose actual, su volumen de almacenamiento y
   el nombre de su SQLite. Detén sus servicios durante la copia (incluidos cola
   y planificador). No elimines el volumen original ni las copias.
2. Crea `nokoplay_storage` y copia **todo** el contenido del volumen original,
   conservando permisos y propietarios. En la copia, renombra la base a
   `database/nokoplay.sqlite`. Si existen los archivos auxiliares SQLite
   `-wal` y `-shm`, copia y renombra también ambos con el mismo prefijo, con
   todos los procesos detenidos. Verifica la integridad y el número de cuentas,
   perfiles y contenidos antes de arrancar la aplicación.
3. Conserva los valores de `.env.production`, cambiando el prefijo de las
   variables de la aplicación a `NOKOPLAY_`, `APP_NAME=NokoPlay`,
   `APP_URL=https://nokoplay.jonvadillo.com`, `MAIL_FROM_NAME=NokoPlay` y
   `MAIL_FROM_ADDRESS=no-reply@nokoplay.jonvadillo.com`. Revisa también
   cualquier `SESSION_COOKIE`, `SESSION_DOMAIN` o prefijo de caché personalizado.
4. Configura el DNS del nuevo subdominio hacia el VPS. Verifica el dominio de
   envío en Resend y su clave antes de habilitar el acceso por correo.
5. Usa `~/projects/nokoplay` como directorio de destino. La URL Git remota se
   administra por separado: no presupongas que existe un repositorio con el
   nuevo nombre. Actualiza rutas de tareas externas y copias programadas.
6. Con los servicios anteriores detenidos y los datos verificados, ejecuta
   `bash ops/deploy.sh --no-pull`. Instala `ops/caddy/nokoplay.caddy` mediante
   `bash ops/install-site.sh`. Retira el sitio anterior del gateway tras
   comprobar el nuevo dominio y validar la configuración de Caddy.
7. Comprueba `/up`, acceso por correo, panel, contenidos propios, reproducción
   de YouTube, vinculación y descarga del paquete LG. Conserva el respaldo
   anterior hasta completar estas comprobaciones.

`ops/deploy.sh` detiene el despliegue si el puerto ya pertenece a otro proyecto
Compose, para que esta migración se haga antes de arrancar el nuevo servicio.
En caso de fallo, detén NokoPlay y restaura el código, entorno, gateway y
servicios anteriores con su volumen original.

## Navegadores y televisores

El nuevo dominio, las cookies y las claves locales usan la identidad NokoPlay.
Los adultos deben iniciar sesión de nuevo. La app LG 2.0.0 tiene un nuevo
identificador, por lo que se instala como otra aplicación: instala
`public/downloads/nokoplay-webos.ipk`, vincúlala y retira la instalación anterior.
Las cuentas, perfiles y contenidos se mantienen mediante la copia del servidor.
Repite la vinculación también en los navegadores que accedan a `/tv/`.

## Recursos de marca

Los originales están en `logos/`. `node tv-apps/make-icons.mjs` genera los
logotipos optimizados en `public/tv/img`, el favicon multirresolución, el icono
de inicio de iOS y los iconos de LG. No altera los originales ni requiere
dependencias. La web de padres, el correo y la TV comparten estos recursos.

Para generar la aplicación LG, sigue [webos.md](webos.md). El paquete incluye
las imágenes localmente y no necesita descargar la marca durante el arranque.
