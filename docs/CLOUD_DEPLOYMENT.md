# Azure deployment: fuadsaary.me

This runbook records the deployment to the existing `cyprianServer` VM in `ServerAdmin-Labs`. It was not rebuilt and no additional VM or snapshot was created. On 2026-11-19 Azure reported the VM as Running in South Africa North, using Ubuntu 24.04.5 LTS and a Standard_B2als_v2 size (2 vCPU, 4 GiB RAM). Its attached `cyprianServer-ip` public IP is Standard/Static IPv4 `102.37.155.177`.

## Isolation and installed services

The initial read-only inventory found no existing `/var/www` tree, Apache site configuration, Apache/PHP/MariaDB installation, or web/database listener. UFW was inactive. The deployment therefore added a dedicated CampusFlow vhost and database while preserving the default Apache site. No existing university site or database was replaced.

The VM now runs Apache 2.4.58, PHP 8.3.6, and MariaDB 10.11.14. CampusFlow is deployed at `/var/www/campusflow`; Apache exposes only its `public/` directory. The app uses its own `campusflow_prod` schema and `campusflow_app` runtime user. The runtime account has `SELECT`, `INSERT`, `UPDATE`, and `DELETE` on that schema only; migrations use the local MariaDB administrator.

## Migrations and application configuration

All four ordered migrations were applied and recorded in `schema_migrations`:

1. `001_schema.sql` — users, service requests, request history, and baseline schema.
2. `002_login_attempts.sql` — persistent throttling in the `login_attempts` table.
3. `003_sla_notifications.sql` — recommended priority, SLA fields, and database notifications.
4. `004_login_attempt_cleanup.sql` — cleanup index for old login-attempt buckets.

The deployed database contains `login_attempts`, `notifications`, `request_status_history`, `schema_migrations`, `service_requests`, and `users`. A generated database password is stored only in root-owned `/var/www/campusflow/.env` (mode `0640`, group `www-data`); it was not printed, screenshotted, or committed.

## Domains and Apache routes

| Hostname | Role | Observed HTTP behavior |
|---|---|---|
| `fuadsaary.me` | Main landing page | `200` |
| `www.fuadsaary.me` | Main-site alias | `301` to the apex |
| `students.fuadsaary.me` | Student portal | `302` to `/login.php` when anonymous; signup page returns `200` |
| `admin.fuadsaary.me` | Administration portal | Anonymous access redirects to login; admin routes enforce authorization |

`apachectl configtest` returned `Syntax OK`. Requests made locally with the matching `Host` header passed, and independent Check-Host probes from several regions returned `200` for the apex, `301` for `www`, and `302` for `students` and `admin`. These are HTTP checks; HTTPS has not been issued or enabled yet.

## DNS and Azure networking

Namecheap Advanced DNS contains:

| Type | Name | Target |
|---|---|---|
| A | `@` | `102.37.155.177` |
| A | `students` | `102.37.155.177` |
| A | `admin` | `102.37.155.177` |
| CNAME | `www` | `fuadsaary.me` |

The existing email-forwarding setting and SPF record were left unchanged; nameservers were not changed. Google Public DNS checks and independent public probes resolved all required names to the Azure IP. See `DNS_CONFIGURATION.md` and `DEPLOYMENT_EVIDENCE.md` for the dated results.

With the user's approval, the existing `cyprianServer-nsg` now allows inbound TCP 80 and 443 from Internet. Existing SSH rule `default-allow-ssh` remains restricted to its previous source (`197.232.165.94`); no database port was opened. The VM remains Running. Port 443 is allowed at the NSG, but Apache is not yet serving TLS until the certificate stage completes.

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

The deployed PHP lint, security checks, and MariaDB integration suite passed using `bash scripts/test.sh`; the GitHub Actions run for commit `917a591` also passed. The test suite covers CSRF and admin authorization, registration and login, hashed passwords, brute-force throttling, priority recommendations, SLA timestamps, request ownership, history and persisted notifications. Current results and limitations are kept in `DEPLOYMENT_EVIDENCE.md`.

## Remaining: free ZeroSSL ACME

No ZeroSSL certificate is installed. The approved free route is ZeroSSL ACME with a 90-day SAN certificate for all four names. It does not create an annual/premium order. The account must have a monitored contact email. `scripts/issue-zerossl.sh` installs the certificate, enables the TLS vhost, verifies hostname coverage and redirects, sets secure cookies only after the checks pass, and installs a renewal schedule. Review `SSL_CONFIGURATION.md` before running it. The script uses an ephemeral RAM-backed registration client so EAB values are not written to the ACME config or passed as command-line arguments.

Before each release, back up the application database and record the deployed Git SHA. Roll back code to the previous verified SHA and restore a matching database backup if a migration needs rollback; do not blindly reverse data migrations.
