# Primeros pasos en web, móvil y TV

La web mantiene `/empezar`. Al iniciar una tele sin vincular aparecen «Continuar con el móvil», «Continuar en esta tele» y «Ya tengo cuenta».

- **Móvil:** QR o `/empezar/tv` y código de seis caracteres. Preparar nombre, edad y series, verificar correo, confirmar la familia y conectar. El código se conserva en la sesión; al abrir el flujo se amplía a una hora. Si caduca, se introduce el nuevo sin perder el borrador.
- **TV:** nombre, edad y listas públicas con el mando. Reproducción inmediata sin cuenta. «Seguir en el móvil» transfiere el borrador. La configuración provisional se conserva 30 días desde el último uso; el proceso horario de limpieza elimina las familias provisionales inactivas sin adultos.
- **Guardar y proteger:** la primera entrada a la Zona de adultos sin PIN muestra QR/dirección/código. El adulto verifica el correo desde el móvil, elige añadir lo preparado o usar sus perfiles actuales y configura el PIN si falta. La TV vuelve a perfiles y sigue exigiendo el PIN para acceder a adultos. No se transfieren a otra familia las teles que ya estaban guardadas.

Los códigos no dan una sesión adulta. La creación provisional solo admite listas públicas disponibles, exige el secreto de la vinculación y está limitada por frecuencia. Los tokens de reproducción no permiten administrar cuentas. El guardado es transaccional y solo se completa una vez por código. Se requiere confirmación reciente del correo para guardar y establecer el PIN.

## Validación y despliegue

`tests/Feature/TvOnboardingTest.php` cubre reproducción provisional, aislamiento, secretos y listas válidas, guardado, PIN, familia existente, caducidad y limpieza. La suite completa debe ejecutarse con SQLite de pruebas, nunca con la base de producción.

La interfaz se comprueba con teclado (flechas, OK, Atrás y escritura), transferencia del borrador, foco visible y acciones dentro de 1280×720. La comprobación en navegador no sustituye una prueba en un LG físico.

El paquete LG 1.5.0 incluye este flujo. Las teles que ya tienen una app instalada necesitan reinstalar el nuevo `public/downloads/kidstv-webos.ipk`; el despliegue del servidor no actualiza su JavaScript local. Las vinculaciones existentes se conservan al actualizar sin borrar datos de la app.
