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
| Mobile-first layouts and accessible navigation | Updated shared header, breakpoint, card layouts, responsive forms, focus states, Escape handling and reduced-motion support | CSS/JavaScript/PHP checks run locally; viewport-by-viewport browser evidence is recorded in `MOBILE_AND_PROFILE_PHOTO.md` only after observed | Pending deployment to the existing Azure VM and responsive checks at all nine requested widths. Browser emulation must not be presented as physical iPhone/Android testing. |
| Private profile photos | Migration 005, authenticated photo endpoint, CSRF, decoded/re-encoded WebP files in private storage, session-based ownership, replacement/removal and initials fallback | Local photo security/validation unit checks passed; MariaDB integration and CI result pending | Pending production migration, GD/storage configuration and a verified upload/replace/remove cycle. |

Additional features demonstrate implementation and do not guarantee marks beyond the assessment rubric. Keep deployed verification separate from code presence and local tests.
