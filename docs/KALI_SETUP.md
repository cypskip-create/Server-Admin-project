# Kali Linux setup

From a clean Kali VM (replace `<repo-url>` after creating the remote):

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server php libapache2-mod-php php-mysql php-mbstring php-xml git
sudo systemctl enable --now apache2 mariadb
sudo mariadb-secure-installation
sudo mariadb
```

In MariaDB, create the CAT2 database and least-privilege app user (use your own strong password):

```sql
CREATE DATABASE CAT2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'campusflow'@'localhost' IDENTIFIED BY 'REPLACE_WITH_STRONG_PASSWORD';
GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX ON CAT2.* TO 'campusflow'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Clone and configure (web root must point at `public/`):

```bash
git clone <repo-url> ~/campusflow
cd ~/campusflow
cp .env.example .env
nano .env
mariadb -u campusflow -p CAT2 < database/migrations/001_schema.sql
sudo tee /etc/apache2/sites-available/campusflow.conf >/dev/null <<'EOF'
<VirtualHost *:80>
  ServerName campusflow.local
  DocumentRoot /home/student/campusflow/public
  <Directory /home/student/campusflow/public>
    AllowOverride All
    Require all granted
  </Directory>
  ErrorLog ${APACHE_LOG_DIR}/campusflow-error.log
  CustomLog ${APACHE_LOG_DIR}/campusflow-access.log combined
</VirtualHost>
EOF
sudo a2enmod rewrite
sudo a2ensite campusflow
sudo systemctl reload apache2
```

Change `student` to the actual account; add `127.0.0.1 campusflow.local` to `/etc/hosts`. Then visit `http://campusflow.local/signup.php` and register `studentCNSA` and `studentCNSB`. Inspect records with `SELECT username,password_hash FROM users;` (hashes should start with a supported password-hash prefix, never plaintext). Logs: `/var/log/apache2/campusflow-error.log` and `journalctl -u mariadb`.
