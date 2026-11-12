# Manual end-to-end verification

Use `bash scripts/test.sh` for PHP lint, CSRF/role unit checks, and MariaDB integration tests. Configure `TEST_DB_HOST`, `TEST_DB_NAME`, `TEST_DB_USER`, and `TEST_DB_PASS`; apply schema with `php scripts/migrate.php` first. GitHub Actions runs these against an isolated MariaDB service. Use a separate disposable CAT2 database and browser for the manual checks below. Do not use production personal data.

Current result: GitHub Actions **passed** for commit `368c4f6` on PHP 8.2 and MariaDB 11. This confirms code and integration behavior in CI; it does not confirm Kali VM screenshots, public hosting, DNS or HTTPS.

1. Apply migrations 001–004. Register `studentCNSA` and `studentCNSB`; confirm distinct real records and non-plaintext password hashes.
2. Retry a duplicate username/email, malformed email, mismatched passwords and a short password. Confirm validation blocks each.
3. Test valid login, invalid login and repeated-failure throttling.
4. Confirm anonymous visits to dashboard, requests, profile and request form redirect to sign-in. Sign out and confirm session access is removed.
5. Submit a request. Inspect its row, tracking ID, response estimate, initial status and history row. Confirm it appears in the owner's request list and detail timeline.
6. As the other student, open the first student's request URL; expect 404 without details. Test invalid category, too-long title, short description, invalid priority and missing/invalid CSRF token.
7. Update profile and password. Confirm duplicate email/student number is rejected and only the new password works.
8. As a student, request `/admin.php`; expect 403. Grant a test account admin role using trusted SQL, change a status, and confirm the timeline records it. Confirm a student cannot change it.
9. Check mobile layout, keyboard focus, and Apache/PHP logs. Record actual outcomes; this checklist is not proof that these checks have run.
