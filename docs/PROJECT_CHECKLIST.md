# Cloud group project completion tracker — 30 marks

**Implemented** means code/configuration exists. **Successfully tested** means a named automated or manual test passed. **Externally verified** means the actual Azure, DNS, or public service was checked. Retain genuine, redacted screenshots and terminal output. Never include `.env`, passwords, EAB values, API keys, or private keys.

| Rubric requirement | Marks | Implemented | Successfully tested | External verification / remaining evidence |
|---|---:|---|---|---|
| Linux cloud hosting | 2 | Existing Ubuntu 24.04.5 Azure VM; isolated Apache/PHP/MariaDB app and dedicated database | Apache config, active services, local routes, all migrations, and full deployed PHP/MariaDB suite passed | Azure hosting and public site are verified. Retain Azure VM overview and redacted terminal screenshots for submission. |
| Valid DNS data | 8 | Namecheap records documented in `DNS_CONFIGURATION.md`; Azure IP is Standard/Static `102.37.155.177` | Google Public DNS A/CNAME queries resolved the expected records; multi-region public probes and HTTPS browser smoke checks reached the VM | Apex and subdomains resolve to CampusFlow; existing SPF/email settings were preserved. Retain Namecheap record screenshot and authoritative `dig NS`, A and CNAME output. |
| Subdomains | 3 | Apache vhosts and config cover apex, `www`, `students`, and `admin` | Local host-header checks, public HTTPS checks, and HTTP redirects passed: apex `200`, `www` redirects to apex, student/admin anonymous routes `302` to login | External HTTPS is verified. Retain browser screenshots of each hostname. |
| ZeroSSL HTTPS | 6 | Free 90-day ZeroSSL ACME issue/installation/renewal script covers all four names | Certificate validity/SANs, HTTPS responses, HTTPS redirects, Secure cookie, HSTS, renewal cron, Apache reload hook, and non-persistence of EAB values passed | HTTPS is externally verified. The free ACME certificate lasts 90 days; the rubric's six-month trial entitlement was not observed. A future renewal cycle has not yet occurred. |
| Database security | 2 | Dedicated `campusflow_prod`, random secret, least-privilege runtime user, hashing, validation, CSRF checks and authorization | Deployed MariaDB integration suite passed registration/login for both student account patterns, hashed password checks, throttle, CSRF and admin authorization | Retain redacted migration/table/grant evidence; do not expose `.env` or password values. |
| Innovation | 7 | Server-side priority recommendations, response SLA fields, request history, and database-backed status notifications | Deployed integration suite passed priority, SLA, ownership, history, status update and persisted notification checks | Capture app browser evidence showing student submission, recommended priority/SLA, admin update and student's notification. |
| Team cohesion | 2 | Shared-workflow guidance and honest contribution table below | Not applicable | Both members must enter actual responsibilities, reviewed commits/PRs and contribution notes; no team-contribution evidence was supplied. |

## Team record — complete with actual information

| Member | Assigned work | Reviewed commit/PR evidence | Contribution notes |
|---|---|---|---|
| Member 1 |  |  |  |
| Member 2 |  |  |  |

Shared workflow: branch from `main`, make focused commits, review the partner's changes, and record the real discussion and review. Do not invent contributions.

## Additional work beyond the 30-mark rubric

| Feature | Implemented in code | Automated test | Production/browser verification |
|---|---|---|---|
| Mobile-first layouts and accessible navigation | Shared header, breakpoint/card layouts, responsive forms, focus states, Escape handling and reduced-motion support | PHP lint, security tests, profile-photo tests, database integration, JavaScript syntax and GitHub Actions passed | Deployed on the existing VM. Public landing and signup pages were emulated at 320, 360, 375, 390, 414, 430, 768, 1024 and 1440 CSS px with no horizontal overflow; menu open/Escape behavior passed. Empty-state screenshots are in `docs/evidence/`. This is not physical device testing and authenticated views were not browser-tested. |
| Private profile photos | Migration 005, authenticated photo endpoint, CSRF, decoded/re-encoded WebP files in private storage, session-based ownership, replacement/removal, cache-version invalidation and initials fallback | Unit checks passed locally; valid-image transforms require GD, and database integration checks require MariaDB. New CI / VM preflight result pending this release. | Current VM inspection found two stored photos and no app-scoped upload-limit configuration. The fix is not production-verified until the new release passes its preflight/deploy; no existing photo has been changed. |
| Administrator portal and request operations | Dedicated role-checked login, current-database role enforcement, root-only existing-user promotion command, database-backed dashboard, filters, request detail/history, status and student response updates with transaction-bound notifications | PHP lint and unit security checks passed locally; MariaDB integration and GitHub Actions run pending. Tests include admin/student credential separation, promotion, permissions, request history, SLA and notification persistence. | Current live database has four students and no admin accounts. No administrator role was changed yet. Full student→admin browser flow remains unverified until deployment and approved test credentials/data are available. |

Additional features demonstrate implementation and do not guarantee marks beyond the assessment rubric. Keep deployed verification separate from code presence and local tests.
