# Requisitos funcionales — Videoteca familiar para Smart TV

**Versión:** 1.0 · **Destino:** implementación desde cero con Claude Code

**Restricciones:** Laravel · despliegue en VPS propio · sin servicios externos para autenticación, datos o almacenamiento (YouTube solo como fuente de contenido embebido).

## 1. Objetivo del producto

Crear una aplicación de videoteca familiar en la que cualquier adulto pueda registrarse, seleccionar contenidos y ofrecer a sus hijos una experiencia de visualización sencilla y supervisada desde la televisión. El catálogo de cada familia combina vídeos propios, audios propios y enlaces a vídeos de YouTube.

La primera versión debe servir para uso personal y estar preparada funcionalmente para admitir múltiples familias independientes sin rediseñar el producto.

La aplicación se desarrollará utilizando Laravel y se desplegará en un VPS. No utilizar servicios externos de autenticación, gestión de usuarios, bases de datos ni almacenamiento como dependencia del producto. La reproducción de contenido procedente de YouTube es una excepción explícita: se utilizará su reproductor oficial y se respetarán sus condiciones y limitaciones.


## 2. Usuarios, roles y dispositivos

- Adulto responsable: crea su cuenta, gestiona perfiles infantiles, añade y elimina contenido, configura la seguridad y vincula televisores.
- Perfil infantil: no tiene credenciales propias; visualiza únicamente el contenido que el adulto le haya asignado.
- Televisor vinculado: muestra el entorno infantil de una familia; debe poder utilizarse con mando a distancia y sin introducir contraseñas de adulto.
- Una familia puede tener varios perfiles infantiles y varios televisores. Los datos de familias diferentes nunca deben ser visibles entre sí.
- La gestión se realiza principalmente desde una web cómoda en móvil y ordenador; la TV se utiliza principalmente para reproducir y navegar.

## 3. Alta y acceso de adultos

- Registro inicial: solicitar únicamente el correo electrónico; enviar un correo con enlace de verificación de un solo uso o código temporal para validar que el adulto controla esa dirección.
- No exigir nombre de usuario ni contraseña en el registro. El nombre visible del adulto es opcional y puede editarse después.
- Una vez verificado el correo, crear la cuenta y permitir iniciar la configuración de la familia.
- En sucesivas visitas desde el mismo navegador/dispositivo, mantener al adulto identificado sin exigir un nuevo correo cada vez, mientras la sesión siga siendo válida.
- Si se usa otro dispositivo, se cierra sesión o esta deja de ser válida, repetir la verificación del email.
- Permitir cerrar sesión; revocar sesiones desde la cuenta; solicitar confirmación adicional para cambios sensibles y eliminación de cuenta.
- Mostrar mensajes claros para correos inexistentes, enlaces vencidos, códigos incorrectos y reenvío de verificación; aplicar límites razonables a reintentos.
- Solicitar la aceptación de condiciones y facilitar la información de privacidad pertinente antes de activar la cuenta pública.

## 4. Incorporación de una nueva familia

- Tras verificar el email, guiar al adulto con pocos pasos: crear el primer perfil infantil, añadir el primer contenido y empezar a probar la videoteca desde el navegador.
- El perfil infantil necesita solo apodo/nombre visible y avatar predeterminado. No requerir nombre completo, fecha de nacimiento ni fotografía.
- Permitir saltarse temporalmente la creación de contenido y regresar al panel con instrucciones claras.
- No exigir la vinculación de una televisión para utilizar el sistema: primero puede probarse desde móvil u ordenador.
- Ofrecer una pantalla inicial vacía útil, con acciones destacadas de Añadir vídeo de YouTube, Subir vídeo, Subir audio y Conectar televisión.

## 5. Biblioteca de contenidos

- Admitir tres clases de contenido: archivos de vídeo propios (al menos MP4), archivos de audio propios (al menos MP3) y vídeos alojados en YouTube añadidos mediante enlace.
- Para cada contenido: título, imagen de portada o miniatura, categoría, tipo, descripción opcional y perfiles infantiles autorizados.
- Permitir subir, editar, ordenar, clasificar y eliminar los archivos propios, con información clara de formatos y límites definidos por la aplicación.
- Para YouTube, permitir pegar una URL, reconocer el vídeo, completar los datos disponibles y mostrar un error entendible si el enlace es inválido o el vídeo no admite reproducción incrustada.
- El adulto decide qué contenidos están visibles en cada perfil. Ningún vídeo debe hacerse público automáticamente a otras familias.
- Organizar contenidos mediante categorías y, preferiblemente, colecciones o listas creadas por el adulto.
- Mostrar estados de publicación y carga: contenido listo, procesando, no disponible y error.
- No descargar ni copiar a nuestro servidor vídeos de YouTube; reproducirlos mediante los mecanismos oficiales permitidos.
- Si YouTube muestra anuncios, enlaces o sugerencias en su reproductor, no prometer que puedan eliminarse; informar de esta limitación al adulto.

## 6. Experiencia infantil en televisión

- Pantalla de inicio con selección sencilla de perfil infantil, siempre dentro de la familia vinculada.
- Interfaz visual y legible a distancia: portadas grandes, categorías claras, pocos elementos simultáneos y mensajes comprensibles.
- Navegación completa con flechas, OK/Seleccionar y Atrás del mando; foco visible y predecible; nunca exigir ratón o pantalla táctil.
- Mostrar solo contenido aprobado para el perfil activo. Sin buscador abierto de Internet o YouTube; sin enlaces a la administración.
- Reproducir vídeos propios a pantalla completa, permitir pausa, reanudación, volumen y avance/retroceso cuando el formato y el dispositivo lo permitan.
- Para audio, mostrar portada y controles claros de reproducción.
- Para YouTube, utilizar la reproducción oficialmente permitida, con explicación de restricciones y pruebas reales en TV.
- Al terminar un vídeo, volver al catálogo por defecto; no pasar automáticamente a otro vídeo salvo que el adulto active una opción explícita.
- Conservar la posición de navegación al regresar desde un reproductor, siempre que sea posible.
- Mostrar pantallas adecuadas ante pérdida de red, contenido retirado, error del reproductor o sesión de TV revocada.

## 7. Modo niño, modo adulto y protección parental

- La interfaz infantil es el modo predeterminado en televisión.
- La opción Zona de adultos estará visible pero protegida mediante un PIN definido por el adulto; evitar usar operaciones matemáticas como única barrera.
- Limitar los intentos incorrectos y aplicar una espera progresiva o bloqueo temporal.
- Recuperar o cambiar el PIN desde el panel del adulto tras revalidar su identidad por email; nunca mostrarlo al niño.
- Preferir que la edición del catálogo, las cuentas y los permisos se haga desde móvil u ordenador, no desde el televisor.
- La protección no debe ser solo visual: desde el modo niño nunca se podrán ejecutar acciones de gestión aunque se manipulen las peticiones.
- El adulto podrá volver al modo niño fácilmente y el modo adulto se bloqueará tras inactividad.

## 8. Conexión y gestión de televisores

- Un televisor no vinculado mostrará un código temporal y, si es viable, un QR para su activación.
- El adulto, desde su sesión web, autoriza la vinculación introduciendo el código o escaneando el QR.
- Una vez vinculado, el televisor muestra los perfiles y contenidos de esa familia sin necesitar un email ni una contraseña en la TV.
- El código de vinculación caduca y no puede reutilizarse; la familia puede poner nombre a sus televisores.
- Permitir listar y desvincular dispositivos desde el panel; una TV desvinculada debe perder el acceso al contenido de la familia.
- La sesión del televisor debe limitarse a consultar perfiles, consultar catálogo y reproducir contenido, nunca administrar la cuenta.
- La primera entrega debe incluir una aplicación de televisión **instalable y funcional en un televisor LG con webOS real**. No es suficiente con que el sitio web pueda abrirse desde el navegador de la televisión.
- Se debe proporcionar el procedimiento completo para generar, instalar, probar y actualizar la aplicación en LG/webOS, incluyendo las limitaciones que pueda tener la instalación privada o en modo desarrollador (por ejemplo, caducidad y necesidad de renovación). No se exige publicarla en la tienda de LG en el MVP.
- La aplicación de TV debe diseñarse de manera que más adelante pueda adaptarse, empaquetarse y distribuirse para otros sistemas de Smart TV (Samsung/Tizen, Android TV/Google TV u otros) **reutilizando la mayor parte de la experiencia y del catálogo**. No se requiere entregar esas versiones ahora.
- La navegación con mando, el inicio, la vinculación familiar y la reproducción de MP4, audios y contenidos de YouTube compatibles deben verificarse específicamente en LG/webOS. Si YouTube no puede reproducirse en un modelo o contexto concreto, debe explicarse la limitación sin impedir el funcionamiento del resto de la biblioteca.

## 9. Panel de adultos

- Inicio con vista de perfiles, últimos contenidos añadidos, acceso inmediato a añadir contenido y estado de televisores vinculados.
- Gestión de los perfiles infantiles y asignación de contenidos por perfil.
- Gestión del catálogo: subir archivo, pegar URL de YouTube, editar metadatos, categorías, ordenar y eliminar.
- Gestión de PIN, correo, sesiones, dispositivos vinculados y eliminación de cuenta.
- Diseño prioritariamente móvil, útil también en ordenador, sin necesidad de instalar una app específica.
- Incluir advertencias sencillas sobre privacidad de los archivos propios y límites del reproductor de YouTube.

## 10. Privacidad, protección y funcionamiento público

- Cada cuenta solo puede ver y administrar los datos de su propia familia.
- Los archivos personales no serán accesibles libremente por otras familias ni se incluirán en índices públicos.
- Minimizar datos personales infantiles; no usar publicidad dirigida a menores ni perfiles comerciales de niños.
- Ofrecer información de privacidad, términos y mecanismo para exportar o eliminar los datos de la familia, conforme a las obligaciones aplicables.
- Las cuentas deben poder solicitar eliminación, que incluya perfiles y archivos privados asociados dentro de los plazos y excepciones legales.
- Aplicar medidas para evitar registros automatizados abusivos, enlaces de acceso reutilizados y consultas de datos ajenos.
- Antes de un lanzamiento público, revisar el cumplimiento normativo sobre servicios dirigidos a menores y las reglas de integración de YouTube; la verificación de un email no acredita por sí sola la patria potestad.
- Informar de modo transparente cuando un contenido procede de YouTube y puede mostrar elementos no controlados por la aplicación.

## 11. Requisitos generales de experiencia y calidad

- Interfaz inicialmente en castellano; preparar los textos para incorporar otros idiomas en el futuro.
- Diseño rápido, ordenado, agradable para niños y adultos, sin elementos que induzcan al consumo infinito de vídeos.
- Estados de espera, error, vacío y confirmación cuidados en todas las operaciones importantes.
- Accesibilidad básica: buen contraste, foco visible, tamaño suficiente de texto, etiquetas claras y controles utilizables sin ratón en TV.
- La actualización del catálogo desde el panel debe reflejarse en los televisores vinculados sin reinstalar la aplicación.
- Permitir que una sola familia utilice varios televisores y pueda reproducir contenido en ellos conforme a sus permisos.
- No depender de un servicio externo para el inicio de sesión ni para el almacenamiento del catálogo y los archivos propios.

## 12. Alcance de la primera entrega

Imprescindible: registro y verificación de email sin contraseña; acceso persistente; panel familiar; uno o más perfiles infantiles; carga de MP4/MP3; enlaces de YouTube; asignación de contenido a perfiles; navegación infantil con mando; reproducción; PIN parental; código de emparejamiento de televisor; gestión y revocación de dispositivos; aislamiento entre familias; aplicación de TV instalable y probada en LG/webOS; despliegue operativo en el VPS.

Fuera de la primera entrega: suscripciones y pagos; recomendaciones algorítmicas; buscador global de YouTube; chat o interacción entre niños; biblioteca pública compartida; tiendas oficiales de Smart TV; descarga de vídeos de YouTube; control parental sofisticado por horarios; aplicaciones móviles nativas.

Si una función depende de capacidades no disponibles en determinado modelo de TV, proporcionar un comportamiento alternativo claro y documentar la limitación, en lugar de bloquear el resto de la experiencia.


## 13. Flujos de aceptación

1. Un adulto introduce su email, recibe un enlace o código, lo valida y accede a su nuevo panel familiar sin configurar contraseña.
1. El adulto vuelve al panel desde el mismo móvil al día siguiente y sigue identificado sin solicitar otro email, salvo cierre/caducidad/revocación.
1. El adulto crea dos perfiles infantiles y añade un MP4, un MP3 y un enlace de YouTube; asigna diferentes contenidos a cada perfil.
1. Cada perfil muestra exclusivamente los elementos autorizados; cambiar de perfil actualiza correctamente el catálogo.
1. En la TV, el adulto vincula el dispositivo mediante código y, desde entonces, el niño entra a su biblioteca usando el mando.
1. El niño puede navegar con flechas y OK, reproducir un MP4 y un audio, regresar al catálogo y no puede entrar en la administración sin PIN.
1. Si un enlace de YouTube no permite reproducción, se informa sin romper la navegación; si funciona, se usa el reproductor oficial.
1. Desde el móvil, el adulto elimina un contenido y este deja de aparecer en el televisor al actualizarse el catálogo.
1. El adulto revoca una televisión y esta pierde el acceso a la familia.
1. Dos cuentas distintas no pueden consultar ni modificar los perfiles, vídeos privados o televisores de la otra.
1. Se genera e instala la aplicación en un LG/webOS real, se vincula a una familia y permite navegar y reproducir los formatos compatibles desde el mando.
1. Se entregan instrucciones de instalación y actualización para LG/webOS y queda documentado cómo ampliar el soporte a otras plataformas sin rehacer el producto.

## 14. Instrucciones de trabajo para Claude Code

- Construir la aplicación completa desde cero tomando este documento como fuente principal de requisitos funcionales.
- Utilizar Laravel y preparar el despliegue operativo en un VPS. No incorporar Supabase, Firebase ni servicios equivalentes de terceros para identidad, base de datos o almacenamiento de archivos de usuario.
- Resolver autónomamente las decisiones técnicas que este documento no especifica, justificando solo aquellas que tengan consecuencias importantes para el producto.
- Priorizar una primera versión simple, usable y funcional frente a funciones accesorias.
- Implementar y verificar los flujos de aceptación, con especial atención a seguridad, aislamiento entre familias, navegación con mando y reproducción multimedia.
- Entregar instrucciones claras para instalar, configurar, desplegar, administrar y actualizar el producto en el VPS, y para instalar y actualizar la aplicación en LG/webOS.
- Mantener una lista explícita de limitaciones conocidas, especialmente en integración de YouTube y compatibilidad con navegadores de Smart TV.
