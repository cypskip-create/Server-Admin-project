#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

if [[ "$(id -u)" -ne 0 ]]; then
  echo 'Run this script through Azure Run Command as root.' >&2
  exit 1
fi
revision="${1:-}"
[[ "$revision" =~ ^[a-f0-9]{40}$ ]] || {
  echo 'Usage: bash scripts/redeploy-existing-azure.sh <40-character-main-commit-sha>' >&2
  exit 2
}
app_dir=/var/www/campusflow
site_conf=/etc/apache2/sites-available/campusflow.conf
upload_limits_conf=/etc/apache2/conf-available/campusflow-upload-limits.conf
backup_root=/root/campusflow-backups
repo_url=https://github.com/cypskip-create/Server-Admin-project.git

[[ -f /etc/os-release ]] || { echo 'Cannot verify operating system.' >&2; exit 1; }
. /etc/os-release
[[ "${ID:-}" == ubuntu ]] || { echo "This redeploy script requires Ubuntu; detected ${ID:-unknown}." >&2; exit 1; }
[[ -d "$app_dir/.git" && -s "$app_dir/.env" ]] || { echo 'CampusFlow installation or private configuration is missing; refusing to continue.' >&2; exit 1; }
[[ -f "$site_conf" ]] || { echo 'CampusFlow Apache site configuration is missing; refusing to continue.' >&2; exit 1; }
upload_conf_existed=0
upload_conf_was_enabled=0
if [[ -e "$upload_limits_conf" ]]; then
  grep -q 'Scoped to CampusFlow only' "$upload_limits_conf" || { echo 'An unrelated upload-limit configuration already exists; refusing to overwrite it.' >&2; exit 1; }
  grep -q 'php_admin_value upload_max_filesize 6M' "$upload_limits_conf" || { echo 'The existing CampusFlow upload-limit file differs from the expected settings; inspect it before redeployment.' >&2; exit 1; }
  grep -q 'php_admin_value post_max_size 8M' "$upload_limits_conf" || { echo 'The existing CampusFlow upload-limit file has an unexpected POST limit; inspect it before redeployment.' >&2; exit 1; }
  upload_conf_existed=1
  [[ ! -e /etc/apache2/conf-enabled/campusflow-upload-limits.conf ]] || upload_conf_was_enabled=1
fi
git -C "$app_dir" remote get-url origin | grep -Fxq "$repo_url" || { echo 'The existing application remote does not match CampusFlow.' >&2; exit 1; }
[[ -z "$(git -C "$app_dir" status --porcelain --untracked-files=no)" ]] || { echo 'The existing tracked application has local edits; refusing to overwrite them.' >&2; exit 1; }
mariadb --batch --skip-column-names -e 'SELECT 1' >/dev/null

database_name="$(php -r '$env=parse_ini_file($argv[1], false, INI_SCANNER_RAW) ?: []; echo $env["DB_NAME"] ?? "";' "$app_dir/.env")"
[[ "$database_name" == campusflow_prod ]] || { echo 'The configured application database is not the expected isolated CampusFlow database.' >&2; exit 1; }

git -C "$app_dir" fetch --depth=10 origin main
fetched_sha="$(git -C "$app_dir" rev-parse FETCH_HEAD)"
[[ "$revision" == "$fetched_sha" ]] || { echo "Requested revision is not the current origin/main tip ($fetched_sha)." >&2; exit 1; }
git -C "$app_dir" cat-file -e "$revision^{commit}"
previous_revision="$(git -C "$app_dir" rev-parse HEAD)"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_dir="$backup_root/$timestamp"
install -d -o root -g root -m 0700 "$backup_dir"
tar -C /var/www -czf "$backup_dir/application-before.tar.gz" campusflow
cp -a "$site_conf" "$backup_dir/campusflow-apache.conf"
chmod 0600 "$backup_dir/campusflow-apache.conf"
mariadb-dump --single-transaction --routines --triggers --events "$database_name" > "$backup_dir/campusflow_prod-before.sql"
[[ -s "$backup_dir/campusflow_prod-before.sql" ]] || { echo 'Database backup is empty; refusing to continue.' >&2; exit 1; }
chmod 0600 "$backup_dir/campusflow_prod-before.sql"
if [[ "$upload_conf_existed" -eq 1 ]]; then
  cp -a "$upload_limits_conf" "$backup_dir/campusflow-upload-limits.conf-before"
  chmod 0600 "$backup_dir/campusflow-upload-limits.conf-before"
else
  printf 'absent\n' > "$backup_dir/campusflow-upload-limits.conf-before-state"
  chmod 0600 "$backup_dir/campusflow-upload-limits.conf-before-state"
fi
user_ini_file="$app_dir/.user.ini"
user_ini_existed=0
user_ini_expected=$'upload_max_filesize=6M\npost_max_size=8M\nmax_file_uploads=10\nmax_execution_time=60\nmax_input_time=60'
if [[ -e "$user_ini_file" ]]; then
  [[ -f "$user_ini_file" && ! -L "$user_ini_file" ]] || { echo 'The existing app-local PHP settings are not a regular file; refusing to overwrite them.' >&2; exit 1; }
  user_ini_actual="$(cat "$user_ini_file")"
  [[ "$user_ini_actual" == "$user_ini_expected" ]] || { echo 'The existing app-local PHP settings differ from the CampusFlow photo limits; refusing to overwrite them.' >&2; exit 1; }
  cp -a "$user_ini_file" "$backup_dir/campusflow-user.ini-before"
  chmod 0600 "$backup_dir/campusflow-user.ini-before"
  user_ini_existed=1
else
  printf 'absent\n' > "$backup_dir/campusflow-user.ini-before-state"
  chmod 0600 "$backup_dir/campusflow-user.ini-before-state"
fi
echo "Private application and database backups created in $backup_dir."
echo "Previous code revision: $previous_revision"

stage_dir="$(mktemp -d /tmp/campusflow-release.XXXXXX)"
test_db="campusflow_release_test_${timestamp//[^0-9]/}_$$"
test_db_created=0
deploy_changed=0
needs_apache_restart=0
upload_config_changed=0
user_ini_changed=0
cleanup() {
  status=$?
  if [[ "$status" -ne 0 && "$deploy_changed" -eq 1 ]]; then
    echo 'Deployment verification failed; restoring the previous code revision.' >&2
    if git -C "$app_dir" checkout --detach "$previous_revision"; then
      apachectl configtest && systemctl reload apache2 || true
    else
      echo "Automatic code rollback failed. Previous application backup: $backup_dir/application-before.tar.gz" >&2
    fi
  fi
  if [[ "$status" -ne 0 && "$upload_config_changed" -eq 1 ]]; then
    if [[ "$upload_conf_existed" -eq 1 ]]; then
      cp -a "$backup_dir/campusflow-upload-limits.conf-before" "$upload_limits_conf"
      if [[ "$upload_conf_was_enabled" -eq 1 ]]; then a2enconf campusflow-upload-limits >/dev/null || true; else a2disconf campusflow-upload-limits >/dev/null 2>&1 || true; fi
    else
      a2disconf campusflow-upload-limits >/dev/null 2>&1 || true
      rm -f -- "$upload_limits_conf"
    fi
    apachectl configtest && systemctl reload apache2 || true
  fi
  if [[ "$status" -ne 0 && "$user_ini_changed" -eq 1 ]]; then
    if [[ "$user_ini_existed" -eq 1 ]]; then
      cp -a "$backup_dir/campusflow-user.ini-before" "$user_ini_file"
    else
      rm -f -- "$user_ini_file"
    fi
  fi
  if [[ "$test_db_created" -eq 1 ]]; then
    mariadb -e "DROP DATABASE IF EXISTS \`$test_db\`;" >/dev/null 2>&1 || echo "Temporary test database cleanup failed: $test_db" >&2
  fi
  case "$stage_dir" in
    /tmp/campusflow-release.*) rm -rf -- "$stage_dir" ;;
  esac
  exit "$status"
}
trap cleanup EXIT

git -C "$app_dir" archive --format=tar "$revision" | tar -xf - -C "$stage_dir"
if ! php -m | grep -qi '^gd$'; then
  apt-get update
  apt-get install -y php-gd
  needs_apache_restart=1
fi
php -m | grep -qi '^gd$' || { echo 'PHP GD is required for profile-photo processing.' >&2; exit 1; }

[[ "$test_db" =~ ^[A-Za-z0-9_]+$ ]] || { echo 'Invalid temporary test database name.' >&2; exit 1; }
mariadb -e "CREATE DATABASE \`$test_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
test_db_created=1
DB_HOST=localhost DB_NAME="$test_db" DB_USER=root DB_PASS='' php "$stage_dir/scripts/migrate.php"
(
  cd "$stage_dir"
  TEST_DB_HOST=localhost TEST_DB_NAME="$test_db" TEST_DB_USER=root TEST_DB_PASS='' bash scripts/test.sh
)

# Apply the additive nullable column while the old application remains active.
# The previous code ignores the new column, so a failed code switch is safe.
DB_HOST=localhost DB_NAME="$database_name" DB_USER=root DB_PASS='' php "$stage_dir/scripts/migrate.php"
applied="$(mariadb --batch --skip-column-names "$database_name" -e "SELECT COUNT(*) FROM schema_migrations WHERE migration='005_profile_photos.sql'")"
[[ "$applied" == 1 ]] || { echo 'The profile-photo migration was not recorded.' >&2; exit 1; }
mariadb --batch --skip-column-names "$database_name" -e 'SHOW COLUMNS FROM users LIKE "profile_photo_key";' | grep -q profile_photo_key

deploy_changed=1
git -C "$app_dir" checkout --detach "$revision"
if ! grep -q '^PROFILE_PHOTO_DIR=' "$app_dir/.env"; then
  printf '\nPROFILE_PHOTO_DIR=/var/lib/campusflow/profile-photos\n' >> "$app_dir/.env"
fi
photo_dir="$(php -r '$env=parse_ini_file($argv[1], false, INI_SCANNER_RAW) ?: []; echo $env["PROFILE_PHOTO_DIR"] ?? "";' "$app_dir/.env")"
[[ "$photo_dir" == /* && "$photo_dir" != *$'\n'* ]] || { echo 'Profile-photo storage must use a configured absolute path.' >&2; exit 1; }
photo_dir_real="$(realpath -m "$photo_dir")"
[[ "$photo_dir_real" != /var/www/campusflow/public && "$photo_dir_real" != /var/www/campusflow/public/* ]] || { echo 'Profile-photo storage cannot be inside the web root.' >&2; exit 1; }
[[ ! -L "$photo_dir" ]] || { echo 'Profile-photo storage cannot be a symbolic link.' >&2; exit 1; }
install -d -o www-data -g www-data -m 0750 "$photo_dir"
user_ini_changed=1
cat > "$user_ini_file" <<'EOF'
upload_max_filesize=6M
post_max_size=8M
max_file_uploads=10
max_execution_time=60
max_input_time=60
EOF
chmod 0640 "$app_dir/.user.ini"
if apachectl -M 2>/dev/null | grep -Eq 'php(_[0-9]+)?_module'; then
  if [[ "$upload_conf_existed" -eq 0 ]]; then
    upload_config_changed=1
    cat > "$upload_limits_conf" <<EOF
# Scoped to CampusFlow only; unrelated PHP applications retain their settings.
<Directory "$app_dir/public">
    <IfModule php_module>
        php_admin_value upload_max_filesize 6M
        php_admin_value post_max_size 8M
        php_admin_value max_file_uploads 10
        php_admin_value max_execution_time 60
        php_admin_value max_input_time 60
    </IfModule>
</Directory>
EOF
    chmod 0640 "$upload_limits_conf"
  fi
  upload_config_changed=1
  a2enconf campusflow-upload-limits >/dev/null
elif ! systemctl list-units --type=service --state=active --no-legend 'php*-fpm.service' 2>/dev/null | grep -q 'php.*-fpm'; then
  echo 'No Apache PHP module or active PHP-FPM service is available for the scoped upload limits.' >&2
  exit 1
fi
chown root:www-data "$app_dir/.env"
chmod 0640 "$app_dir/.env"
chown -R root:www-data "$app_dir"
find "$app_dir" -type d -exec chmod 0750 {} +
find "$app_dir" -type f -exec chmod 0640 {} +

applied="$(mariadb --batch --skip-column-names "$database_name" -e "SELECT COUNT(*) FROM schema_migrations WHERE migration='005_profile_photos.sql'")"
[[ "$applied" == 1 ]] || { echo 'The profile-photo migration was not recorded.' >&2; exit 1; }
mariadb --batch --skip-column-names "$database_name" -e 'SHOW COLUMNS FROM users LIKE "profile_photo_key";' | grep -q profile_photo_key
apachectl configtest
if [[ "$needs_apache_restart" -eq 1 ]]; then
  systemctl restart apache2
else
  systemctl reload apache2
fi
systemctl is-active --quiet apache2
systemctl is-active --quiet mariadb

for host in fuadsaary.me www.fuadsaary.me students.fuadsaary.me admin.fuadsaary.me; do
  status="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --resolve "$host:443:127.0.0.1" "https://$host/")"
  case "$host:$status" in
    fuadsaary.me:200|www.fuadsaary.me:301|students.fuadsaary.me:302|admin.fuadsaary.me:302) echo "HTTPS route passed: $host ($status)" ;;
    *) echo "Unexpected HTTPS route result for $host: $status" >&2; exit 1 ;;
  esac
done
for host in fuadsaary.me www.fuadsaary.me students.fuadsaary.me admin.fuadsaary.me; do
  http_status="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --resolve "$host:80:127.0.0.1" "http://$host/")"
  [[ "$http_status" == 301 ]] || { echo "Unexpected HTTP status for $host: $http_status (expected 301)." >&2; exit 1; }
done
curl --fail --silent --show-error --resolve students.fuadsaary.me:443:127.0.0.1 https://students.fuadsaary.me/login.php | grep -q 'Sign in'
curl --fail --silent --show-error --resolve admin.fuadsaary.me:443:127.0.0.1 https://admin.fuadsaary.me/admin-login.php | grep -q 'Administrator sign in'
admin_gate_status="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --resolve admin.fuadsaary.me:443:127.0.0.1 https://admin.fuadsaary.me/admin.php)"
[[ "$admin_gate_status" == 302 ]] || { echo "Anonymous admin dashboard returned $admin_gate_status, expected a login redirect." >&2; exit 1; }
for asset in style.css app.js; do
  curl --fail --silent --show-error --resolve students.fuadsaary.me:443:127.0.0.1 "https://students.fuadsaary.me/assets/$asset" >/dev/null
done
auth_status="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --resolve students.fuadsaary.me:443:127.0.0.1 https://students.fuadsaary.me/profile-photo.php)"
[[ "$auth_status" == 401 ]] || { echo "Unauthenticated photo endpoint returned $auth_status, expected 401." >&2; exit 1; }

echo "CampusFlow redeployment succeeded at $revision."
echo "Migration 005 is applied; uploaded photos use private storage at $photo_dir."
echo "Application backup: $backup_dir/application-before.tar.gz"
echo "Database backup: $backup_dir/campusflow_prod-before.sql"
echo 'Apache and MariaDB are active; TLS routes, admin login, protected anonymous admin dashboard, versioned assets and unauthenticated-photo authorization passed.'
echo 'CampusFlow photo upload limits are 6M per file and 8M per POST; the app accepts up to 5 MiB.'
