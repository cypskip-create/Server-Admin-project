#!/usr/bin/env bash
set -Eeuo pipefail
[[ "$(id -u)" -eq 0 ]] || { echo 'Run with sudo after the HTTP vhost is active.' >&2;exit 1; }
domain="${1:-}";email="${2:-}";[[ "$domain" =~ ^([A-Za-z0-9-]+\.)+[A-Za-z]{2,}$ && "$email" =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]] || { echo 'Usage: sudo scripts/issue-zerossl.sh real-domain.example admin@example.edu' >&2;exit 2; }
acme="$(command -v acme.sh || true)";if [[ -z "$acme" && -x /root/.acme.sh/acme.sh ]];then acme=/root/.acme.sh/acme.sh;fi;[[ -n "$acme" ]] || { echo 'Install acme.sh as root first; see docs/SSL_CONFIGURATION.md.' >&2;exit 1; }
read -rsp 'ZeroSSL EAB KID: ' eab_kid;echo;read -rsp 'ZeroSSL EAB HMAC key: ' eab_hmac;echo
[[ -n "$eab_kid" && -n "$eab_hmac" ]] || { echo 'EAB credentials are required.' >&2;exit 1; }
app_dir=/var/www/campusflow
"$acme" --set-default-ca --server zerossl
"$acme" --register-account --server zerossl --eab-kid "$eab_kid" --eab-hmac-key "$eab_hmac" -m "$email"
unset eab_kid eab_hmac
"$acme" --issue --server zerossl --webroot "$app_dir/public" -d "$domain" -d "www.$domain" -d "app.$domain" -d "admin.$domain"
install -o root -g root -m 755 -d /etc/ssl/certs
install -o root -g www-data -m 750 -d /etc/ssl/private
sudo touch /etc/ssl/private/campusflow-key.pem /etc/ssl/certs/campusflow-fullchain.pem
sudo chown root:www-data /etc/ssl/private/campusflow-key.pem;sudo chmod 640 /etc/ssl/private/campusflow-key.pem
sudo chown root:root /etc/ssl/certs/campusflow-fullchain.pem;sudo chmod 644 /etc/ssl/certs/campusflow-fullchain.pem
"$acme" --install-cert -d "$domain" --key-file /etc/ssl/private/campusflow-key.pem --fullchain-file /etc/ssl/certs/campusflow-fullchain.pem --reloadcmd 'systemctl reload apache2'
sudo sed "s/example\.edu/$domain/g" "$app_dir/deploy/apache/campusflow.conf.example" | sudo tee /etc/apache2/sites-available/campusflow.conf >/dev/null
sudo python3 - "$app_dir/.env" <<'PY'
from pathlib import Path
import re
import sys
config = Path(sys.argv[1])
content = config.read_text()
if 'SESSION_SECURE=' not in content:
    raise SystemExit('SESSION_SECURE is missing from .env; update it manually after verifying file permissions.')
config.write_text(re.sub(r'^SESSION_SECURE=.*$', 'SESSION_SECURE=true', content, flags=re.MULTILINE))
PY
sudo chown root:www-data "$app_dir/.env";sudo chmod 640 "$app_dir/.env"
sudo apachectl configtest;sudo systemctl reload apache2
curl --fail --silent --show-error -I "https://$domain/" >/dev/null
curl --fail --silent --show-error -I "https://www.$domain/" >/dev/null
curl --fail --silent --show-error -I "https://app.$domain/login.php" >/dev/null
curl --fail --silent --show-error -I "https://admin.$domain/admin.php" >/dev/null
echo 'Certificate issued/installed and host HTTP checks passed. Confirm renewal with acme.sh --list and inspect each SAN/certificate with openssl s_client.'
