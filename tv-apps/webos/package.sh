#!/usr/bin/env bash
# Genera el paquete instalable para LG webOS (.ipk) y lo publica en public/downloads.
# Requisitos: Node.js 18+. Las herramientas de LG (@webos-tools/cli) se usan vía npx.
# Uso: bash tv-apps/webos/package.sh [https://servidor]
set -euo pipefail
cd "$(dirname "$0")/../.."
SERVER="${1:-https://nokoplay.jonvadillo.com}"

node tv-apps/build.mjs webos --server "$SERVER"
rm -rf tv-apps/webos/out && mkdir -p tv-apps/webos/out
npx -y -p @webos-tools/cli@3 ares-package tv-apps/webos/dist -o tv-apps/webos/out
mkdir -p public/downloads
VERSION=$(node -p "require('./tv-apps/webos/appinfo.json').version")
cp "tv-apps/webos/out/com.jonvadillo.nokoplay_${VERSION}_all.ipk" public/downloads/nokoplay-webos.ipk
echo "Paquete listo: tv-apps/webos/out/$(ls tv-apps/webos/out) (copiado en public/downloads/nokoplay-webos.ipk)"
