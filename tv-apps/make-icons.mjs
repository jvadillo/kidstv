#!/usr/bin/env node
/* Dibuja los iconos PNG de la app (sin dependencias). Uso: node tv-apps/make-icons.mjs */
import { writeFileSync } from 'node:fs';
import { deflateSync } from 'node:zlib';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

function crc32(buf) {
    let c, crc = 0xffffffff;
    for (let n = 0; n < buf.length; n++) {
        c = (crc ^ buf[n]) & 0xff;
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

function png(size, shade) {
    const S = 4; // supermuestreo para suavizar bordes
    const raw = Buffer.alloc((size * 4 + 1) * size);
    for (let y = 0; y < size; y++) {
        raw[y * (size * 4 + 1)] = 0;
        for (let x = 0; x < size; x++) {
            let r = 0, g = 0, b = 0, a = 0;
            for (let sy = 0; sy < S; sy++) for (let sx = 0; sx < S; sx++) {
                const [pr, pg, pb, pa] = shade((x + (sx + 0.5) / S) / size * 64, (y + (sy + 0.5) / S) / size * 64);
                r += pr * pa; g += pg * pa; b += pb * pa; a += pa;
            }
            const o = y * (size * 4 + 1) + 1 + x * 4;
            raw[o] = a ? Math.round(r / a) : 0; raw[o + 1] = a ? Math.round(g / a) : 0; raw[o + 2] = a ? Math.round(b / a) : 0;
            raw[o + 3] = Math.round(a / (S * S) * 255);
        }
    }
    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(size, 0); ihdr.writeUInt32BE(size, 4);
    ihdr[8] = 8; ihdr[9] = 6; ihdr[10] = 0; ihdr[11] = 0; ihdr[12] = 0;
    return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ihdr), chunk('IDAT', deflateSync(raw)), chunk('IEND', Buffer.alloc(0))]);
}

const inRoundRect = (x, y, x0, y0, w, h, r) => {
    const cx = Math.max(x0 + r, Math.min(x, x0 + w - r));
    const cy = Math.max(y0 + r, Math.min(y, y0 + h - r));
    return x >= x0 && x <= x0 + w && y >= y0 && y <= y0 + h && (x - cx) ** 2 + (y - cy) ** 2 <= r * r;
};
const inTriangle = (x, y) => x >= 28 && x <= 39 && Math.abs(y - 30) <= (39 - x) * (6.5 / 11);

// Mismo diseño que public/img/icon.svg (viewBox 64x64).
const shade = (x, y) => {
    if (!inRoundRect(x, y, 0, 0, 64, 64, 16)) return [0, 0, 0, 0];
    if (inTriangle(x, y) && inRoundRect(x, y, 10, 15, 44, 30, 7)) return [0x2b, 0x25, 0x60, 1];
    if (inRoundRect(x, y, 10, 15, 44, 30, 7)) return [0xff, 0xc8, 0x3d, 1];
    if ((x >= 22 && x <= 42 && y >= 50 && y <= 54) || (x >= 30 && x <= 34 && y >= 45 && y <= 52)) return [0xff, 0xc8, 0x3d, 1];
    return [0x2b, 0x25, 0x60, 1];
};

writeFileSync(join(here, 'webos', 'icon.png'), png(80, shade));
writeFileSync(join(here, 'webos', 'largeIcon.png'), png(130, shade));
console.log('Iconos generados en tv-apps/webos/');
