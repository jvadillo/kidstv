#!/usr/bin/env node
/* Genera los recursos de NokoPlay a partir de logos/*.png, sin dependencias.
 * Uso: node tv-apps/make-icons.mjs. Los originales no se modifican.
 */
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { deflateSync, inflateSync } from 'node:zlib';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const assets = join(root, 'public/tv/img');
mkdirSync(assets, { recursive: true });

function crc32(buf) {
    let crc = 0xffffffff;
    for (const byte of buf) {
        let c = (crc ^ byte) & 0xff;
        for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        crc = (crc >>> 8) ^ c;
    }
    return (crc ^ 0xffffffff) >>> 0;
}
function chunk(type, data) {
    const len = Buffer.alloc(4); len.writeUInt32BE(data.length);
    const td = Buffer.concat([Buffer.from(type), data]);
    const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(td));
    return Buffer.concat([len, td, crc]);
}
function png({ width, height, pixels }) {
    const raw = Buffer.alloc((width * 4 + 1) * height);
    for (let y = 0; y < height; y++) pixels.copy(raw, y * (width * 4 + 1) + 1, y * width * 4, (y + 1) * width * 4);
    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(width, 0); ihdr.writeUInt32BE(height, 4);
    ihdr[8] = 8; ihdr[9] = 6;
    return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ihdr), chunk('IDAT', deflateSync(raw, { level: 9 })), chunk('IEND', Buffer.alloc(0))]);
}
function readPng(name) {
    const input = readFileSync(join(root, 'logos', name));
    if (input.subarray(0, 8).toString('hex') !== '89504e470d0a1a0a' || input[24] !== 8 || input[25] !== 6 || input[28] !== 0) {
        throw new Error(`${name}: se requiere PNG RGBA de 8 bits sin entrelazado`);
    }
    const width = input.readUInt32BE(16), height = input.readUInt32BE(20), data = [];
    for (let offset = 8; offset < input.length;) {
        const length = input.readUInt32BE(offset);
        if (input.toString('ascii', offset + 4, offset + 8) === 'IDAT') data.push(input.subarray(offset + 8, offset + 8 + length));
        offset += length + 12;
    }
    const raw = inflateSync(Buffer.concat(data)), stride = width * 4, pixels = Buffer.alloc(stride * height);
    if (raw.length !== (stride + 1) * height) throw new Error(`PNG incompleto: ${name}`);
    for (let y = 0; y < height; y++) {
        const filter = raw[y * (stride + 1)];
        if (filter > 4) throw new Error(`Filtro PNG desconocido: ${filter}`);
        for (let x = 0; x < stride; x++) {
            const i = y * stride + x, a = x >= 4 ? pixels[i - 4] : 0, b = y ? pixels[i - stride] : 0, c = y && x >= 4 ? pixels[i - stride - 4] : 0;
            const p = a + b - c, pa = Math.abs(p - a), pb = Math.abs(p - b), pc = Math.abs(p - c);
            const predictor = [0, a, b, Math.floor((a + b) / 2), pa <= pb && pa <= pc ? a : pb <= pc ? b : c][filter];
            pixels[i] = raw[y * (stride + 1) + 1 + x] + predictor;
        }
    }
    return { width, height, pixels };
}
// Promedio de área con alfa premultiplicado: conserva bordes limpios en tamaños pequeños.
function resize(source, width, height) {
    const pixels = Buffer.alloc(width * height * 4);
    for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
        const x0 = x * source.width / width, x1 = (x + 1) * source.width / width;
        const y0 = y * source.height / height, y1 = (y + 1) * source.height / height;
        const sum = [0, 0, 0, 0];
        for (let sy = Math.floor(y0); sy < Math.ceil(y1); sy++) for (let sx = Math.floor(x0); sx < Math.ceil(x1); sx++) {
            const weight = (Math.min(x1, sx + 1) - Math.max(x0, sx)) * (Math.min(y1, sy + 1) - Math.max(y0, sy));
            const i = (Math.min(sy, source.height - 1) * source.width + Math.min(sx, source.width - 1)) * 4;
            const alpha = source.pixels[i + 3] * weight;
            for (let c = 0; c < 3; c++) sum[c] += source.pixels[i + c] * alpha;
            sum[3] += alpha;
        }
        const i = (y * width + x) * 4;
        for (let c = 0; c < 3; c++) pixels[i + c] = sum[3] ? Math.round(sum[c] / sum[3]) : 0;
        pixels[i + 3] = Math.round(sum[3] / ((x1 - x0) * (y1 - y0)));
    }
    return { width, height, pixels };
}
const symbol = readPng('isotipoPNG.png');
// padding: margen alrededor del isotipo (los iconos «maskable» necesitan una zona segura del 80 %).
function icon(size, white = false, padding = .07) {
    const inset = Math.max(1, Math.round(size * padding));
    const width = size - inset * 2, height = Math.round(width * symbol.height / symbol.width);
    const scaled = resize(symbol, width, height), pixels = Buffer.alloc(size * size * 4, white ? 255 : 0);
    const top = Math.floor((size - height) / 2);
    for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
        const i = (y * width + x) * 4, dest = ((top + y) * size + inset + x) * 4, a = scaled.pixels[i + 3] / 255;
        for (let c = 0; c < 3; c++) pixels[dest + c] = white ? Math.round(scaled.pixels[i + c] * a + 255 * (1 - a)) : scaled.pixels[i + c];
        pixels[dest + 3] = white ? 255 : scaled.pixels[i + 3];
    }
    return png({ width: size, height: size, pixels });
}
writeFileSync(join(assets, 'logo-horizontal.png'), png(resize(readPng('logoHorizontalPNG.png'), 780, 200)));
writeFileSync(join(assets, 'logo-vertical.png'), png(resize(readPng('logoPNG.png'), 568, 460)));
writeFileSync(join(assets, 'icon.png'), icon(256));
writeFileSync(join(assets, 'apple-touch-icon.png'), icon(180, true));
writeFileSync(join(root, 'tv-apps/webos/icon.png'), icon(80, true));
// Iconos de la aplicación instalable (PWA): normales y «maskable» para Android.
const pwa = join(root, 'public/img/pwa');
mkdirSync(pwa, { recursive: true });
for (const size of [192, 512]) {
    writeFileSync(join(pwa, `icon-${size}.png`), icon(size, true));
    writeFileSync(join(pwa, `maskable-${size}.png`), icon(size, true, .2));
}
writeFileSync(join(root, 'tv-apps/webos/largeIcon.png'), icon(130, true));
const sizes = [16, 32, 48, 256], images = sizes.map(size => icon(size));
const header = Buffer.alloc(6 + sizes.length * 16);
header.writeUInt16LE(1, 2); header.writeUInt16LE(sizes.length, 4);
let offset = header.length;
images.forEach((image, i) => {
    const entry = 6 + i * 16;
    header[entry] = header[entry + 1] = sizes[i] % 256;
    header.writeUInt16LE(1, entry + 4); header.writeUInt16LE(32, entry + 6);
    header.writeUInt32LE(image.length, entry + 8); header.writeUInt32LE(offset, entry + 12);
    offset += image.length;
});
writeFileSync(join(root, 'public/favicon.ico'), Buffer.concat([header, ...images]));
console.log('Logotipos, favicon e iconos de NokoPlay generados.');
