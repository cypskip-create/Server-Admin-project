# Ubuntu Azure VM deployment

This runbook targets the existing Azure VM `cyprianServer` in resource group `ServerAdmin-Labs` (Ubuntu 24.04.5 LTS, South Africa North). It does not create a VM. The current Azure Public IP is `102.37.155.177`, allocated as Standard/Static. Run `bash scripts/deploy-ubuntu.sh fuadsaary.me` as root through Azure Run Command after reviewing the script.

The installer takes a root-only backup of any existing `/etc/apache2` and `/etc/mysql` configuration before package changes. It refuses to replace `/var/www/campusflow` or an existing CampusFlow site file, creates only the `campusflow_prod` schema and `campusflow_app` database user, and never disables the default site or changes existing databases/grants. The CampusFlow runtime user gets SELECT/INSERT/UPDATE/DELETE only on its own schema; the migration runner uses the local administrative account to apply DDL.

The hostnames are `fuadsaary.me` (main landing page), `www.fuadsaary.me` (canonical redirect), `students.fuadsaary.me` (student app), and `admin.fuadsaary.me` (role-checked administration). The HTTP setup uses a separate CampusFlow Apache site file and keeps HTTPS disabled until DNS and inbound web access are ready.

## Database and app configuration

The deploy script runs `scripts/migrate.php`, which applies the ordered files in `database/migrations/` and records them in `schema_migrations`:

1. `001_schema.sql`: users, service requests and status history.
2. `002_login_attempts.sql`: persistent login throttling in the `login_attempts` table.
3. `003_sla_notifications.sql`: server priority recommendation, response SLA timestamps and database-backed notifications.
4. `004_login_attempt_cleanup.sql`: index used to expire old rate-limit rows safely.

For manual operations, configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` for the migration runner, then run `php scripts/migrate.php` as a database account with DDL access. The runtime MariaDB user should have only SELECT/INSERT/UPDATE/DELETE after migrations. The migration account needs DDL access.

`APP_ENV=production` and the four configured hostnames are written to `/var/www/campusflow/.env`; the generated database password is random and is not printed by the installer. `SESSION_SECURE` remains false while the deployment is HTTP-only; the ZeroSSL script changes it to true and changes `APP_URL` to HTTPS only after TLS checks pass. The `.env` is root-owned and group-readable by Apache; the public document root is the `public/` subdirectory.

## Azure workflow

1. Keep `cyprianServer` running during installation and testing. The latest read-only Azure Run Command inventory confirmed Ubuntu 24.04.5 LTS (kernel `7.0.0-1017-azure`), 3.8 GiB RAM, about 26 GiB free on the 30 GiB OS disk, no Apache/PHP/MariaDB/PostgreSQL/container packages detected, no `/var/www` application tree or Apache sites directory, and no web/database listeners. UFW is inactive. The existing NSG currently permits restricted SSH only; TCP 80/443 are not yet open.
2. Before public exposure, ask for approval to add TCP 80 and 443 inbound rules to `cyprianServer-nsg`. Preserve the existing SSH source restriction; do not open SSH to the Internet.
3. DNS records were saved through Namecheap Advanced DNS on 2026-11-19: `@`, `students`, and `admin` A records to `102.37.155.177`; `www` CNAME to `fuadsaary.me`. A read-only `getent ahostsv4` check from the Azure VM returned that IPv4 for all four names. Independently query the authoritative nameservers/public resolvers and test public HTTP after the approved NSG change. The existing SPF TXT record and Email Forwarding setting were preserved.
4. In the Azure portal, open **VM → Run command → RunShellScript** and run:

   ```bash
   set -e
   cd /tmp
   git clone --depth 1 https://github.com/cypskip-create/Server-Admin-project.git campusflow-deploy
   bash /tmp/campusflow-deploy/scripts/deploy-ubuntu.sh fuadsaary.me
   ```

   The deployment script exits if its destination, dedicated database, database user or Apache site already exists. Investigate rather than deleting/reusing those objects.
5. Save the returned terminal output and Azure portal VM overview screenshot as deployment evidence. Do not include `.env`, generated DB credentials, EAB values or private keys in screenshots.

## Verify and recover

```bash
sudo apachectl configtest
sudo systemctl status apache2 mariadb --no-pager
curl -I -H 'Host: fuadsaary.me' http://127.0.0.1/
curl -I -H 'Host: students.fuadsaary.me' http://127.0.0.1/
curl -I -H 'Host: admin.fuadsaary.me' http://127.0.0.1/
curl -I -H 'Host: www.fuadsaary.me' http://127.0.0.1/
sudo mariadb campusflow_prod -e 'SHOW TABLES; SELECT migration,applied_at FROM schema_migrations ORDER BY migration;'
sudo mariadb campusflow_prod -e 'SHOW COLUMNS FROM login_attempts;'
sudo tail -n 100 /var/log/apache2/campusflow-error.log
```

After public DNS and HTTPS are set up, use the HTTPS checks in `SSL_CONFIGURATION.md`. Before each release, back up the application database and record the deployed Git SHA. Roll back code to the previous verified SHA and restore a matching database backup if a migration needs rollback; do not blindly reverse data migrations. The script does not create a VM, edit registrar DNS or Azure network rules, or claim public availability before those checks pass.
