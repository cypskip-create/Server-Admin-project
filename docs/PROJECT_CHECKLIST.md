# Cloud group project completion tracker — 30 marks

Use three separate states. **Implemented** = repository code/docs exist. **Tested** = a named automated/manual test actually passed. **Externally verified** = running service/DNS/certificate was checked on the real provider/VPS. This repository has not been deployed, and no team contribution is assumed.

| Rubric requirement | Marks | Implemented | Tested here/CI | External verification still required |
|---|---:|---|---|---|
| Linux cloud hosting | 2 | Ubuntu VPS deploy script, Apache template, health/rollback guidance | Script syntax and CI only | VPS creation, Apache/PHP/MariaDB runtime, public health check |
| Valid DNS data | 8 | Exact A/AAAA/CNAME and verification guide | No authoritative DNS test | Owned domain, nameservers, correct A records and public propagation |
| Subdomains | 3 | `MAIN_HOST`, `APP_HOST`, `ADMIN_HOST`; host routing and vhosts | PHP syntax only | DNS and HTTP/HTTPS routing for all actual hostnames |
| SSL/HTTPS with ZeroSSL | 6 | ACME EAB issue/install/renew procedure; 90-day ACME info | No certificate test | EAB credentials, issuance, Apache TLS, renewal and public validation |
| Database security | 2 | Password hashing, validation, PDO, CSRF, roles, cookie settings | Auth/DB integration in CI | Repeat on deployed environment and inspect configuration safely |
| Innovation | 7 | Rule-based server priority recommendation, tracked response SLA, status timeline and DB notifications | DB integration implemented; CI result pending | Demonstrate status changes and notification delivery/storage in group demo |
| Team cohesion | 2 | Contribution/workflow template below | Not applicable | Team fills actual roles, commits and review evidence |

## Team record — fill with actual information

| Member | Assigned work | Commit/PR evidence | Contribution notes |
|---|---|---|---|
| Member 1 |  |  |  |
| Member 2 |  |  |  |

Shared workflow: branch from `main`, use descriptive commits, push branches, review the partner's changes, merge after discussion. Do not invent contributions.
