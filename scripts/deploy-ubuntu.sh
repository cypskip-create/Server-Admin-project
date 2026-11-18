#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

if [[ "$(id -u)" -ne 0 ]]; then
  echo 'Run this deployment through Azure Run Command as root, or with sudo.' >&2
  exit 1
fi

domain="${1:-}"
[[ "$domain" =~ ^([A-Za-z0-9-]+\.)+[A-Za-z]{2,}$ ]] || {
  echo 'Usage: sudo bash scripts/deploy-ubuntu.sh fuadsaary.me' >&2
  exit 2
}
domain="${domain,,}"
students_host="students.$domain"
admin_host="admin.$domain"
www_host="www.$domain"
app_dir=/var/www/campusflow
db_name="${DB_NAME:-campusflow_prod}"
db_user="${DB_USER:-campusflow_app}"
repo_url="${REPO_URL:-https://github.com/cypskip-create/Server-Admin-project.git}"

[[ "$db_name" =~ ^[A-Za-z0-9_]+$ && "$db_user" =~ ^[A-Za-z0-9_]+$ ]] || {
  echo 'Invalid database identifier.' >&2
  exit 1
}
[[ ! -e "$app_dir" ]] || { echo "Refusing to overwrite existing $app_dir." >&2; exit 1; }
[[ ! -e /etc/apache2/sites-available/campusflow.conf && ! -e /etc/apache2/sites-enabled/campusflow.conf ]] || {
  echo 'CampusFlow Apache site already exists; inspect and back it up before continuing.' >&2
  exit 1
}

# Preserve the current service configuration before package installation can
# create or change Apache/MariaDB files. No existing site is disabled.
backup_dir="/root/campusflow-backups/$(date -u +%Y%m%dT%H%M%SZ)"
install -d -o root -g root -m 700 "$backup_dir"
for config in apache2 mysql; do
  if [[ -d "/etc/$config" ]]; then
    tar -C /etc -cpf "$backup_dir/${config}-before.tar" "$config"
    chmod 600 "$backup_dir/${config}-before.tar"
  fi
done
echo "Configuration backup directory: $backup_dir"

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y apache2 mariadb-server php php-cli libapache2-mod-php php-mysql php-mbstring git curl openssl
systemctl enable --now apache2 mariadb

# Never reuse an existing database or database account. This app uses its own
# production database and runtime principal; other university schemas/users
# and grants are left untouched.
database_exists="$(mariadb --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$db_name'")"
account_exists="$(mariadb --batch --skip-column-names -e "SELECT COUNT(*) FROM mysql.user WHERE User='$db_user'")"
if [[ "$database_exists" != 0 || "$account_exists" != 0 ]]; then
  echo "Refusing to reuse existing database '$db_name' or user '$db_user'. Choose unused names after review." >&2
  exit 1
fi

install -d -o root -g www-data -m 2750 /var/www
git clone --depth 1 --branch main "$repo_url" "$app_dir"
db_secret="$(openssl rand -hex 32)"
mariadb <<SQL
CREATE DATABASE $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '$db_user'@'127.0.0.1' IDENTIFIED BY '$db_secret';
GRANT SELECT, INSERT, UPDATE, DELETE ON $db_name.* TO '$db_user'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

# The migration runner connects as the local MariaDB administrative account;
# it applies all ordered migrations, including login_attempts, before runtime
# access is limited to CRUD on this one schema.
DB_HOST=localhost DB_NAME="$db_name" DB_USER=root DB_PASS='' php "$app_dir/scripts/migrate.php"

cat > "$app_dir/.env" <<EOF
APP_ENV=production
APP_URL=http://$students_host
APP_NAME=CampusFlow
MAIN_HOST=$domain
WWW_HOST=$www_host
STUDENTS_HOST=$students_host
ADMIN_HOST=$admin_host
DB_HOST=127.0.0.1
DB_PORT=3306
DB_TIMEZONE=+03:00
DB_NAME=$db_name
DB_USER=$db_user
DB_PASS=$db_secret
SESSION_SECURE=false
EOF
unset db_secret
chown root:www-data "$app_dir/.env"
chmod 640 "$app_dir/.env"
chown -R root:www-data "$app_dir"
find "$app_dir" -type d -exec chmod 750 {} +
find "$app_dir" -type f -exec chmod 640 {} +

cat > /etc/apache2/sites-available/campusflow.conf <<EOF
# CampusFlow-owned virtual hosts. Other enabled sites are preserved.
<Directory $app_dir/public>
    Options -Indexes
    AllowOverride None
    Require all granted
</Directory>

<VirtualHost *:80>
    ServerName $domain
    DocumentRoot $app_dir/public
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    ErrorLog \${APACHE_LOG_DIR}/campusflow-main-error.log
    CustomLog \${APACHE_LOG_DIR}/campusflow-main-access.log combined
</VirtualHost>

<VirtualHost *:80>
    ServerName $www_host
    Redirect permanent / http://$domain/
    ErrorLog \${APACHE_LOG_DIR}/campusflow-www-error.log
    CustomLog \${APACHE_LOG_DIR}/campusflow-www-access.log combined
</VirtualHost>

<VirtualHost *:80>
    ServerName $students_host
    DocumentRoot $app_dir/public
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    ErrorLog \${APACHE_LOG_DIR}/campusflow-students-error.log
    CustomLog \${APACHE_LOG_DIR}/campusflow-students-access.log combined
</VirtualHost>

<VirtualHost *:80>
    ServerName $admin_host
    DocumentRoot $app_dir/public
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    ErrorLog \${APACHE_LOG_DIR}/campusflow-admin-error.log
    CustomLog \${APACHE_LOG_DIR}/campusflow-admin-access.log combined
</VirtualHost>
EOF
chmod 640 /etc/apache2/sites-available/campusflow.conf
a2enmod alias headers >/dev/null
a2ensite campusflow.conf >/dev/null
apachectl configtest
systemctl reload apache2

echo '--- Applied migrations ---'
mariadb --batch --skip-column-names "$db_name" -e 'SELECT migration FROM schema_migrations ORDER BY migration;'
migration_count="$(mariadb --batch --skip-column-names "$db_name" -e 'SELECT COUNT(*) FROM schema_migrations')"
[[ "$migration_count" == 4 ]] || { echo "Expected four migrations, found $migration_count." >&2; exit 1; }
mariadb "$db_name" -e 'SHOW TABLES;'

curl --fail --silent --show-error -H "Host: $domain" http://127.0.0.1/ >/dev/null
curl --silent --show-error -I -H "Host: $students_host" http://127.0.0.1/ | grep -qi "Location: /login.php"
curl --silent --show-error -I -H "Host: $admin_host" http://127.0.0.1/ | grep -qi "Location: /admin.php"
curl --silent --show-error -I -H "Host: $www_host" http://127.0.0.1/ | grep -qi "Location: http://$domain/"
php -v | head -n 1
systemctl --no-pager --full status apache2 mariadb | sed -n '1,24p'
echo "HTTP deployment and local host routing passed for $domain, $students_host, $admin_host and $www_host."
echo "Public DNS and ports are not verified. After DNS records resolve and Azure/host firewalls allow HTTP, run scripts/issue-zerossl.sh $domain you@example.com. HTTPS is not yet enabled."
