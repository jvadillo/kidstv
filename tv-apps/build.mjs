#!/usr/bin/env node
/*
 * Genera el paquete web de la app de TV para una plataforma a partir de public/tv
 * (la misma app que se sirve en /tv/). Sin dependencias.
 *
 *   node tv-apps/build.mjs webos --server https://nokoplay.jonvadillo.com
 *
 * Resultado: tv-apps/<plataforma>/dist, listo para empaquetar (ares-package en webOS,
 * Tizen Studio en Samsung, un WebView en Android TV).
 */
import { cpSync, mkdirSync, readFileSync, rmSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const args = process.argv.slice(2);
const platform = args[0] || 'webos';
const serverIndex = args.indexOf('--server');
const server = (serverIndex !== -1 ? args[serverIndex + 1] : process.env.NOKOPLAY_SERVER || 'https://nokoplay.jonvadillo.com').replace(/\/+$/, '');

const platformDir = join(root, 'tv-apps', platform);
if (!existsSync(platformDir)) {
    console.error(`Plataforma desconocida: ${platform} (no existe tv-apps/${platform})`);
    process.exit(1);
}

const dist = join(platformDir, 'dist');
rmSync(dist, { recursive: true, force: true });
mkdirSync(dist, { recursive: true });

// 1. App común (HTML, CSS, JS) y avatares.
cpSync(join(root, 'public', 'tv'), dist, { recursive: true });
cpSync(join(root, 'public', 'avatars'), join(dist, 'avatars'), { recursive: true });

// 2. Configuración específica del paquete.
const appinfoPath = join(platformDir, 'appinfo.json');
const version = existsSync(appinfoPath) ? JSON.parse(readFileSync(appinfoPath, 'utf8')).version : '1.0.0';
writeFileSync(join(dist, 'config.js'), `/* Generado por tv-apps/build.mjs (${platform}). */
window.NOKOPLAY_CONFIG = {
    apiBase: ${JSON.stringify(server)},
    platform: ${JSON.stringify(platform)},
    avatarBase: 'avatars/',
    version: ${JSON.stringify(version)}
};
`);

// 3. Ficheros propios de la plataforma (appinfo.json, iconos...).
for (const file of ['appinfo.json', 'icon.png', 'largeIcon.png', 'config.xml']) {
    if (existsSync(join(platformDir, file))) cpSync(join(platformDir, file), join(dist, file));
}

console.log(`App de TV para ${platform} ${version} generada en ${dist} (servidor: ${server})`);
