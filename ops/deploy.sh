#!/usr/bin/env bash
# Despliega la versión actual de la rama main en el VPS.
# Uso (en el servidor): bash ops/deploy.sh
set -euo pipefail
cd "$(dirname "$0")/.."

COMPOSE="docker compose -f compose.production.yml"
PORT="${KIDSTV_PORT:-8086}"

[ -f .env.production ] || { echo "Falta .env.production (ver ops/env.production.example)"; exit 1; }

if [ "${1:-}" != "--no-pull" ]; then
    git pull --ff-only
fi

export KIDSTV_RELEASE="$(git rev-parse --short HEAD)"
echo "==> Construyendo versión ${KIDSTV_RELEASE}"
$COMPOSE build

echo "==> Migraciones"
$COMPOSE run --rm --no-deps app php artisan migrate --force

echo "==> Arrancando contenedores"
$COMPOSE up -d --remove-orphans
$COMPOSE exec -T worker php artisan queue:restart >/dev/null 2>&1 || true

echo "==> Comprobando salud"
for i in $(seq 1 30); do
    if curl -fsS "http://127.0.0.1:${PORT}/up" >/dev/null 2>&1; then
        echo "OK: KidsTV ${KIDSTV_RELEASE} responde en 127.0.0.1:${PORT}"
        break
    fi
    [ "$i" = 30 ] && { echo "ERROR: la aplicación no responde"; $COMPOSE logs --tail=50; exit 1; }
    sleep 2
done

# Conserva solo las dos últimas imágenes de cada servicio.
for repo in kidstv-app kidstv-web; do
    docker images "$repo" --format '{{.CreatedAt}}\t{{.Repository}}:{{.Tag}}' | sort -r | tail -n +3 | cut -f2 \
        | grep -v ":${KIDSTV_RELEASE}$" | xargs -r docker rmi >/dev/null 2>&1 || true
done
