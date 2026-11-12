# Cloud VPS deployment

Use a supported Ubuntu LTS VPS and a real domain you control. Set `APP_URL`, database credentials, and `SESSION_SECURE=true` in an untracked `.env`. Restrict MariaDB to localhost, use a dedicated DB user, keep `.env` outside version control, and point Apache's document root at `public/`.

1. Install Apache, PHP 8.2+, `php-mysql`, `php-mbstring`, and MariaDB; enable services at boot.
2. Clone the repository into `/var/www/campusflow`; configure `.env`; import `database/migrations/001_schema.sql`.
3. Set source/config permissions to root-owned and readable by Apache; allow writes only to a dedicated log directory if configured.
4. Copy the example vhosts in `../deploy/apache/`, substitute your actual hostnames and paths, enable `ssl` and `headers`, enable sites, run `apachectl configtest`, reload Apache. The application subdomain routes its root to login/dashboard, and the admin subdomain routes to the role-checked admin page.
5. Verify DNS first, provision TLS per `SSL_CONFIGURATION.md`, enable redirect vhost and set secure cookies.
6. Health-check with `curl -I https://your-domain/`; inspect `systemctl status apache2 mariadb`, Apache logs, and database connectivity. Back up DB before each release; rollback by restoring prior code and database backup.

No deployment, domain, DNS, or certificate is claimed here; these are preparation steps.
