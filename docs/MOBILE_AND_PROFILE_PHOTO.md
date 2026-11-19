# Mobile layout and profile photos

## Responsive behavior

The shared PHP layout now uses a compact header and an accessible, in-flow mobile menu at widths up to `860px`; the desktop navigation remains visible above that breakpoint. The menu exposes Home plus the signed-in Dashboard, Requests, Notifications, Profile, role-gated Admin, account identity and Sign out controls. The toggle has an `aria-expanded` state, visible keyboard focus, Escape-to-close and close-after-navigation behavior.

The landing hero, feature list, dashboard statistics and chart, request detail, filters, and forms use fluid widths and grid tracks with `minmax(0, 1fr)`. At small widths, request, dashboard and administrator records switch from wide tables to readable cards. Wide desktop tables remain inside their own scroll containers. The document does not use page-wide horizontal clipping to conceal layout problems. Inputs use 16px text and 44px or taller controls; focus states, semantic labels, validation alerts and reduced-motion settings are included.

Content-hash query strings on the shared CSS and JavaScript assets make each changed asset URL unique after a release, so clients fetch updated styles and navigation code.

## Profile photo security and lifecycle

- `database/migrations/005_profile_photos.sql` adds a nullable `users.profile_photo_key` column. It leaves existing users and records in place.
- `public/profile-photo.php` checks the authenticated session identity; it does not accept a target user ID. Photo changes and removals require the current session's CSRF token.
- The application limit is 5 MiB, with JPEG, PNG and WebP input only. The server checks the upload status, actual byte count, `fileinfo` MIME, image header, dimensions (at most 12 million source pixels), and GD decoder result. The browser and app-scoped Apache limits allow the multipart request overhead; the PHP application still rejects image files over 5 MiB. Browser MIME and original filename are ignored.
- Images are center-cropped, decoded and re-encoded to a 512 by 512 WebP, which strips source metadata. Storage keys contain 24 bytes of randomness and are always `.webp` files.
- The default `/var/lib/campusflow/profile-photos` directory is outside Apache's `/var/www/campusflow/public` document root. PHP owns the directory with mode `0750`; files use mode `0640`. The profile-photo endpoint serves only the current authenticated account's own image, with `nosniff` and private cache headers.
- New image files are written before the database reference is changed. If the database update fails, the new file is deleted; after a successful replacement, the prior file is removed. Removal clears the database reference before unlinking the validated file.
- Initials are the fallback. The avatar appears in profile settings, the dashboard greeting, and the desktop/mobile account navigation.
- Photo selection and profile details use separate forms and explicit actions. Choosing a file previews the selected image and enables **Save new photo**; saving other profile fields cannot display a false photo-success message. Each saved image has a new opaque URL version and stale photo URLs are rejected, while private responses require cache revalidation.

## Administrator portal implementation

`https://admin.fuadsaary.me` routes to a dedicated administrator sign-in. Public administrator registration is not provided. The admin login checks the existing `users` table and its persisted `admin` role, reuses the shared CSRF and rate-limit protections, and regenerates the session on success. Protected admin pages recheck the role in MariaDB so a stale session cannot retain access after demotion. `scripts/promote-admin.php` promotes one existing username only when run locally on the server as root and after the exact typed confirmation; it does not set a password.

The dashboard queries the live counts for students, request statuses, urgent work and response deadlines. Administrators can search/filter requests, view a detail page and status history, and post a status plus a student-visible response. The status, timeline row and notification are committed together; ordinary students remain limited to their own request IDs. CI and the Azure disposable-database preflight passed their MariaDB integration suites. User approval was obtained to promote existing account `cypskip`; the server-side command succeeded and a separate MariaDB read-back returned role `admin`. The deployed admin login route returned HTTP 200 with a Secure session cookie, and anonymous dashboard access redirected with HTTP 302. A password-authenticated browser session and complete live student-to-admin workflow are still unverified; no production request or photo was changed for testing.

## Apply and verify the production update

Use the existing running Ubuntu VM only. Do not run the initial provisioning script against it. From Azure Run Command as root, run the redeployment helper using the exact current `main` commit SHA:

```bash
bash scripts/redeploy-existing-azure.sh <40-character-main-commit-sha>
```

The helper refuses an unexpected operating system, repository, local tracked edit, database name, or non-current main SHA. It creates root-only application/configuration and `campusflow_prod` SQL backups under `/root/campusflow-backups/<UTC timestamp>/`. It tests the full revision against a newly-created temporary MariaDB database, removes that temporary test database on exit, applies the additive migration while the current version is still active, updates the checked-out code, configures the private photo directory, validates Apache, and checks the four HTTPS host routes, versioned assets and anonymous photo-endpoint denial. On a failure after changing code, it checks out the previous revision and reloads Apache; it retains backups for review and does not restore or delete production records automatically.

No database password should be printed or copied for this workflow. No DNS, NSG, ZeroSSL or certificate renewal configuration is changed.

## Test and browser evidence

`bash scripts/test.sh` runs PHP syntax checks, security tests, profile photo validation tests, and—when `TEST_DB_*` variables point to a disposable database—authentication, request and profile photo database integration tests. CI applies all migrations to MariaDB and installs GD. The profile-photo integration test creates unique disposable accounts and removes only those test rows and generated files.

On 2026-11-19, the deployed public landing page and student signup page were checked in the sandbox browser at 320, 360, 375, 390, 414, 430, 768, 1024 and 1440 CSS pixels. Both pages had `scrollWidth === clientWidth` at each width. At 320px, after the CSS correction, both measured 305px document and client widths (the browser's available content width after its vertical scrollbar), with no horizontal overflow. The signup form used 16px input text and a 46px submit button. The public landing-page menu opened with `aria-expanded=true` and Escape closed it back to `false`. The checks used viewport emulation and are not physical iPhone/Android testing.

Genuine, empty-state production screenshots are retained as [mobile signup at 390px](evidence/mobile-signup-390.jpg) and [desktop landing page at 1440px](evidence/desktop-landing-1440.jpg). They contain no personal information. The mobile signup screenshot shows the complete registration form and its touch-sized submit control; the desktop capture shows the landing hero and navigation.

The landing and signup pages were the only production pages measured across the full width matrix. Dashboard, request creation/tracking, notifications, profile-photo preview/upload and administrator UI were not browser-tested with a production account. The deployment smoke test verified anonymous photo-endpoint denial, and automated integration tests exercised photo validation, ownership, replacement, removal and database/file consistency against disposable test data. No production account or personal photo was modified.

## Verified deployment record

| Check | Observed result on 2026-11-19 |
|---|---|
| Deployed revision | Latest release `cf3f25b4c0cb735131614eeed0e8ec4c6e100b8d` on the existing Ubuntu 24.04.5 `cyprianServer` VM. |
| Safe backups | Latest redeploy retained root-only application/configuration and database backups under `/root/campusflow-backups/20261119T090807Z/`; earlier release backups remain under `/root/campusflow-backups/20261119T071642Z/` and `/root/campusflow-backups/20261119T072809Z/`. |
| Migration and storage | `005_profile_photos.sql` was applied on the first release deployment and reported already applied on the repeat deployment. Private storage is `/var/lib/campusflow/profile-photos`, outside the Apache document root. PHP GD was installed when needed. |
| Preflight and automated tests | The latest deployment helper ran the complete PHP/security/MariaDB/profile-photo/admin-request integration suite on a temporary database on the VM and passed. GitHub Actions run [44493124816](https://github.com/cypskip-create/Server-Admin-project/actions/runs/44493124816) for the deployed revision completed successfully, including migrations, PHP checks/tests and Bash syntax. Earlier CI run [44479615710](https://github.com/cypskip-create/Server-Admin-project/actions/runs/44479615710) covers the preceding release. |
| Apache and public routes | `apachectl configtest` returned `Syntax OK`; Apache remained active after reload. The helper's HTTPS checks passed for apex (200), www (redirect), students (anonymous login redirect) and admin (anonymous login redirect); HTTP-to-HTTPS and versioned-asset checks passed. Anonymous access to `profile-photo.php` returned 401. |
| Latest verification and CI | GitHub Actions [44493124816](https://github.com/cypskip-create/Server-Admin-project/actions/runs/44493124816) and the Azure preflight passed for `cf3f25b4c0cb735131614eeed0e8ec4c6e100b8d`. Final Azure checks confirmed PHP upload limits 6M/8M, Apache `Syntax OK`, admin login HTTP 200 with Secure cookie, anonymous admin dashboard HTTP 302, and migrations 001–005. |
| Administrator role | `cypskip` was promoted through the root-only CLI after user approval; a separate production MariaDB query returned `cypskip admin`. No password was changed. Interactive admin login and live request workflow still need browser verification. |
| Responsive browser checks | Landing and signup had no horizontal overflow at all nine widths listed above. Escape closed the mobile menu. Screenshots are linked above. |
| Production photo lifecycle | Not manually performed in a production account. Upload, replacement and removal passed automated integration tests using disposable accounts/files; no real user photo was used. |

The deployment changed application code, installed GD if absent, retained the additive photo schema and configured the private photo directory. It did not change DNS, NSG, ZeroSSL or renewal configuration, and did not restore, delete or overwrite production records. Automated tests ran against temporary test data, not the live production request or photo records. The password-authenticated dashboard/request workflow, real-user photo replacement, and a future certificate renewal remain unverified.
