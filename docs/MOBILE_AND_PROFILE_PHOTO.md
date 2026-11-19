# Mobile layout and profile photos

## Responsive behavior

The shared PHP layout now uses a compact header and an accessible, in-flow mobile menu at widths up to `860px`; the desktop navigation remains visible above that breakpoint. The menu exposes Home plus the signed-in Dashboard, Requests, Notifications, Profile, role-gated Admin, account identity and Sign out controls. The toggle has an `aria-expanded` state, visible keyboard focus, Escape-to-close and close-after-navigation behavior.

The landing hero, feature list, dashboard statistics and chart, request detail, filters, and forms use fluid widths and grid tracks with `minmax(0, 1fr)`. At small widths, request, dashboard and administrator records switch from wide tables to readable cards. Wide desktop tables remain inside their own scroll containers. The document does not use page-wide horizontal clipping to conceal layout problems. Inputs use 16px text and 44px or taller controls; focus states, semantic labels, validation alerts and reduced-motion settings are included.

Content-hash query strings on the shared CSS and JavaScript assets make each changed asset URL unique after a release, so clients fetch updated styles and navigation code.

## Profile photo security and lifecycle

- `database/migrations/005_profile_photos.sql` adds a nullable `users.profile_photo_key` column. It leaves existing users and records in place.
- `public/profile-photo.php` checks the authenticated session identity; it does not accept a target user ID. Photo changes and removals require the current session's CSRF token.
- The upload limit is 2 MiB, with JPEG, PNG and WebP input only. The server checks the upload status, actual byte count, `fileinfo` MIME, image header, dimensions (at most 12 million source pixels), and GD decoder result. Browser MIME and original filename are ignored.
- Images are center-cropped, decoded and re-encoded to a 512 by 512 WebP, which strips source metadata. Storage keys contain 24 bytes of randomness and are always `.webp` files.
- The default `/var/lib/campusflow/profile-photos` directory is outside Apache's `/var/www/campusflow/public` document root. PHP owns the directory with mode `0750`; files use mode `0640`. The profile-photo endpoint serves only the current authenticated account's own image, with `nosniff` and private cache headers.
- New image files are written before the database reference is changed. If the database update fails, the new file is deleted; after a successful replacement, the prior file is removed. Removal clears the database reference before unlinking the validated file.
- Initials are the fallback. The avatar appears in profile settings, the dashboard greeting, and the desktop/mobile account navigation.

## Apply and verify the production update

Use the existing running Ubuntu VM only. Do not run the initial provisioning script against it. From Azure Run Command as root, run the redeployment helper using the exact current `main` commit SHA:

```bash
bash scripts/redeploy-existing-azure.sh <40-character-main-commit-sha>
```

The helper refuses an unexpected operating system, repository, local tracked edit, database name, or non-current main SHA. It creates root-only application/configuration and `campusflow_prod` SQL backups under `/root/campusflow-backups/<UTC timestamp>/`. It tests the full revision against a newly-created temporary MariaDB database, removes that temporary test database on exit, applies the additive migration while the current version is still active, updates the checked-out code, configures the private photo directory, validates Apache, and checks the four HTTPS host routes, versioned assets and anonymous photo-endpoint denial. On a failure after changing code, it checks out the previous revision and reloads Apache; it retains backups for review and does not restore or delete production records automatically.

No database password should be printed or copied for this workflow. No DNS, NSG, ZeroSSL or certificate renewal configuration is changed.

## Test and browser evidence

`bash scripts/test.sh` runs PHP syntax checks, security tests, profile photo validation tests, and—when `TEST_DB_*` variables point to a disposable database—authentication, request and profile photo database integration tests. CI applies all migrations to MariaDB and installs GD. The profile-photo integration test creates unique disposable accounts and removes only those test rows and generated files.

For browser evidence, record actual outcomes at 320, 360, 375, 390, 414, 430, 768, 1024 and 1440 CSS pixels. Check page scroll width against the viewport width and inspect Home, Login, Registration, Dashboard, Requests, Request detail, New request, Profile, Notifications and Admin where an administrator test session is available. Also check the mobile menu with keyboard and touch. A browser viewport emulation is not physical iPhone/Android testing.

Keep genuine screenshots of the deployed mobile navigation, landing page, student dashboard/cards, profile photo preview and saved avatar, plus terminal/CI test output. Redact user email addresses, tracking IDs, database names if sensitive, credentials and private configuration. Do not represent an emulated viewport as a real-device capture.

## Verified deployment record

This section must be updated from actual output after redeployment. Record the deployed Git SHA, Azure backup directory, migration 005 result, PHP GD version, Apache configtest/reload, HTTPS host responses, mobile viewport widths and actual profile-photo upload/replace/remove results. Until those observations are recorded, the features are implemented in code but production deployment and browser verification are not confirmed.
