#!/usr/bin/env bash
set -Eeuo pipefail

repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
if [[ "$(id -u)" -eq 0 ]]; then echo 'Run this script as your normal VM user; it invokes sudo where needed.' >&2; exit 1; fi
db_name="${DB_NAME:-CAT2}";db_user="${DB_USER:-campusflow}";site_host="${SITE_HOST:-campusflow.local}"
[[ "$db_name" =~ ^[A-Za-z0-9_]+$ && "$db_user" =~ ^[A-Za-z0-9_]+$ && "$site_host" =~ ^[A-Za-z0-9.-]+$ ]] || { echo 'Invalid database or host name.' >&2;exit 1; }
if [[ -e "$repo_dir/.env" ]]; then echo "Refusing to overwrite $repo_dir/.env; back it up or edit it manually." >&2;exit 1;fi
read -rsp 'Choose an application DB password (16+ letters/digits and _!@#%+=:,.?- only): ' db_secret;echo
[[ "$db_secret" =~ ^[A-Za-z0-9_\!@#%+=:,.\?-]{16,128}$ ]] || { echo 'Password did not meet the allowed format/length.' >&2;exit 1; }
sudo apt-get update
sudo apt-get install -y apache2 mariadb-server php php-cli libapache2-mod-php php-mysql php-mbstring git
sudo systemctl enable --now apache2 mariadb
sudo mariadb <<SQL
CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$db_user'@'127.0.0.1' IDENTIFIED BY '$db_secret';
ALTER USER '$db_user'@'127.0.0.1' IDENTIFIED BY '$db_secret';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM '$db_user'@'127.0.0.1';
GRANT SELECT,INSERT,UPDATE,DELETE ON $db_name.* TO '$db_user'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
sudo env DB_HOST=localhost DB_NAME="$db_name" DB_USER=root DB_PASS='' php "$repo_dir/scripts/migrate.php"
sudo tee "$repo_dir/.env" >/dev/null <<EOF
APP_ENV=development
APP_URL=http://$site_host
APP_NAME=CampusFlow
MAIN_HOST=$site_host
APP_HOST=app.$site_host
ADMIN_HOST=admin.$site_host
DB_HOST=127.0.0.1
DB_PORT=3306
DB_TIMEZONE=+03:00
DB_NAME=$db_name
DB_USER=$db_user
DB_PASS="$db_secret"
SESSION_SECURE=false
EOF
unset db_secret
sudo chown root:www-data "$repo_dir/.env";sudo chmod 640 "$repo_dir/.env"
sudo tee /etc/apache2/sites-available/campusflow.conf >/dev/null <<EOF
<VirtualHost *:80>
    ServerName $site_host
    ServerAlias app.$site_host admin.$site_host
    DocumentRoot $repo_dir/public
    <Directory $repo_dir/public>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/campusflow-error.log
    CustomLog \${APACHE_LOG_DIR}/campusflow-access.log combined
</VirtualHost>
EOF
sudo a2enmod headers rewrite >/dev/null
sudo a2ensite campusflow >/dev/null
sudo systemctl reload apache2
printf '127.0.0.1 %s app.%s admin.%s\n' "$site_host" "$site_host" "$site_host" | sudo tee -a /etc/hosts >/dev/null
sudo apachectl configtest
sudo mariadb "$db_name" -e 'SHOW TABLES; SELECT migration,applied_at FROM schema_migrations ORDER BY migration;'
curl --fail --silent --show-error -H "Host: $site_host" http://127.0.0.1/ >/dev/null
curl --fail --silent --show-error -I -H "Host: app.$site_host" http://127.0.0.1/login.php >/dev/null
echo "Kali setup complete. Open http://$site_host/signup.php; check services with systemctl and logs in /var/log/apache2/. No VM/browser registration test was fabricated."
