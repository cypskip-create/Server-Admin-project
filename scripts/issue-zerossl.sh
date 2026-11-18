#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

[[ "$(id -u)" -eq 0 ]] || { echo 'Run with sudo after the HTTP vhost and DNS are ready.' >&2; exit 1; }
domain="${1:-}"
email="${2:-}"
[[ "$domain" =~ ^([A-Za-z0-9-]+\.)+[A-Za-z]{2,}$ && "$email" =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]] || {
  echo 'Usage: sudo bash scripts/issue-zerossl.sh fuadsaary.me contact@example.com' >&2
  exit 2
}
domain="${domain,,}"
hosts=("$domain" "www.$domain" "students.$domain" "admin.$domain")
app_dir=/var/www/campusflow
apache_site=/etc/apache2/sites-available/campusflow.conf
env_file="$app_dir/.env"
template="$app_dir/deploy/apache/campusflow.conf.example"
acme="$(command -v acme.sh || true)"
if [[ -z "$acme" && -x /root/.acme.sh/acme.sh ]]; then acme=/root/.acme.sh/acme.sh; fi
[[ -n "$acme" ]] || { echo 'Install acme.sh as root first; see docs/SSL_CONFIGURATION.md.' >&2; exit 1; }
acme_source="$(readlink -f "$acme")"
acme_home="$(dirname "$acme_source")"
acme_config_home="${LE_CONFIG_HOME:-/root/.acme.sh}"
[[ "$acme_config_home" == /* && "$acme_config_home" != *" "* ]] || {
  echo 'ACME config home must be an absolute path without spaces.' >&2
  exit 1
}
[[ -f "$apache_site" && -f "$env_file" && -f "$template" ]] || { echo 'CampusFlow HTTP deployment is incomplete.' >&2; exit 1; }
for required in "MAIN_HOST=$domain" "STUDENTS_HOST=students.$domain" "ADMIN_HOST=admin.$domain"; do
  grep -Fxq "$required" "$env_file" || { echo "Expected $required in $env_file." >&2; exit 1; }
done

for host in "${hosts[@]}"; do
  getent ahostsv4 "$host" >/dev/null || { echo "DNS A/AAAA lookup failed for $host; publish the DNS records first." >&2; exit 1; }
done
[[ "$(apachectl -t 2>&1)" == *'Syntax OK'* ]] || apachectl configtest

# acme.sh can obtain ZeroSSL EAB credentials using the ACME contact email. Its
# normal registration path writes those values to ca.conf. Use a RAM-backed,
# one-shot copy with only those persistence statements removed, so the EAB
# values remain in process memory and are never written to disk or argv.
ca_conf="$acme_config_home/ca/acme.zerossl.com/v2/DV90/ca.conf"
if [[ -f "$ca_conf" ]] && grep -Eq '^CA_EAB_(KEY_ID|HMAC_KEY)=' "$ca_conf"; then
  echo 'Existing ZeroSSL EAB data is present in the ACME config; inspect and remove it locally before continuing.' >&2
  exit 1
fi
secure_client_dir=""
cleanup_secure_client() {
  if [[ -n "$secure_client_dir" && -d "$secure_client_dir" ]]; then
    rm -rf -- "$secure_client_dir"
  fi
}
trap cleanup_secure_client EXIT
[[ -f "$acme_source" && -x "$acme_source" && -d /dev/shm ]] && findmnt -n -o FSTYPE /dev/shm | grep -Fxq tmpfs || {
  echo 'The acme.sh source or RAM-backed temporary storage is unavailable.' >&2
  exit 1
}
secure_client_dir="$(mktemp -d /dev/shm/campusflow-acme.XXXXXX)"
secure_client="$secure_client_dir/acme.sh"
eab_save_count="$(grep -Ec '^[[:space:]]*_savecaconf[[:space:]]+CA_EAB_(KEY_ID|HMAC_KEY)([[:space:]]|$)' "$acme_source" || true)"
[[ "$eab_save_count" -ge 2 ]] || {
  echo 'This acme.sh version does not match the reviewed EAB handling; refusing to register.' >&2
  exit 1
}
sed -E '/^[[:space:]]*_savecaconf[[:space:]]+CA_EAB_(KEY_ID|HMAC_KEY)([[:space:]]|$)/d' "$acme_source" > "$secure_client"
chmod 700 "$secure_client"
if grep -Eq '_savecaconf[[:space:]]+CA_EAB_(KEY_ID|HMAC_KEY)([[:space:]]|$)' "$secure_client"; then
  echo 'Unable to remove EAB persistence from the temporary ACME client.' >&2
  exit 1
fi

backup_dir="/root/campusflow-backups/$(date -u +%Y%m%dT%H%M%SZ)-zerossl"
install -d -o root -g root -m 700 "$backup_dir"
cp -a "$apache_site" "$backup_dir/campusflow.conf-before-tls"
chmod 600 "$backup_dir/campusflow.conf-before-tls"
echo "CampusFlow Apache configuration backup: $backup_dir/campusflow.conf-before-tls"

"$acme" --home "$acme_home" --config-home "$acme_config_home" --set-default-ca --server zerossl
"$secure_client" --home "$acme_home" --config-home "$acme_config_home" --register-account --server zerossl -m "$email" --log-level 1
if [[ -f "$ca_conf" ]] && grep -Eq '^CA_EAB_(KEY_ID|HMAC_KEY)=' "$ca_conf"; then
  echo 'EAB data was persisted despite the temporary client guard; stopping before certificate issuance.' >&2
  exit 1
fi
cleanup_secure_client
secure_client_dir=""
issue_args=(--home "$acme_home" --config-home "$acme_config_home" --issue --server zerossl --webroot "$app_dir/public")
for host in "${hosts[@]}"; do issue_args+=(-d "$host"); done
"$acme" "${issue_args[@]}"

install -d -o root -g root -m 755 /etc/ssl/certs
install -d -o root -g www-data -m 750 /etc/ssl/private
"$acme" --home "$acme_home" --config-home "$acme_config_home" --install-cert -d "$domain" \
  --key-file /etc/ssl/private/campusflow-key.pem \
  --fullchain-file /etc/ssl/certs/campusflow-fullchain.pem \
  --reloadcmd 'systemctl reload apache2'
chown root:www-data /etc/ssl/private/campusflow-key.pem
chmod 640 /etc/ssl/private/campusflow-key.pem
chown root:root /etc/ssl/certs/campusflow-fullchain.pem
chmod 644 /etc/ssl/certs/campusflow-fullchain.pem

rendered="$(mktemp /etc/apache2/sites-available/campusflow.XXXXXX)"
sed "s|@DOMAIN@|$domain|g" "$template" > "$rendered"
chown root:root "$rendered"
chmod 640 "$rendered"
mv "$rendered" "$apache_site"
a2enmod alias headers ssl >/dev/null
if ! apachectl configtest; then
  cp -a "$backup_dir/campusflow.conf-before-tls" "$apache_site"
  apachectl configtest || true
  echo "TLS vhost rejected; restored the backed-up CampusFlow HTTP config. Backup: $backup_dir" >&2
  exit 1
fi
systemctl reload apache2

python3 - "$env_file" "$domain" <<'PY'
from pathlib import Path
import re
import sys

path = Path(sys.argv[1])
domain = sys.argv[2]
content = path.read_text()

secure_values = re.findall(r'^SESSION_SECURE=(true|false)$', content, flags=re.MULTILINE)
if secure_values == ['false']:
    content = re.sub(r'^SESSION_SECURE=false$', 'SESSION_SECURE=true', content, count=1, flags=re.MULTILINE)
elif secure_values != ['true']:
    raise SystemExit('Expected one valid SESSION_SECURE setting; manual review is required.')

app_pattern = re.compile(r'^APP_URL=(https?)://students\.' + re.escape(domain) + r'(/[^\r\n]*)?$', re.MULTILINE)
matches = list(app_pattern.finditer(content))
if len(matches) != 1:
    raise SystemExit('Expected one APP_URL for the configured students host; manual review is required.')
match = matches[0]
if match.group(1) == 'http':
    content = content[:match.start()] + 'APP_URL=https://students.' + domain + (match.group(2) or '') + content[match.end():]

temporary = path.with_suffix('.env.tmp')
temporary.write_text(content)
temporary.chmod(0o640)
temporary.replace(path)
PY
chown root:www-data "$env_file"
chmod 640 "$env_file"
systemctl reload apache2

cert=/etc/ssl/certs/campusflow-fullchain.pem
openssl x509 -in "$cert" -checkend 86400 -noout
san="$(openssl x509 -in "$cert" -noout -ext subjectAltName)"
for host in "${hosts[@]}"; do
  grep -Fq "$host" <<<"$san" || { echo "Certificate SAN missing $host." >&2; exit 1; }
  curl --fail --silent --show-error -I "https://$host/" >/dev/null
done

for host in "${hosts[@]}"; do
  expected="https://$host/"
  [[ "$host" == "www.$domain" ]] && expected="https://$domain/"
  headers="$(curl --silent --show-error -D - -o /dev/null "http://$host/")"
  grep -Fqi "Location: $expected" <<<"$headers" || { echo "HTTP to HTTPS redirect failed for $host." >&2; exit 1; }
done

session_headers="$(curl --silent --show-error -D - -o /dev/null "https://students.$domain/login.php")"
grep -Eiq '^Set-Cookie:.*;[[:space:]]*secure([;[:space:]]|$)' <<<"$session_headers" || {
  echo 'Student login response did not set a Secure session cookie after enabling SESSION_SECURE.' >&2
  exit 1
}
grep -Eiq '^Strict-Transport-Security:' <<<"$session_headers" || { echo 'HSTS header missing on student host.' >&2; exit 1; }

if ! crontab -u root -l 2>/dev/null | grep -Fq -- '--cron'; then
  "$acme" --install-cronjob
fi
crontab -u root -l | grep -F -- '--cron'
echo '--- ZeroSSL certificate ---'
openssl x509 -in "$cert" -noout -subject -issuer -dates -ext subjectAltName
echo 'HTTPS, HTTP redirects, hostname SANs, HSTS, Secure session cookie and renewal cron configuration passed.'

