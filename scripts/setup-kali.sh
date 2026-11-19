#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
if [[ "$(id -u)" -eq 0 ]]; then
  echo 'Run as your normal Kali VM user; this script invokes sudo where needed.' >&2
  exit 1
fi

db_name="${DB_NAME:-CAT2}"
db_user="${DB_USER:-campusflow_lab}"
site_host="${SITE_HOST:-campusflow.local}"
site_id="$(id -un | tr -cd 'A-Za-z0-9_-')"
site_file="campusflow-kali-${site_id}.conf"
students_host="students.$site_host"
admin_host="admin.$site_host"
www_host="www.$site_host"
[[ "$db_name" =~ ^[A-Za-z0-9_]+$ && "$db_user" =~ ^[A-Za-z0-9_]+$ && "$site_host" =~ ^([A-Za-z0-9-]+\.)*[A-Za-z0-9-]+$ ]] || {
  echo 'Invalid database or host name.' >&2
  exit 1
}
[[ ! -e "$repo_dir/.env" ]] || { echo "Refusing to overwrite $repo_dir/.env; inspect it and back it up first." >&2; exit 1; }
[[ ! -e "/etc/apache2/sites-available/$site_file" ]] || { echo "Refusing to overwrite existing Apache site $site_file." >&2; exit 1; }

read -rsp 'Choose an application DB password (16+ letters/digits and _!@#%+=:,.?- only): ' db_secret; echo
[[ "$db_secret" =~ ^[A-Za-z0-9_\!@#%+=:,\.\?-]{16,128}$ ]] || {
  echo 'Password did not meet the allowed format/length.' >&2
  exit 1
}

backup_dir="/tmp/campusflow-kali-backups/$(date -u +%Y%m%dT%H%M%SZ)"
sudo install -d -o root -g root -m 700 "$backup_dir"
for config in apache2 mysql; do
  if sudo test -d "/etc/$config"; then
    sudo tar -C /etc -cpf "$backup_dir/${config}-before.tar" "$config"
    sudo chmod 600 "$backup_dir/${config}-before.tar"
  fi
done
echo "Configuration backup directory: $backup_dir"

sudo env DEBIAN_FRONTEND=noninteractive apt-get update
sudo env DEBIAN_FRONTEND=noninteractive apt-get install -y apache2 mariadb-server php php-cli libapache2-mod-php php-mysql php-mbstring php-gd git curl
sudo systemctl enable --now apache2 mariadb

database_exists="$(sudo mariadb --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$db_name'")"
account_exists="$(sudo mariadb --batch --skip-column-names -e "SELECT COUNT(*) FROM mysql.user WHERE User='$db_user'")"
if [[ "$database_exists" != 0 || "$account_exists" != 0 ]]; then
  unset db_secret
  echo "Refusing to reuse existing database '$db_name' or user '$db_user'. Choose unused names after review." >&2
  exit 1
fi

sudo mariadb <<SQL
CREATE DATABASE $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '$db_user'@'127.0.0.1' IDENTIFIED BY '$db_secret';
GRANT SELECT, INSERT, UPDATE, DELETE ON $db_name.* TO '$db_user'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
sudo env DB_HOST=localhost DB_NAME="$db_name" DB_USER=root DB_PASS='' php "$repo_dir/scripts/migrate.php"
sudo install -d -o root -g www-data -m 0750 /var/lib/campusflow
sudo install -d -o www-data -g www-data -m 0750 /var/lib/campusflow/profile-photos

sudo tee "$repo_dir/.env" >/dev/null <<EOF
APP_ENV=development
APP_URL=http://$students_host
APP_NAME=CampusFlow
MAIN_HOST=$site_host
WWW_HOST=$www_host
STUDENTS_HOST=$students_host
ADMIN_HOST=$admin_host
DB_HOST=127.0.0.1
DB_PORT=3306
DB_TIMEZONE=+03:00
DB_NAME=$db_name
DB_USER=$db_user
DB_PASS="$db_secret"
SESSION_SECURE=false
PROFILE_PHOTO_DIR=/var/lib/campusflow/profile-photos
EOF
unset db_secret
sudo chown root:www-data "$repo_dir/.env"
sudo chmod 640 "$repo_dir/.env"

sudo tee "/etc/apache2/sites-available/$site_file" >/dev/null <<EOF
# CampusFlow-only Kali site; existing sites remain enabled.
<Directory $repo_dir/public>
    Options -Indexes
    AllowOverride None
    Require all granted
</Directory>
<VirtualHost *:80>
    ServerName $site_host
    DocumentRoot $repo_dir/public
    ErrorLog \${APACHE_LOG_DIR}/campusflow-kali-error.log
    CustomLog \${APACHE_LOG_DIR}/campusflow-kali-access.log combined
</VirtualHost>
<VirtualHost *:80>
    ServerName $www_host
    Redirect permanent / http://$site_host/
</VirtualHost>
<VirtualHost *:80>
    ServerName $students_host
    DocumentRoot $repo_dir/public
    ErrorLog \${APACHE_LOG_DIR}/campusflow-kali-students-error.log
    CustomLog \${APACHE_LOG_DIR}/campusflow-kali-students-access.log combined
</VirtualHost>
<VirtualHost *:80>
    ServerName $admin_host
    DocumentRoot $repo_dir/public
    ErrorLog \${APACHE_LOG_DIR}/campusflow-kali-admin-error.log
    CustomLog \${APACHE_LOG_DIR}/campusflow-kali-admin-access.log combined
</VirtualHost>
EOF
sudo chmod 640 "/etc/apache2/sites-available/$site_file"
sudo a2enmod alias headers >/dev/null
sudo a2ensite "$site_file" >/dev/null
sudo apachectl configtest
sudo systemctl reload apache2

hosts_line="127.0.0.1 $site_host $www_host $students_host $admin_host"
if ! grep -Eq "(^|[[:space:]])$students_host([[:space:]]|$)" /etc/hosts; then
  printf '%s\n' "$hosts_line" | sudo tee -a /etc/hosts >/dev/null
fi
sudo mariadb "$db_name" -e 'SHOW TABLES; SELECT migration,applied_at FROM schema_migrations ORDER BY migration;'
[[ "$(sudo mariadb --batch --skip-column-names "$db_name" -e 'SELECT COUNT(*) FROM schema_migrations')" == 5 ]]
php -m | grep -qi '^gd$'
curl --fail --silent --show-error -H "Host: $site_host" http://127.0.0.1/ >/dev/null
curl --silent --show-error -I -H "Host: $students_host" http://127.0.0.1/ | grep -qi 'Location: /login.php'
curl --silent --show-error -I -H "Host: $admin_host" http://127.0.0.1/ | grep -qi 'Location: /admin.php'
echo "Kali setup passed for http://$site_host, http://$students_host and http://$admin_host. Backups: $backup_dir"
echo 'Open the URLs from the Kali VM browser and capture the actual signup, login and request screenshots for CAT2 evidence.'
