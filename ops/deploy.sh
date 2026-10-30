#!/usr/bin/env bash
# Despliega la versión actual de la rama main en el VPS.
# Uso (en el servidor): bash ops/deploy.sh
set -euo pipefail
cd "$(dirname "$0")/.."

COMPOSE="docker compose -f compose.production.yml"
PORT="${NOKOPLAY_PORT:-8086}"

[ -f .env.production ] || { echo "Falta .env.production (ver ops/env.production.example)"; exit 1; }

# Un cambio de nombre de Compose crea otro volumen. No arrancar una base vacía
# sobre una instalación existente: primero copiar los datos y detener el proyecto anterior.
for container in $(docker ps -q --filter "publish=${PORT}"); do
    project=$(docker inspect --format '{{index .Config.Labels "com.docker.compose.project"}}' "$container")
    if [ "$project" != "nokoplay" ]; then
        echo "El puerto ${PORT} pertenece a otro proyecto. Completa docs/migracion-nokoplay.md antes de desplegar."
        exit 1
    fi
done

if [ "${1:-}" != "--no-pull" ]; then
    git pull --ff-only
fi

export NOKOPLAY_RELEASE="$(git rev-parse --short HEAD)"
echo "==> Construyendo versión ${NOKOPLAY_RELEASE}"
$COMPOSE build

echo "==> Migraciones"
$COMPOSE run --rm --no-deps app php artisan migrate --force

echo "==> Arrancando contenedores"
# --force-recreate: cada build genera una imagen nueva aunque el commit no cambie, y
# Compose no siempre recrea los contenedores; así todos usan la imagen y el .env actuales.
$COMPOSE up -d --remove-orphans --force-recreate

echo "==> Comprobando salud"
for i in $(seq 1 30); do
    if curl -fsS "http://127.0.0.1:${PORT}/up" >/dev/null 2>&1; then
        echo "OK: NokoPlay ${NOKOPLAY_RELEASE} responde en 127.0.0.1:${PORT}"
        break
    fi
    [ "$i" = 30 ] && { echo "ERROR: la aplicación no responde"; $COMPOSE logs --tail=50; exit 1; }
    sleep 2
done

# Conserva solo las dos últimas imágenes de cada servicio.
for repo in nokoplay-app nokoplay-web; do
    docker images "$repo" --format '{{.CreatedAt}}\t{{.Repository}}:{{.Tag}}' | sort -r | tail -n +3 | cut -f2 \
        | grep -v ":${NOKOPLAY_RELEASE}$" | xargs -r docker rmi >/dev/null 2>&1 || true
done
