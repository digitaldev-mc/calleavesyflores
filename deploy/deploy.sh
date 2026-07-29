#!/usr/bin/env bash
# Ejecutado en el servidor tras cada git pull / push de despliegue.
set -euo pipefail

WEB_DIR="${WEB_DIR:-$HOME/aves-mc}"
BRANCH="${BRANCH:-main}"

cd "$WEB_DIR"

echo "==> Despliegue en $WEB_DIR (rama $BRANCH)"

if [[ ! -f config.php ]]; then
  if [[ -f config.php.example ]]; then
    cp config.php.example config.php
    echo "AVISO: Se creó config.php desde config.php.example."
    echo "       Edítalo con las credenciales reales antes de usar el sitio."
  else
    echo "ERROR: Falta config.php. Créalo con los datos de MySQL." >&2
    exit 1
  fi
fi

# Permisos mínimos para PHP/Apache
chmod 644 index.html api.php db.php .htaccess logo.png 2>/dev/null || true
chmod 600 config.php 2>/dev/null || true

# setup.php no debe quedar en producción
if [[ -f setup.php ]]; then
  echo "AVISO: setup.php sigue presente. Bórralo tras la instalación inicial."
fi

echo "==> Despliegue completado $(date -Is)"
