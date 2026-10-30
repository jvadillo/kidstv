#!/usr/bin/env bash
# Copia de seguridad manual: base de datos (siempre) y, con --media, también los archivos.
# Las copias de la BD se generan además cada noche dentro del volumen (nokoplay:backup).
set -euo pipefail
cd "$(dirname "$0")/.."
DEST="${BACKUP_DIR:-$HOME/backups/nokoplay}"
STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$DEST"

docker compose -f compose.production.yml exec -T app php artisan nokoplay:backup
docker compose -f compose.production.yml exec -T app sh -c 'ls -1t storage/backups/*.sqlite | head -1 | xargs cat' > "$DEST/nokoplay-$STAMP.sqlite"
echo "Base de datos copiada en $DEST/nokoplay-$STAMP.sqlite"

if [ "${1:-}" = "--media" ]; then
    docker compose -f compose.production.yml exec -T app tar czf - -C storage/app private > "$DEST/nokoplay-media-$STAMP.tar.gz"
    echo "Archivos copiados en $DEST/nokoplay-media-$STAMP.tar.gz"
fi
