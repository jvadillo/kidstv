#!/usr/bin/env bash
# Instala/actualiza el sitio de NokoPlay en el Caddy compartido y lo recarga sin cortar las demás webs.
set -euo pipefail
cd "$(dirname "$0")/.."

GATEWAY_DIR="${GATEWAY_DIR:-$HOME/projects/gateway}"
CADDY_CONTAINER="${CADDY_CONTAINER:-web-gateway-caddy-1}"

cp ops/caddy/nokoplay.caddy "$GATEWAY_DIR/sites/nokoplay.caddy"
docker exec "$CADDY_CONTAINER" caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
docker exec "$CADDY_CONTAINER" caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile
echo "Sitio nokoplay instalado en $GATEWAY_DIR/sites/nokoplay.caddy y Caddy recargado."
