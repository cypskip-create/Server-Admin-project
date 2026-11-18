# Kali Linux setup

Clone the repo into `/var/www/campusflow` on the Kali VM, then run the setup script from a sudo-enabled normal account. The script backs up existing Apache and MariaDB configuration, refuses to overwrite an existing CampusFlow site, `.env`, CAT2 database, or application database user, and leaves other enabled Apache sites alone.

```bash
sudo apt-get update
sudo apt-get install -y git
sudo git clone https://github.com/cypskip-create/Server-Admin-project.git /var/www/campusflow
sudo chown -R "$USER":www-data /var/www/campusflow
cd /var/www/campusflow
bash scripts/setup-kali.sh
```

The script installs Apache, MariaDB and required PHP packages; creates `CAT2` and a restricted app user; applies all four migrations using a migration ledger; writes `.env` without committing it; configures Apache and a local `campusflow.local` host; then checks Apache configuration and the landing-page HTTP response. If your VM has a custom MariaDB root password instead of socket authentication, adapt the migration invocation inside the script before running it.

Check services and schema:

```bash
sudo systemctl status apache2 mariadb --no-pager
sudo mariadb CAT2 -e 'SHOW TABLES; SELECT migration,applied_at FROM schema_migrations ORDER BY migration;'
curl -I -H 'Host: campusflow.local' http://127.0.0.1/
curl -I -H 'Host: students.campusflow.local' http://127.0.0.1/
curl -I -H 'Host: admin.campusflow.local' http://127.0.0.1/
```

Then open `http://students.campusflow.local/signup.php` in the VM browser and register `studentCNSA` and `studentCNSB` through the actual form. Log in as each account, submit a service request, open its detail timeline and record real screenshots per `CAT2_CHECKLIST.md`. To check password storage without exposing hashes, run `SELECT username, password_hash <> username AS hash_is_not_username FROM users;` in the test VM and capture only a redacted query result; never include hashes or passwords in submitted evidence.

Logs: `/var/log/apache2/campusflow-kali-error.log` and `sudo journalctl -u mariadb`. Backups are stored under `/tmp/campusflow-kali-backups/` with root-only permissions. If setup fails, inspect the exact failing command, keep `.env` secret, and rerun only after understanding which database/schema objects were created.
