# Cloud group project completion tracker — 30 marks

**Implemented** means code/configuration exists. **Successfully tested** means a named automated or manual test passed. **Externally verified** means the actual Azure, DNS, or public service was checked. Retain genuine, redacted screenshots and terminal output. Never include `.env`, passwords, EAB values, API keys, or private keys.

| Rubric requirement | Marks | Implemented | Successfully tested | External verification / remaining evidence |
|---|---:|---|---|---|
| Linux cloud hosting | 2 | Existing Ubuntu 24.04.5 Azure VM; isolated Apache/PHP/MariaDB app and dedicated database | Apache config, active services, local routes, all migrations, and full deployed PHP/MariaDB suite passed | Azure VM overview and redacted terminal screenshots should be retained for submission. Public HTTP routes passed external probes. |
| Valid DNS data | 8 | Namecheap records documented in `DNS_CONFIGURATION.md`; Azure IP is Standard/Static `102.37.155.177` | Google Public DNS A/CNAME queries resolved the expected records; multi-region public HTTP probes reached the VM | Save Namecheap record screenshot and authoritative `dig NS`, A and CNAME output. Apex and subdomains now point to CampusFlow; existing SPF/email settings were preserved. |
| Subdomains | 3 | Apache vhosts and config cover apex, `www`, `students`, and `admin` | Local host-header checks and independent public HTTP checks passed: apex `200`, `www` `301`, student/admin anonymous `302` | Capture browser screenshots of each hostname once HTTPS is enabled. |
| ZeroSSL HTTPS | 6 | Free 90-day ZeroSSL ACME issue/installation/renewal runbook and script cover all four names | Script Bash syntax check passes; no certificate has been requested or installed | Needs account contact email, issuance, SAN/validity verification, HTTPS redirects, Secure cookie/HSTS, and verified renewal schedule. Do not claim HTTPS complete yet. |
| Database security | 2 | Dedicated `campusflow_prod`, random secret, least-privilege runtime user, hashing, validation, CSRF checks and authorization | Deployed MariaDB integration suite passed registration/login for both student account patterns, hashed password checks, throttle, CSRF and admin authorization | Retain redacted migration/table/grant evidence; do not expose `.env` or password values. |
| Innovation | 7 | Server-side priority recommendations, response SLA fields, request history, and database-backed status notifications | Deployed integration suite passed priority, SLA, ownership, history, status update and persisted notification checks | Capture app browser evidence showing student submission, recommended priority/SLA, admin update and student's notification. |
| Team cohesion | 2 | Shared-workflow guidance and honest contribution table below | Not applicable | Both members must enter actual responsibilities, reviewed commits/PRs and contribution notes; no team-contribution evidence was supplied. |

## Team record — complete with actual information

| Member | Assigned work | Reviewed commit/PR evidence | Contribution notes |
|---|---|---|---|
| Member 1 |  |  |  |
| Member 2 |  |  |  |

Shared workflow: branch from `main`, make focused commits, review the partner's changes, and record the real discussion and review. Do not invent contributions.
