#!/usr/bin/env bash
# Despliegue manual en el servidor (alternativa al hook post-receive).
# Uso en el VPS: cd ~/aves-mc && bash deploy/pull-deploy.sh
set -euo pipefail

WEB_DIR="${WEB_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
BRANCH="${BRANCH:-main}"
REMOTE="${REMOTE:-origin}"

cd "$WEB_DIR"

if [[ ! -d .git ]]; then
  echo "Este directorio no es un clon Git. Usa git push al bare repo en su lugar." >&2
  exit 1
fi

git fetch "$REMOTE"
git checkout "$BRANCH"
git pull "$REMOTE" "$BRANCH"

bash "$WEB_DIR/deploy/deploy.sh"
