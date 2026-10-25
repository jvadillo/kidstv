# Verificación de los flujos de aceptación

Fuentes de evidencia:
- **Test**: tests automáticos (`php artisan test`, 39 tests / 417 aserciones, en `tests/Feature`).
- **E2E prod**: recorrido real con Chrome (Playwright) contra https://kidstv.jonvadillo.com el 24/10/2026: panel en viewport móvil 390×844 y tele en 1920×1080 manejada solo con teclado. Las cuentas de prueba se eliminaron después.
- **LG real**: pendiente de ejecutar la lista de [webos.md §4](webos.md#4-probar-lista-de-verificación-en-tele-real) en un televisor LG; no hubo acceso a uno durante el desarrollo.

| # | Flujo | Estado | Evidencia |
|---|---|---|---|
| 1 | Email → código/enlace → panel sin contraseña | ✅ | Test `AuthFlowTest` (código, enlace de un solo uso, términos). E2E prod: alta con código real leído del servidor, error con código incorrecto («Te quedan 4 intentos»), aceptación de condiciones, asistente. |
| 2 | Volver al día siguiente sin otro email salvo cierre/caducidad/revocación | ✅ | Test: cookie persistente tras «un día» → panel; tras revocar → login. E2E prod: nuevo contexto de navegador solo con la cookie → entra en `/panel`. |
| 3 | Dos perfiles, MP4 + MP3 + YouTube, asignación distinta | ✅ | Test `LibraryTest`. E2E prod: perfiles Ane e Iker, YouTube real (título vía oEmbed), MP4 y MP3 subidos por fragmentos y procesados con ffmpeg → «Listo». |
| 4 | Cada perfil ve solo lo suyo; cambiar de perfil actualiza el catálogo | ✅ | Test `TvTest`. E2E prod: Ane = [Nana del mar, Big Buck Bunny]; Iker = [Nana del mar, Barras de colores]. |
| 5 | Vincular la tele con código y entrar con el mando | ✅ (navegador) · ⏳ LG | Test (código, QR, entrega única del token, caducidad, no reutilizable). E2E prod: código en pantalla de TV, introducido en el móvil, la tele pasa sola a los perfiles. |
| 6 | Flechas y OK, MP4 y audio, volver, sin admin sin PIN | ✅ (navegador) · ⏳ LG | E2E prod: foco inicial, OK, MP4 reproduciendo, → avanza 10 s, OK pausa, Atrás vuelve con el foco en el mismo vídeo; audio reproduciendo. Test: el token de TV no abre ninguna ruta del panel; catálogo completo y desvincular exigen PIN. |
| 7 | YouTube no permitido → aviso sin romper; permitido → reproductor oficial | ✅ (navegador) · ⏳ LG | Test: errores 401/403/404 de oEmbed con mensaje claro; códigos 100/101/150 desde la tele marcan «No disponible». E2E prod: reproductor oficial cargado mediante la página puente (evento `ready`). |
| 8 | Borrar en el móvil → desaparece de la tele | ✅ | Test (archivo borrado, catálogo y reproducción 404). E2E prod: borrado de «Barras de colores» y desaparece del catálogo de Iker. |
| 9 | Revocar una tele → pierde el acceso | ✅ | Test (API 401 y URL firmada 403). E2E prod: la tele muestra «Esta tele se ha desvinculado». |
| 10 | Dos cuentas no acceden a datos ajenos | ✅ | Test `FamilyIsolationTest`: 404 en todas las rutas del panel sobre perfiles, contenidos, archivos, categorías, teles y sesiones ajenas; validación impide asignar perfiles/categorías/contenidos ajenos; la API de TV y las URLs firmadas no sirven datos de otra familia, ni manipulando el parámetro de dispositivo. |
| 11 | Instalar en un LG real, vincular, navegar y reproducir | 📦 Listo · ⏳ LG | Paquete `kidstv-webos.ipk` generado con `ares-package` (sin errores) y publicado. Falta la prueba física. |
| 12 | Instrucciones de instalación/actualización LG y ampliación a otras plataformas | ✅ | [webos.md](webos.md), [otras-plataformas.md](otras-plataformas.md). |

## Defectos encontrados y corregidos durante la verificación

- El middleware de autenticación se ejecutaba antes que el que restaura la sesión persistente (prioridad de middleware de Laravel): la cookie no servía. Corregido y cubierto por test.
- Las URLs firmadas de archivos para la tele caducaban en décadas en lugar de horas (un entero se interpretaba como segundos desde ahora). Corregido y cubierto por test.
- En la tele, Derecha al final de una fila saltaba al botón de la cabecera; ahora izquierda/derecha no salen de la fila.
- En el PIN de la tele, tras teclear con los números del mando, OK añadía otra cifra; ahora OK confirma.
- Mensajes de error de YouTube en el panel y desbordamiento horizontal en móvil con títulos largos.
