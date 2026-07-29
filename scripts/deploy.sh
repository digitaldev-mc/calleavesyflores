#!/usr/bin/env bash
# Se ejecuta EN EL VPS (manual o vía GitHub Actions).
# git pull en la copia de trabajo → rsync al web root (sin tocar config.php).
set -euo pipefail

WORK_PATH="${VPS_WORK_PATH:-$HOME/avesyflores-src}"
WEB_ROOT="${WEB_ROOT:-$HOME/calleavesyflores.manizalescomparte.com}"
BRANCH="${BRANCH:-main}"

echo "==> Deploy aves-mc"
echo "    WORK_PATH: $WORK_PATH"
echo "    WEB_ROOT:  $WEB_ROOT"
echo "    BRANCH:    $BRANCH"

cd "$WORK_PATH"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull origin "$BRANCH"

mkdir -p "$WEB_ROOT"

rsync -av --delete \
  --exclude 'config.php' \
  --exclude '.git/' \
  --exclude 'node_modules/' \
  --exclude 'GUIA-DESPLIEGUE.html' \
  --exclude 'README.md' \
  --exclude 'COMO-FUNCIONA.md' \
  --exclude 'scripts/' \
  --exclude 'deploy/' \
  --exclude '.github/' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude '.gitignore' \
  "$WORK_PATH/" "$WEB_ROOT/"

if [[ ! -f "$WEB_ROOT/config.php" ]]; then
  echo "ERROR: Falta $WEB_ROOT/config.php (créalo en el servidor; no va en Git)." >&2
  exit 1
fi

chmod 644 "$WEB_ROOT/index.html" "$WEB_ROOT/api.php" "$WEB_ROOT/db.php" \
  "$WEB_ROOT/.htaccess" "$WEB_ROOT/logo.png" 2>/dev/null || true
chmod 600 "$WEB_ROOT/config.php"

if [[ -f "$WEB_ROOT/setup.php" ]]; then
  echo "AVISO: setup.php no debe existir en producción. Elimínalo del web root."
fi

echo "==> Despliegue completado $(date -Is)"
