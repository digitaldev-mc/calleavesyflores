#!/usr/bin/env bash
# Configuración inicial del VPS DreamHost (ejecutar UNA vez conectado por SSH).
# Uso: bash setup-vps.sh
#
# Antes de ejecutar, ajusta estas variables:
DOMAIN="tudominio.com"          # dominio del sitio
DEPLOY_USER="avesmc"            # usuario Linux dedicado al proyecto
WEB_DIR="/home/${DEPLOY_USER}/${DOMAIN}"
REPO_DIR="/home/${DEPLOY_USER}/repos/aves-mc.git"
BRANCH="main"

set -euo pipefail

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Ejecuta este script como root o con sudo."
  exit 1
fi

echo "==> Creando usuario $DEPLOY_USER"
if ! id "$DEPLOY_USER" &>/dev/null; then
  adduser --disabled-password --gecos "" "$DEPLOY_USER"
  usermod -aG www-data "$DEPLOY_USER" 2>/dev/null || true
fi

echo "==> Directorios web y repositorio"
sudo -u "$DEPLOY_USER" mkdir -p "$WEB_DIR" "$(dirname "$REPO_DIR")"

if [[ ! -d "$REPO_DIR" ]]; then
  sudo -u "$DEPLOY_USER" git init --bare "$REPO_DIR"
fi

HOOK="$REPO_DIR/hooks/post-receive"
cat > "$HOOK" <<'HOOKEOF'
#!/usr/bin/env bash
set -euo pipefail
WEB_DIR="__WEB_DIR__"
BRANCH="__BRANCH__"
GIT_DIR="$(pwd)"

while read -r oldrev newrev ref; do
  if [[ "$ref" != "refs/heads/$BRANCH" ]]; then
    echo "Ignorando ref $ref (solo despliega $BRANCH)"
    continue
  fi
  echo "==> Recibiendo push en $BRANCH ($newrev)"
  mkdir -p "$WEB_DIR"
  git --work-tree="$WEB_DIR" --git-dir="$GIT_DIR" checkout -f "$BRANCH"
  if [[ -x "$WEB_DIR/deploy/deploy.sh" ]]; then
    WEB_DIR="$WEB_DIR" BRANCH="$BRANCH" bash "$WEB_DIR/deploy/deploy.sh"
  fi
done
HOOKEOF
sed -i "s|__WEB_DIR__|$WEB_DIR|g; s|__BRANCH__|$BRANCH|g" "$HOOK"
chmod +x "$HOOK"

echo "==> Instalando dependencias del sistema (PHP + MySQL cliente)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq git apache2 libapache2-mod-php php-mysql php-mbstring mysql-server certbot python3-certbot-apache

echo "==> VirtualHost Apache (HTTP; luego activa SSL con certbot)"
cat > "/etc/apache2/sites-available/${DOMAIN}.conf" <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot ${WEB_DIR}
    <Directory ${WEB_DIR}>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/${DOMAIN}-error.log
    CustomLog \${APACHE_LOG_DIR}/${DOMAIN}-access.log combined
</VirtualHost>
EOF

a2ensite "${DOMAIN}.conf" 2>/dev/null || true
a2enmod rewrite headers ssl 2>/dev/null || true
systemctl reload apache2

echo ""
echo "============================================================"
echo " VPS listo para recibir el primer push."
echo ""
echo " 1) En el VPS, crea la base de datos MySQL:"
echo "      sudo mysql"
echo "      CREATE DATABASE aves_y_flores CHARACTER SET utf8mb4;"
echo "      CREATE USER 'aves_user'@'localhost' IDENTIFIED BY 'contraseña_segura';"
echo "      GRANT ALL ON aves_y_flores.* TO 'aves_user'@'localhost';"
echo "      FLUSH PRIVILEGES;"
echo ""
echo " 2) En tu PC (dentro del proyecto):"
echo "      git init && git add . && git commit -m 'Initial commit'"
echo "      git remote add production ${DEPLOY_USER}@${DOMAIN}:${REPO_DIR}"
echo "      git push -u production ${BRANCH}"
echo ""
echo " 3) En el servidor, edita config.php con DB_HOST=localhost"
echo " 4) Abre https://${DOMAIN}/setup.php una vez, luego bórralo"
echo " 5) SSL: sudo certbot --apache -d ${DOMAIN}"
echo "============================================================"
