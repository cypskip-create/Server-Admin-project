# Ubuntu VPS deployment

Use a supported Ubuntu LTS VPS and a real domain you control. `bash scripts/deploy-ubuntu.sh example.edu` provisions Apache, PHP, MariaDB, a least-privilege database account, app config and an HTTP virtual host. It refuses to overwrite an existing `.env`. Run it from a sudo-enabled, non-root account with network access. Review the script before running it.

The script configures `example.edu`, `www.example.edu`, `app.example.edu` and `admin.example.edu`. Replace the example with the actual domain. `app.` routes to sign-in/dashboard; `admin.` routes to the role-checked admin page.

## Database and app configuration

The deploy script runs `scripts/migrate.php`, which applies the ordered files in `database/migrations/` and records them in `schema_migrations`:

1. `001_schema.sql`: users, service requests and status history.
2. `002_login_attempts.sql`: persistent login throttling.
3. `003_sla_notifications.sql`: server priority recommendation, response SLA timestamps and database-backed notifications.
4. `004_login_attempt_cleanup.sql`: index used to expire old rate-limit rows safely.

For manual operations, configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` for the migration runner, then run `php scripts/migrate.php` as a database account with DDL access. The runtime MariaDB user should have only SELECT/INSERT/UPDATE/DELETE after migrations. The migration account needs DDL access.

`APP_ENV=production` and configured host values are written into the untracked `.env`. `SESSION_SECURE` remains false while the deployment is HTTP-only; the ZeroSSL issuance script changes it to true only after installing TLS. If enabling HTTPS manually, make that change after TLS works. The deployed `.env` is owned by root and group-readable by Apache; the public document root is the `public/` subdirectory.

## Verify and recover

```bash
sudo apachectl configtest
sudo systemctl status apache2 mariadb --no-pager
curl -I -H 'Host: example.edu' http://127.0.0.1/
curl -I -H 'Host: app.example.edu' http://127.0.0.1/login.php
sudo mariadb CAT2 -e 'SHOW TABLES; SELECT migration,applied_at FROM schema_migrations ORDER BY migration;'
sudo tail -n 100 /var/log/apache2/campusflow-error.log
```

After public DNS and HTTPS are set up, use the HTTPS checks in `SSL_CONFIGURATION.md`. Before each release, back up the database and record the deployed Git SHA. Roll back code to the previous verified SHA and restore the matching database backup if a migration needs rollback; do not blindly reverse data migrations. The automated deploy script does not claim to create a VPS, configure external DNS, or verify public availability.
