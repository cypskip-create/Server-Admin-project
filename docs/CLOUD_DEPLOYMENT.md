# Azure deployment: fuadsaary.me

This runbook records the deployment to the existing `cyprianServer` VM in `ServerAdmin-Labs`. It was not rebuilt and no additional VM or snapshot was created. On 2026-11-19 Azure reported the VM as Running in South Africa North, using Ubuntu 24.04.5 LTS and a Standard_B2als_v2 size (2 vCPU, 4 GiB RAM). Its attached `cyprianServer-ip` public IP is Standard/Static IPv4 `102.37.155.177`.

## Isolation and installed services

The initial read-only inventory found no existing `/var/www` tree, Apache site configuration, Apache/PHP/MariaDB installation, or web/database listener. UFW was inactive. The deployment therefore added a dedicated CampusFlow vhost and database while preserving the default Apache site. No existing university site or database was replaced.

The VM now runs Apache 2.4.58, PHP 8.3.6, and MariaDB 10.11.14. CampusFlow is deployed at `/var/www/campusflow`; Apache exposes only its `public/` directory. The app uses its own `campusflow_prod` schema and `campusflow_app` runtime user. The runtime account has `SELECT`, `INSERT`, `UPDATE`, and `DELETE` on that schema only; migrations use the local MariaDB administrator.

## Migrations and application configuration

The initial four ordered migrations were applied and recorded in `schema_migrations`:

1. `001_schema.sql` — users, service requests, request history, and baseline schema.
2. `002_login_attempts.sql` — persistent throttling in the `login_attempts` table.
3. `003_sla_notifications.sql` — recommended priority, SLA fields, and database notifications.
4. `004_login_attempt_cleanup.sql` — cleanup index for old login-attempt buckets.

Migration `005_profile_photos.sql` adds a nullable photo storage key to `users`; it is incremental and does not recreate accounts. Run `scripts/redeploy-existing-azure.sh <current-main-SHA>` for an existing installation. It verifies Ubuntu and the expected CampusFlow DB, stores mode-`0600` application/config and MariaDB backups under `/root/campusflow-backups/`, applies and tests the release against a temporary MariaDB database, applies only pending migrations, and rolls the code back to its prior commit if post-deployment checks fail. Review the actual backup directory and command output before recording deployment evidence.

Profile photos require PHP GD. The update script installs `php-gd` only when missing. It stores user images under `/var/lib/campusflow/profile-photos` (outside Apache's document root) with `www-data` ownership and `0750` directory permissions. `.env` contains only the private storage path as `PROFILE_PHOTO_DIR`; database credentials are not part of the release. Apache serves the image only through the authenticated `public/profile-photo.php` endpoint. The release changes Apache's app code and reloads Apache; it does not replace the existing vhosts, alter DNS/NSG rules, stop the VM, or change TLS/ACME files.

The deployed database contains `login_attempts`, `notifications`, `request_status_history`, `schema_migrations`, `service_requests`, and `users`. A generated database password is stored only in root-owned `/var/www/campusflow/.env` (mode `0640`, group `www-data`); it was not printed, screenshotted, or committed.

## Domains and Apache routes

| Hostname | Role | Observed HTTP behavior |
|---|---|---|
| `fuadsaary.me` | Main landing page | `200` |
| `www.fuadsaary.me` | Main-site alias | `301` to the apex |
| `students.fuadsaary.me` | Student portal | `302` to `/login.php` when anonymous; signup page returns `200` |
| `admin.fuadsaary.me` | Administration portal | Anonymous access redirects to login; admin routes enforce authorization |

`apachectl configtest` returned `Syntax OK`. Requests made locally with the matching `Host` header passed, and independent Check-Host probes from several regions returned `200` for the apex, `301` for `www`, and `302` for `students` and `admin`. After TLS setup, all four HTTPS endpoints were checked from the VM and the sandbox browser; anonymous student/admin routes reach the login page.

## DNS and Azure networking

Namecheap Advanced DNS contains:

| Type | Name | Target |
|---|---|---|
| A | `@` | `102.37.155.177` |
| A | `students` | `102.37.155.177` |
| A | `admin` | `102.37.155.177` |
| CNAME | `www` | `fuadsaary.me` |

The existing email-forwarding setting and SPF record were left unchanged; nameservers were not changed. Google Public DNS checks and independent public probes resolved all required names to the Azure IP. See `DNS_CONFIGURATION.md` and `DEPLOYMENT_EVIDENCE.md` for the dated results.

With the user's approval, the existing network security configuration was updated to permit public web access. Existing SSH access remains restricted to its approved source; detailed source addresses and rule identifiers are omitted from this public runbook and should be retained only in private submission evidence. No database port was opened. The VM remained Running during deployment. Apache serves TLS and redirects all four HTTP hostnames to HTTPS; `www` then redirects to the apex.

## Reproduction and verification commands

The same isolated deployment on an unused Ubuntu VM is performed by:

```bash
cd /tmp
git clone --depth 1 https://github.com/cypskip-create/Server-Admin-project.git campusflow-deploy
bash /tmp/campusflow-deploy/scripts/deploy-ubuntu.sh fuadsaary.me
```

The installer refuses to replace an existing CampusFlow directory, schema, DB account, or site. It does not create Azure resources, modify DNS, or change NSG rules. On the deployed VM, inspect without printing `.env` or credentials:

```bash
sudo apachectl configtest
sudo systemctl status apache2 mariadb --no-pager
curl -I -H 'Host: fuadsaary.me' http://127.0.0.1/
curl -I -H 'Host: students.fuadsaary.me' http://127.0.0.1/
curl -I -H 'Host: admin.fuadsaary.me' http://127.0.0.1/
curl -I -H 'Host: www.fuadsaary.me' http://127.0.0.1/
sudo mariadb campusflow_prod -e 'SHOW TABLES; SELECT migration,applied_at FROM schema_migrations ORDER BY migration;'
sudo mariadb campusflow_prod -e 'SHOW COLUMNS FROM login_attempts;'
```

The deployed PHP lint, security checks, and MariaDB integration suite passed using `bash scripts/test.sh`; the GitHub Actions run for commit `2dca2e0` also passed. The test suite covers CSRF and admin authorization, registration and login, hashed passwords, brute-force throttling, priority recommendations, SLA timestamps, request ownership, history and persisted notifications. Profile-photo unit and MariaDB tests cover validation, ownership, private WebP output, replacement, removal and reference consistency. Current results and limitations are kept in `DEPLOYMENT_EVIDENCE.md` and `MOBILE_AND_PROFILE_PHOTO.md`.

## Free ZeroSSL ACME deployment result

The approved free route issued one 90-day SAN certificate for all four names without creating an annual/premium order. Certificate validity and SANs, public HTTPS routes, HTTP-to-HTTPS redirects, Secure session cookies, HSTS, and the root ACME cron and Apache reload hook were verified. EAB values were not written to `ca.conf`. The repository's `scripts/issue-zerossl.sh` uses an ephemeral RAM-backed registration client; see `SSL_CONFIGURATION.md` for checks and the validity mismatch with the rubric's six-month trial wording. A future renewal cycle has not yet occurred.

The initial ACME attempt stopped before registration because of an upstream acme.sh code-shape change. After changing the temporary EAB persistence guard to use no-ops and verifying shell syntax, certificate issuance and all checks passed. The Azure Run Command also reported a trailing carriage-return error after the success message; the repository script was normalized to LF in commit `2dca2e0`, CI passed, and independent read-only checks confirmed the live TLS setup.

Before each release, back up the application database and record the deployed Git SHA. Roll back code to the previous verified SHA and restore a matching database backup if a migration needs rollback; do not blindly reverse data migrations.
