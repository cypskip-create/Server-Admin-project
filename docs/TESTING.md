# Automated and manual verification

Run `bash scripts/test.sh` from a Linux shell. It runs PHP lint, CSRF/admin-authorization checks, photo validation and MariaDB integration tests. Set `TEST_DB_HOST`, `TEST_DB_NAME`, `TEST_DB_USER`, and `TEST_DB_PASS` against a disposable test database and apply schema migrations before the integration run. GitHub Actions provisions MariaDB 11, applies migrations 001–005, checks Bash syntax for the Kali/deploy/ZeroSSL scripts, and runs the full suite.

The latest completed GitHub Actions run is [44414494877](https://github.com/cypskip-create/Server-Admin-project/actions/runs/44414494877) for commit `2dca2e0`; it passed PHP syntax checks, security checks, MariaDB integration tests, and Bash syntax checks. Azure Run Command also verified the EAB-guarded temporary acme.sh copy passes `bash -n` before certificate issuance.

The local Windows PHP run passed lint and security tests. MariaDB integration tests reported `SKIP` because no local test database was configured. Git Bash failed to initialize its MSYS runtime in this Windows session, so it did not produce a local Bash syntax result. Azure VM tests and CI are the available full Linux/MariaDB results; none of these proves a Kali VM session.

Use a separate disposable CAT2 database/browser for manual checks. Do not use production personal data.

## Responsive UI and profile photos

The automated suite includes `tests/profile_photo_unit.php` for authentication identity validation, CSRF token failures, unsupported and fake content, executable payloads, upload size limits, public-root refusal, filename/path traversal and raster-to-WebP output. `tests/profile_photo_integration.php` adds database ownership checks, successful upload, replacement, removal, and reference consistency. CI installs GD, applies migrations 001–005, and supplies a disposable MariaDB database. Run `bash scripts/test.sh` with `TEST_DB_HOST`, `TEST_DB_NAME`, `TEST_DB_USER`, and `TEST_DB_PASS` set to a disposable schema.

The local Windows verification for this change passed `php scripts/lint.php`, `tests/security_test.php`, `tests/profile_photo_unit.php` (PHP 8.4 with GD and fileinfo explicitly loaded), and `node --check public/assets/app.js`. Local MariaDB integration tests were skipped because no test DB is configured and the installed PHP CLI does not load PDO MySQL. Git Bash syntax checks could not start because the local MSYS runtime returned an access-denied initialization error; CI's Linux Bash syntax check remains the authoritative shell check.

Responsive browser measurements must be recorded separately from this automated suite. Use the exact requested viewports (320, 360, 375, 390, 414, 430, 768, 1024, 1440), capture page `scrollWidth` versus `innerWidth`, inspect all listed routes, and record when a route could not be viewed because no appropriately privileged account was available. Emulation does not establish physical-device behavior. See `MOBILE_AND_PROFILE_PHOTO.md` for the security model and production update flow.

1. Apply migrations 001–005. Register `studentCNSA` and `studentCNSB`; confirm distinct records and non-plaintext password hashes.
2. Try duplicate username/email, malformed email, mismatched passwords and a short password. Confirm validation blocks each.
3. Test valid login for both accounts, invalid login and repeated-failure throttling.
4. Confirm anonymous dashboard, request, profile and request-form visits redirect to sign-in. Sign out and confirm session access is removed.
5. Submit a request. Inspect the row, tracking ID, response estimate, initial status and history. Confirm it appears in the owner's request list and detail timeline.
6. As the other student, open the first student's request URL; expect 404 without details. Test invalid category, too-long title, short description, invalid priority and missing/invalid CSRF tokens.
7. Update profile and password. Confirm duplicate email/student number is rejected and only the new password works.
8. As a student, request `/admin.php`; expect 403. Grant a test account admin role using trusted SQL, change a status, and confirm the timeline records it. Confirm a student cannot change it.
9. Check mobile layout, keyboard focus, and Apache/PHP logs. Record actual outcomes; this checklist is not proof that these checks have run.
