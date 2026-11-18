# Automated and manual verification

Run `bash scripts/test.sh` from a Linux shell. It runs PHP lint, CSRF/admin-authorization checks and MariaDB integration tests. Set `TEST_DB_HOST`, `TEST_DB_NAME`, `TEST_DB_USER`, and `TEST_DB_PASS` against a disposable test database and apply schema migrations before the integration run. GitHub Actions provisions MariaDB 11, applies migrations 001–004, checks Bash syntax for the Kali/deploy/ZeroSSL scripts, and runs the full suite.

The latest completed GitHub Actions run is [44414494877](https://github.com/cypskip-create/Server-Admin-project/actions/runs/44414494877) for commit `2dca2e0`; it passed PHP syntax checks, security checks, MariaDB integration tests, and Bash syntax checks. Azure Run Command also verified the EAB-guarded temporary acme.sh copy passes `bash -n` before certificate issuance.

The local Windows PHP run passed lint and security tests. MariaDB integration tests reported `SKIP` because no local test database was configured. Git Bash failed to initialize its MSYS runtime in this Windows session, so it did not produce a local Bash syntax result. Azure VM tests and CI are the available full Linux/MariaDB results; none of these proves a Kali VM session.

Use a separate disposable CAT2 database/browser for manual checks. Do not use production personal data.

1. Apply migrations 001–004. Register `studentCNSA` and `studentCNSB`; confirm distinct records and non-plaintext password hashes.
2. Try duplicate username/email, malformed email, mismatched passwords and a short password. Confirm validation blocks each.
3. Test valid login for both accounts, invalid login and repeated-failure throttling.
4. Confirm anonymous dashboard, request, profile and request-form visits redirect to sign-in. Sign out and confirm session access is removed.
5. Submit a request. Inspect the row, tracking ID, response estimate, initial status and history. Confirm it appears in the owner's request list and detail timeline.
6. As the other student, open the first student's request URL; expect 404 without details. Test invalid category, too-long title, short description, invalid priority and missing/invalid CSRF tokens.
7. Update profile and password. Confirm duplicate email/student number is rejected and only the new password works.
8. As a student, request `/admin.php`; expect 403. Grant a test account admin role using trusted SQL, change a status, and confirm the timeline records it. Confirm a student cannot change it.
9. Check mobile layout, keyboard focus, and Apache/PHP logs. Record actual outcomes; this checklist is not proof that these checks have run.

