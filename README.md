# CampusFlow — Student Services Portal

A PHP 8.2+/MariaDB student service portal for the Server Administration CAT 2 and group project. Apache serves only `public/`; PHP uses PDO, prepared statements, password hashing, CSRF tokens and role-protected administration.

## Run locally

1. Install PHP with `pdo_mysql` and `mbstring`, MariaDB, and Apache (Kali instructions in `docs/KALI_SETUP.md`).
2. Create database `CAT2`, copy `.env.example` to `.env` and set credentials, then apply all migrations with `php scripts/migrate.php` using a migration-capable database account. This applies migrations `001`–`004` exactly once.
3. Set Apache `DocumentRoot` to the `public/` directory. For a quick PHP development server from repository root: `php -S 127.0.0.1:8080 -t public` (requires MariaDB and `.env`).
4. Register `studentCNSA`, `studentCNSB`, `studentMiguel`, and `studentHaggai` through the real signup page as needed; no users are pre-seeded.

The first account is not automatically an administrator. To grant admin access on a trusted local DB, run `UPDATE users SET role='admin' WHERE username='YOUR_ACTUAL_ADMIN_USERNAME';` after registering. Restrict this operation to trusted administrators.

## Features

- Student registration/sign-in, session ID rotation, logout, non-empty CSRF token checks, stricter server-side validation and persistent IP/identity login throttling.
- Private dashboard with status chart, profile/password editing, service request submission/detail pages, ownership checks, unique tracking IDs, server-side deterministic priority recommendations, response SLA tracking and a status history timeline.
- Role-gated admin request queue; status changes update the response SLA, append history and create persistent in-app notifications.
- Responsive interface and deployment/evidence guidance in `docs/`.

## Architecture and assessment

`config/` bootstraps sessions and PDO; `database/migrations/` has schema; `public/` is the Apache web root; `docs/` holds Kali, cloud, DNS, SSL, and assessment guidance. Required Linux host packages: Apache, MariaDB server/client, Git, and PHP runtime integration (`libapache2-mod-php` or PHP-FPM); PHP modules such as `php-mysql` are excluded from that list. PHP executes server-side request logic, validates inputs, performs database operations and returns dynamic HTML. Git records changes, GitHub hosts collaboration/remotes, Linux VM hosting runs a continuously managed web server. This is traditional server hosting; serverless platforms run code on managed, event-driven infrastructure without the team administering the underlying server.

## Azure domain and deployment

The domain is `fuadsaary.me`: the apex serves the landing page, `www` redirects to the apex, `students` serves the student app, and `admin` serves the role-checked admin portal. The application runs on the existing Azure VM with a separate `campusflow_prod` database; CAT2 uses its own `CAT2` database. Namecheap A/CNAME records resolve publicly to the Azure static IP and all four virtual hosts passed public HTTPS smoke checks. The Azure NSG allows approved HTTP and HTTPS ports, while SSH remains source-restricted. See `docs/DNS_CONFIGURATION.md`, `docs/CLOUD_DEPLOYMENT.md`, and `docs/SSL_CONFIGURATION.md` for verified results and remaining assessment evidence.

The deployed TLS certificate uses free ZeroSSL ACME, covers the apex plus `www`, `students`, and `admin`, and expires on 2027-02-16. Automated renewal and Apache reload are configured. The rubric's “six-month trial” wording does not match this free ACME certificate's 90-day validity; do not claim six months. `docs/DEPLOYMENT_EVIDENCE.md`, `docs/PROJECT_CHECKLIST.md`, and `docs/CAT2_CHECKLIST.md` separate implemented code, passed tests, externally verified deployment, and student-captured evidence.

## Automated checks

Run `bash scripts/test.sh`. It performs PHP syntax checks, CSRF/authorization unit checks, then MariaDB integration checks when `TEST_DB_HOST`, `TEST_DB_NAME`, and `TEST_DB_USER` are configured. GitHub Actions provisions MariaDB and runs the full suite. Local syntax/unit results do not verify a VM, VPS, DNS or HTTPS deployment.

