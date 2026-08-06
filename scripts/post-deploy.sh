#!/usr/bin/env bash
# Post-deploy en el VPS: despliega, aplica secrets opcionales y verifica.
set -euo pipefail

WORK_PATH="${VPS_WORK_PATH:-$HOME/avesyflores-src}"
WEB_ROOT="${WEB_ROOT:-$HOME/calleavesyflores.manizalescomparte.com}"

bash "$WORK_PATH/scripts/deploy.sh"

if [[ -n "${RESEND_API_KEY:-}" ]] || [[ -n "${CORREO_NOMBRE:-}" ]]; then
  export WEB_ROOT
  php "$WORK_PATH/scripts/patch-config.php"
fi

echo "==> Verificación post-deploy"
test -f "$WEB_ROOT/resend.php" || { echo "ERROR: falta resend.php en web root"; exit 1; }
test -f "$WEB_ROOT/mail.php" || { echo "ERROR: falta mail.php en web root"; exit 1; }

if [[ -n "${RESEND_API_KEY:-}" ]] && [[ "$RESEND_API_KEY" == re_* ]]; then
  php "$WORK_PATH/scripts/test-mail.php" >/dev/null && echo "OK: prueba Resend enviada a CORREO_DESTINO"
fi

echo "==> Producción lista $(date -Is)"
