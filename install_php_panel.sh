#!/usr/bin/env bash
#
# FireLam PHP Admin/Chat Panel — VPS installer
# Target: Ubuntu/Debian with nginx + PHP-FPM, model served locally via Ollama.
#
# Usage (as root or with sudo):
#   sudo bash install/install_php_panel.sh yourdomain.com
#
set -euo pipefail

DOMAIN="${1:-}"
APP_DIR="/var/www/firelam-panel"
REPO_SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"   # .../php_panel

if [[ $EUID -ne 0 ]]; then
    echo "Run this with sudo/root: sudo bash install/install_php_panel.sh <domain>" >&2
    exit 1
fi

if [[ -z "$DOMAIN" ]]; then
    read -rp "Domain or public IP this panel will be served on: " DOMAIN
fi

echo "==> Installing packages (nginx, PHP-FPM, sqlite, curl, mbstring)"
apt-get update -qq
apt-get install -y -qq nginx php-fpm php-sqlite3 php-curl php-mbstring curl

PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_SOCK="/run/php/php${PHP_VER}-fpm.sock"

echo "==> Copying application to $APP_DIR"
mkdir -p "$APP_DIR"
# NOTE: 'install/' is deliberately copied too (not excluded) — seed_admin.php
# is invoked from $APP_DIR/install/ a few lines below, on every run.
rsync -a --delete \
  --exclude 'storage' \
  --exclude '.env' \
  "$REPO_SRC"/ "$APP_DIR"/

mkdir -p "$APP_DIR/storage"

echo "==> Writing .env"
if [[ ! -f "$APP_DIR/.env" ]]; then
    cat > "$APP_DIR/.env" <<EOF
FIRELAM_ENV=production
FIRELAM_DEBUG=0
FIRELAM_DB_PATH=$APP_DIR/storage/firelam.db
FIRELAM_OLLAMA_URL=http://127.0.0.1:11434
FIRELAM_MODEL=firelam-1.5
EOF
fi

echo "==> Setting ownership and permissions"
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 750 {} \;
find "$APP_DIR" -type f -exec chmod 640 {} \;
chmod 770 "$APP_DIR/storage"

echo "==> Initializing database + admin account"
read -rp "Admin username [admin]: " ADMIN_USER
ADMIN_USER="${ADMIN_USER:-admin}"
while true; do
    read -rsp "Admin password (min 8 chars): " ADMIN_PASS; echo
    [[ ${#ADMIN_PASS} -ge 8 ]] && break
    echo "Too short, try again."
done
sudo -u www-data php "$APP_DIR/install/seed_admin.php" "$ADMIN_USER" "$ADMIN_PASS"

echo "==> Configuring nginx"
sed \
  -e "s|SERVER_NAME|$DOMAIN|g" \
  -e "s|PHP_SOCK|$PHP_SOCK|g" \
  "$REPO_SRC/install/nginx_firelam_panel.conf.template" > /etc/nginx/sites-available/firelam-panel

ln -sf /etc/nginx/sites-available/firelam-panel /etc/nginx/sites-enabled/firelam-panel
nginx -t
systemctl reload nginx
systemctl restart "php${PHP_VER}-fpm"
systemctl enable nginx "php${PHP_VER}-fpm" > /dev/null

echo
echo "==================================================================="
echo " FireLam panel installed."
echo "   URL:      http://$DOMAIN/  (run certbot for HTTPS, see template)"
echo "   Files:    $APP_DIR"
echo "   Database: $APP_DIR/storage/firelam.db"
echo "   Login:    $ADMIN_USER"
echo
echo " Before this actually works end-to-end, make sure your FireLam"
echo " model is loaded and being served by Ollama on this same box:"
echo "   ollama create firelam-1.5 -f ollama/Modelfile"
echo "   ollama run firelam-1.5 --keepalive 60m &"
echo " (or point Settings > Ollama URL at wherever Ollama is running)."
echo "==================================================================="
