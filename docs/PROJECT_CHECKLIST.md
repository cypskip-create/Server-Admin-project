# Cloud group project completion tracker — 30 marks

Use distinct states. **Implemented** = repository code/docs exist. **Successfully tested** = a named automated/manual test actually passed, with a run date or evidence link. **Externally verified** = actual Azure/DNS/public HTTPS state was checked. Do not mark a deployment item complete from source code, a script, or an intended command alone. Record each real command/result and retain redacted screenshots; never capture `.env`, database passwords, EAB values or private keys.

| Rubric requirement | Marks | Implemented | Tested here/CI | External verification still required |
|---|---:|---|---|---|
| Linux cloud hosting | 2 | Existing Ubuntu 24.04 Azure VM and isolated Apache/MariaDB deployment script | Pending deployment run | Apache/PHP/MariaDB and app health on `cyprianServer`; public reachability |
| Valid DNS data | 8 | Exact records for `fuadsaary.me` in DNS runbook; Azure IP observed as `102.37.155.177` (Standard/Static) | Namecheap shows apex, students and admin A records plus www CNAME; VM resolver returned the target IP for all four on 2026-11-19 | Independent authoritative/public resolver verification and public HTTP response still pending |
| Subdomains | 3 | `MAIN_HOST`, `WWW_HOST`, `STUDENTS_HOST`, `ADMIN_HOST`; host routing and four vhosts | VM resolver returned the configured IP for all four names; Apache routing still pending | Public HTTP/HTTPS routing for all four names |
| SSL/HTTPS with ZeroSSL | 6 | ACME EAB issue/install/renew script; certificate SAN and 90-day renewal guidance | No certificate issued yet | User's EAB credentials, DNS/port 80, certificate issuance, HTTPS, renew schedule |
| Database security | 2 | Dedicated `campusflow_prod`; random secret; least-privilege runtime user; hashing, validation, CSRF, roles and cookie settings | PHP syntax and CSRF/admin authorization unit checks passed; database integration skipped without a test DB | Deployed registration, authentication, secure cookie and grants checks |
| Innovation | 7 | Server-side priority recommendation, SLA due dates/first response, status history and database-backed notifications | Integration test implemented but not run without MariaDB test credentials | Demonstrate request submission, admin status change, SLA and notification in app |
| Team cohesion | 2 | Contribution/workflow template below | Not applicable | Team fills actual roles, commits and review evidence |

## Team record — fill with actual information

| Member | Assigned work | Commit/PR evidence | Contribution notes |
|---|---|---|---|
| Member 1 |  |  |  |
| Member 2 |  |  |  |

Shared workflow: branch from `main`, use descriptive commits, push branches, review the partner's changes, merge after discussion. Do not invent contributions.
