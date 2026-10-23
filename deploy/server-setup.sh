#!/usr/bin/env bash
# =============================================================================
# Mega Mall Tanzania: one-time setup of a fresh Ubuntu 24.04 server.
#
#   1. Point the domains to the server's IP first (DNS "A" records):
#        megamall.co.tz, www.megamall.co.tz, api.megamall.co.tz
#   2. Copy this file to the server and run as root:
#        sudo bash server-setup.sh
#
# Installs: nginx, PHP 8.4 (FPM), MySQL 8, Node.js 22, Composer, Certbot (free SSL),
# firewall, automatic security updates. Creates the database and the "deploy" user.
# =============================================================================
set -euo pipefail

SHOP_DOMAIN="megamall.co.tz"
API_DOMAIN="api.megamall.co.tz"
ADMIN_EMAIL="it@megamall.co.tz"        # for SSL certificate expiry notices
DB_NAME="megamall"
DB_USER="megamall_app"
APP_USER="deploy"

[ "$(id -u)" -eq 0 ] || { echo "Run as root: sudo bash server-setup.sh"; exit 1; }
export DEBIAN_FRONTEND=noninteractive

echo "==> System updates and basics"
apt-get update -y && apt-get upgrade -y
apt-get install -y software-properties-common curl git unzip ufw fail2ban unattended-upgrades acl
timedatectl set-timezone Africa/Dar_es_Salaam

echo "==> Firewall: SSH and web only"
ufw allow OpenSSH
ufw allow 'Nginx Full' || true
ufw --force enable

echo "==> PHP 8.4"
add-apt-repository -y ppa:ondrej/php
apt-get update -y
apt-get install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
    php8.4-zip php8.4-intl php8.4-bcmath php8.4-gd php8.4-opcache
cat > /etc/php/8.4/fpm/conf.d/99-megamall.ini <<'INI'
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 256M
max_execution_time = 120
expose_php = Off
opcache.enable = 1
opcache.validate_timestamps = 1
INI
systemctl restart php8.4-fpm

echo "==> Composer"
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

echo "==> Node.js 22 LTS"
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y nodejs

echo "==> nginx and Certbot"
apt-get install -y nginx certbot python3-certbot-nginx
rm -f /etc/nginx/sites-enabled/default

echo "==> MySQL (only reachable from this server)"
apt-get install -y mysql-server
DB_PASSWORD="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-28)"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "==> App user and folders"
id "$APP_USER" >/dev/null 2>&1 || adduser --disabled-password --gecos "" "$APP_USER"
usermod -aG www-data "$APP_USER"
mkdir -p /var/www /var/backups/megamall
chown "$APP_USER":www-data /var/www /var/backups/megamall
# Let the deploy user reload services after an update, without a password.
cat > /etc/sudoers.d/megamall <<SUDO
$APP_USER ALL=(root) NOPASSWD: /usr/bin/systemctl restart megamall-web, /usr/bin/systemctl reload php8.4-fpm, /usr/bin/systemctl reload nginx
SUDO
chmod 440 /etc/sudoers.d/megamall

echo "==> Automatic security updates"
dpkg-reconfigure -f noninteractive unattended-upgrades

cat <<DONE

=============================================================================
 Server ready. SAVE THIS DATABASE PASSWORD (it is not shown again):

   DB_USERNAME=${DB_USER}
   DB_PASSWORD=${DB_PASSWORD}

 Free SSL (the padlock), once the nginx sites from step 4 are enabled:
   certbot --nginx -d ${SHOP_DOMAIN} -d www.${SHOP_DOMAIN} -d ${API_DOMAIN} -m ${ADMIN_EMAIL} --agree-tos --redirect

 Next: follow LAUNCH-CHECKLIST.md from "3. Put the code on the server".
=============================================================================
DONE
