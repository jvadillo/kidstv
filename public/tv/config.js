/*
 * Configuración de la app de TV. Esta versión es la que se sirve desde el propio
 * servidor (/tv/). Los paquetes para televisores (webOS, etc.) generan su propio
 * config.js con la dirección absoluta del servidor (ver tv-apps/).
 */
window.NOKOPLAY_CONFIG = {
    apiBase: '',
    platform: 'web',
    avatarBase: '/avatars/',
    version: '2.0.0'
};
