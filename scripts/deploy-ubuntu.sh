#!/usr/bin/env bash
set -Eeuo pipefail
if [[ "$(id -u)" -eq 0 ]]; then echo 'Run as a sudo-enabled user, not with sudo.' >&2;exit 1;fi
domain="${1:-}";[[ "$domain" =~ ^([A-Za-z0-9-]+\.)+[A-Za-z]{2,}$ ]] || { echo 'Usage: scripts/deploy-ubuntu.sh real-domain.example' >&2;exit 2; }
app_dir=/var/www/campusflow;db_name="${DB_NAME:-CAT2}";db_user="${DB_USER:-campusflow}";repo_url="${REPO_URL:-https://github.com/cypskip-create/Server-Admin-project.git}"
[[ "$db_name" =~ ^[A-Za-z0-9_]+$ && "$db_user" =~ ^[A-Za-z0-9_]+$ ]] || { echo 'Invalid database identifier.' >&2;exit 1; }
read -rp 'ACME contact email: ' contact_email
[[ "$contact_email" =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]] || { echo 'Invalid contact email.' >&2;exit 1; }
read -rsp 'Choose an application DB password (16+ letters/digits and _!@#%+=:,.?- only): ' db_secret;echo
[[ "$db_secret" =~ ^[A-Za-z0-9_\!@#%+=:,.\?-]{16,128}$ ]] || { echo 'Password did not meet the allowed format/length.' >&2;exit 1; }
sudo apt-get update
sudo apt-get install -y apache2 mariadb-server php php-cli libapache2-mod-php php-mysql php-mbstring git curl
sudo systemctl enable --now apache2 mariadb
if [[ ! -d "$app_dir/.git" ]]; then sudo install -d -o "$(id -un)" -g www-data -m 2750 "$app_dir";git clone --branch main "$repo_url" "$app_dir";fi
if [[ -e "$app_dir/.env" ]]; then echo "Refusing to overwrite $app_dir/.env; preserve and update it manually." >&2;exit 1;fi
sudo mariadb <<SQL
CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$db_user'@'127.0.0.1' IDENTIFIED BY '$db_secret';
ALTER USER '$db_user'@'127.0.0.1' IDENTIFIED BY '$db_secret';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM '$db_user'@'127.0.0.1';
GRANT SELECT,INSERT,UPDATE,DELETE ON $db_name.* TO '$db_user'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
sudo env DB_HOST=localhost DB_NAME="$db_name" DB_USER=root DB_PASS='' php "$app_dir/scripts/migrate.php"
sudo tee "$app_dir/.env" >/dev/null <<EOF
APP_ENV=production
APP_URL=https://$domain
APP_NAME=CampusFlow
MAIN_HOST=$domain
APP_HOST=app.$domain
ADMIN_HOST=admin.$domain
DB_HOST=127.0.0.1
DB_PORT=3306
DB_TIMEZONE=+03:00
DB_NAME=$db_name
DB_USER=$db_user
DB_PASS="$db_secret"
SESSION_SECURE=false
EOF
unset db_secret
sudo chown root:www-data "$app_dir/.env";sudo chmod 640 "$app_dir/.env"
sudo chown -R root:www-data "$app_dir";sudo find "$app_dir" -type d -exec chmod 750 {} +;sudo find "$app_dir" -type f -exec chmod 640 {} +
sudo tee /etc/apache2/sites-available/campusflow.conf >/dev/null <<EOF
<VirtualHost *:80>
    ServerName $domain
    ServerAlias www.$domain app.$domain admin.$domain
    DocumentRoot $app_dir/public
    <Directory $app_dir/public>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/campusflow-error.log
    CustomLog \${APACHE_LOG_DIR}/campusflow-access.log combined
</VirtualHost>
EOF
sudo a2enmod headers ssl rewrite >/dev/null;sudo a2ensite campusflow >/dev/null;sudo a2dissite 000-default >/dev/null || true
sudo apachectl configtest;sudo systemctl reload apache2
sudo mariadb "$db_name" -e 'SHOW TABLES; SELECT migration,applied_at FROM schema_migrations ORDER BY migration;'
curl --fail --silent --show-error -I -H "Host: $domain" http://127.0.0.1/ >/dev/null
curl --fail --silent --show-error -I -H "Host: app.$domain" http://127.0.0.1/login.php >/dev/null
echo "HTTP VPS deployment prepared for $domain, app.$domain and admin.$domain. Verify public DNS/80-443 reachability, then run scripts/issue-zerossl.sh $domain $contact_email. HTTPS is not enabled or verified yet."
