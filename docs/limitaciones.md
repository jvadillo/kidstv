# Limitaciones conocidas

## YouTube

- **Inserción decidida por el propietario.** Algunos vídeos no se pueden reproducir fuera de YouTube (errores 101/150), están restringidos por edad o región, o se borran/privatizan después de añadirlos. El panel lo comprueba al añadir (oEmbed) y la tele lo vuelve a detectar al reproducir: muestra un aviso, sigue funcionando el resto de la biblioteca y el contenido queda como «No disponible» en el panel (con «Volver a comprobar»).
- **Anuncios, logotipo y sugerencias.** El reproductor oficial puede mostrar anuncios, el logotipo, un título con enlace y vídeos sugeridos al pausar o al terminar. No se pueden eliminar; se usa el modo de privacidad mejorada (`youtube-nocookie.com`), `rel=0` y la app vuelve al catálogo al terminar. La tele avisa al empezar un vídeo de YouTube y el panel informa al adulto.
- **Interacción con el reproductor.** Con el mando normal la app controla reproducir/pausar/avanzar; los botones propios de YouTube (p. ej. «Saltar anuncio») solo son pulsables con el puntero del Magic Remote o el ratón.
- **Controles y progreso.** En *Cuenta → Preferencias → Controles de YouTube en la tele* se elige entre automáticos (por defecto, desaparecen tras 4 segundos reproduciendo y reaparecen al usar el mando), siempre visibles u ocultos. La barra de KidsTV se muestra debajo del vídeo, con pausa, saltos de 10 segundos y posición. La preferencia se aplica a todas las teles de la familia al abrir el siguiente vídeo; el mando funciona también con la barra oculta. Los controles nativos de YouTube siguen su propia ocultación automática y se desactivan con la opción «Ocultos»; esta opción no elimina anuncios, enlaces o sugerencias. YouTube ya no admite fijar su barra nativa mediante `autohide` ([parámetros oficiales](https://developers.google.com/youtube/player_parameters)).
- **Autoplay.** Algunos navegadores bloquean el inicio automático con sonido; en ese caso basta pulsar OK.
- **Disponibilidad de la API.** Si YouTube cambia las condiciones del reproductor insertado (como el requisito de origen web de 2025, resuelto con la página puente), puede requerir ajustes.
- **Buscador del panel.** Usa la YouTube Data API v3 y requiere una clave de Google. Con la cuota gratuita da para unas 95 búsquedas al día en todo el servidor (con caché de 1 hora). Usa búsqueda segura estricta y solo muestra vídeos insertables, pero **no garantiza** que todo resultado sea adecuado para niños: el adulto debe revisarlos (hay vista previa) antes de añadirlos. El filtro «para niños» depende de cómo lo marque cada canal. Para un lanzamiento público hay que cumplir las [políticas de la API de YouTube](https://developers.google.com/youtube/terms/developer-policies) (atribución, y no conservar datos de la API más de 30 días salvo los del propio usuario).
- **No se descargan ni se copian vídeos de YouTube** en el servidor, por diseño. Sin conexión no se pueden ver.
- Antes de un lanzamiento público hay que revisar las [condiciones de la API de YouTube](https://developers.google.com/youtube/terms/api-services-terms-of-service) aplicables a servicios dirigidos a menores.

## Navegadores de Smart TV y formatos

- **Verificación en LG real pendiente.** La app se ha probado con navegación por teclado, reproducción de MP4/MP3 y YouTube en Chrome (1920×1080), y se ha comprobado que el código es ES5 válido para Chromium 38. La lista de verificación de [webos.md](webos.md#4-probar-lista-de-verificación-en-tele-real) debe completarse en un televisor LG físico: no hubo acceso a uno durante el desarrollo.
- **Códecs.** MP4 con H.264 + AAC es lo más compatible. HEVC, VP9 y AV1 se aceptan con aviso (dependen del modelo); otros códecs se rechazan con un mensaje claro. **No se recodifica** en el servidor (CPU compartida). Archivos con perfiles H.264 altos o 4K pueden no reproducirse en televisores antiguos.
- **Volumen.** Lo controla el propio televisor con sus teclas; la app no lo modifica.
- **webOS 3 y anteriores (2016 o antes):** rendimiento limitado y posibles problemas con los certificados actuales de Let's Encrypt (cadena ISRG Root X1). webOS 1–2 no está soportado.
- **Modo desarrollador de LG** caduca si no se renueva y desinstala la app (ver [webos.md](webos.md#6-limitaciones-de-la-instalación-en-modo-desarrollador)).
- **Posición de reproducción.** Al volver de un vídeo se conserva el foco en el catálogo, pero no se recuerda el minuto por el que iba el vídeo.
- **Actualización del catálogo** en la tele: cada 60 segundos o al cambiar de pantalla, no en tiempo real.
- Si se revoca una tele mientras reproduce, el archivo ya en el búfer puede terminar de verse; la siguiente petición (avance, otro contenido, catálogo) ya se rechaza.

## Servidor y producto

- **Correo.** El acceso depende de que el correo llegue. Se envía con Resend desde `no-reply@kidstv.jonvadillo.com`; si Resend no está disponible o la clave se revoca, nadie podrá entrar desde un dispositivo nuevo (las sesiones ya abiertas siguen funcionando y `kidstv:login-link` sirve de emergencia).
- **Espacio en disco.** El VPS es compartido y tiene poco espacio libre; por defecto 500 MB por vídeo, 3 GB por familia y un margen de 1,5 GB libres en el disco. Para muchas familias habría que ampliar disco o mover los archivos a un volumen dedicado.
- **Copias de seguridad** automáticas de la base de datos en el mismo disco; los archivos solo se copian manualmente (`ops/backup.sh --media`).
- **Una persona adulta por familia** en esta versión (el modelo de datos ya separa familia y usuario, para añadir más adultos en el futuro).
- **Listas públicas**: se añaden tal cual; las familias no pueden quitarles ni añadirles vídeos (sí quitarlas enteras). Son listas de la familia del administrador: si se elimina esa cuenta, desaparecen de todas las videotecas.
- **Estadísticas de uso**: el tiempo visto lo envían las teles con la versión nueva de la app; un paquete webOS antiguo no lo envía hasta reinstalarlo. Se guarda por tele y contenido, nunca por perfil infantil, y se borra a los 12 meses.
- **Idioma de los resultados de YouTube**: es una preferencia (`relevanceLanguage`), no un filtro; pueden aparecer vídeos en otros idiomas.
- **Sin buscador** en la tele (por diseño) ni control por horarios (fuera de alcance).
- **Protección anti-abuso** básica (campo trampa, tiempo mínimo, límites por IP y correo); no hay CAPTCHA para no depender de servicios externos.
- **Textos legales** (privacidad y condiciones) son una base razonable, pero deben revisarse legalmente antes de abrir el servicio al público, en particular por tratarse de un servicio usado por menores. Verificar un correo no acredita la patria potestad.
- Interfaz solo en castellano; los textos ya pasan por `__()` (panel) y `i18n.js` (TV) para añadir idiomas.
