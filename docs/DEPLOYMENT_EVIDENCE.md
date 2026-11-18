# CampusFlow deployment evidence log

This log records observed results for the Azure group project. Implemented code, passing tests, and externally verified infrastructure are separate. Evidence date: 2026-11-19. Do not add `.env`, passwords, EAB values, account keys, or private keys.

## Verified deployment record

| Area | Actual command or observation | Result |
|---|---|---|
| Existing VM | Azure portal: `cyprianServer`, resource group `ServerAdmin-Labs` | Reused existing VM; Running during setup and tests; no VM, snapshot, or other Azure resource created. |
| OS/capacity | Azure Run Command: `/etc/os-release`, `uname -r`, `free -h`, `df -h /` | Ubuntu 24.04.5 LTS, kernel `7.0.0-1017-azure`, Standard_B2als_v2, 2 vCPU/4 GiB RAM; approximately 3.8 GiB RAM and 26 GiB free on the 30 GiB OS disk at inspection. |
| Initial workload inventory | Run Command: service/package/listener/path inventory; `systemctl`, `ss -lntup`, `ls -la /var/www /etc/apache2/sites-enabled`, `ufw status verbose` | No existing `/var/www`, Apache site directory, web/database listener, or Apache/PHP/MariaDB stack. UFW inactive. The default Apache site was retained after deployment; no existing university app or DB was overwritten. |
| Azure public IPv4 | Azure Public IP resource `cyprianServer-ip` configuration | `102.37.155.177`, Standard SKU, Static allocation; no IPv6 configured. |
| Azure NSG | Azure portal `cyprianServer-nsg` inbound rules, after the user's approval | Rule `1010 default-allow-ssh` remains restricted to `197.232.165.94`; added `1020 allow-http-campusflow` TCP/80 Any→Any Allow and `1030 allow-https-campusflow` TCP/443 Any→Any Allow. No database rule added. |
| DNS provider records | Authenticated Namecheap Advanced DNS view | `@`, `students`, and `admin` A records point to `102.37.155.177`; `www` CNAME points to `fuadsaary.me`. Existing email forwarding and SPF record were preserved; nameservers were not changed. |
| Public DNS | Google Public DNS JSON queries for A/CNAME and independent external HTTP probes | All four names resolve to `102.37.155.177`; `www` CNAME resolves to apex. The target and host routing were checked independently from several Check-Host regions. |
| Public HTTP | Check-Host HTTP probes: apex request `58d90ef5k8c6`; `www`, `students`, and `admin` probes | Apex returned HTTP `200` (nodes `ru1`, `ua1`, `us5`); `www` returned `301` (nodes `id1`, `ir3`, `ru1`); `students` returned `302` (nodes `it2`, `md1`, `se1`); `admin` returned `302` (nodes `de1`, `pl1`, `tr1`). These redirects are expected for anonymous portal routes. |
| Web stack | Azure VM service/version checks | Apache `2.4.58`, PHP `8.3.6`, MariaDB `10.11.14`; Apache and MariaDB active. `apachectl configtest` returned `Syntax OK`. |
| Application routing | Azure localhost `curl -I` requests with each virtual-host `Host` header | Main landing `200`; `www` redirects to apex; student portal redirects anonymous users to `/login.php`; admin route requires authentication/authorization; signup page returns `200`. |
| Schema and security | `SHOW TABLES`, migration ledger, schema grants, integration checks on VM | Database `campusflow_prod`; migrations 001–004 applied. Tables include `login_attempts`, `notifications`, `request_status_history`, `schema_migrations`, `service_requests`, and `users`. Runtime DB account limited to CRUD on this schema. Tests confirmed password hashes rather than plaintext. |
| Azure automated suite | `bash scripts/test.sh` on the deployed VM | Passed PHP lint, security unit tests, and MariaDB integration tests, including CSRF failures, role authorization, registration/login, password hashing, duplicate constraints, throttling, server priority recommendations, SLA, ownership, history, status updates and persisted notifications. |
| GitHub Actions | Repository workflow run [44398099946](https://github.com/cypskip-create/Server-Admin-project/actions/runs/44398099946) for commit `917a591` | Passed PHP lint, security and MariaDB integration checks and Bash syntax validation. This is CI evidence, not Kali VM evidence. |
| TLS / HTTPS | Azure NSG and Apache state | TCP/443 is allowed through the NSG, but no certificate has been issued and Apache is not yet serving HTTPS. No HTTPS, redirect, certificate SAN, secure-cookie, HSTS, or renewal claim is made. |

## Rubric status and outstanding evidence

| Rubric requirement | Marks | Implemented | Successfully tested | Externally verified / still needed |
|---|---:|---|---|---|
| Cloud hosting | 2 | Isolated Apache/PHP/MariaDB deployment on the existing Ubuntu VM | VM service health, Apache config, local routes, migrations and app test suite passed | Azure VM screenshot and terminal output should be retained by the student for submission. |
| Valid DNS data | 8 | Registrar records documented in `DNS_CONFIGURATION.md` | Public resolver and multi-region HTTP probe results recorded above | Keep Namecheap screenshot, authoritative NS/A/CNAME output, and evidence that unrelated email records stayed intact. DNS and public HTTP are currently verified. |
| Subdomains | 3 | `students`, `admin`, and `www` vhosts | Local host-header tests and external HTTP probes passed for all four hosts | Capture browser screenshots of each hostname after HTTPS is enabled. |
| ZeroSSL HTTPS | 6 | Free ACME issue/installation/renewal script and runbook cover apex + `www`, `students`, `admin` | Script syntax checked; no certificate run yet | Issue 90-day free ACME certificate; verify all SANs, HTTP→HTTPS, secure cookie, HSTS, certificate validity, Apache reload hook, and cron. Requires an account contact email and successful challenge validation. |
| Database security | 2 | Dedicated schema, random secret, least-privilege runtime DB user, password hashing, validation, CSRF and role checks | Deployed MariaDB integration tests passed; hashes and grants checked | Retain redacted migration/table/grant/password-hash evidence; never show `.env` or plaintext passwords. |
| Innovation | 7 | Server-side priority recommendations, SLA due dates and first response, status history, DB notifications | Integration tests passed on the deployed VM | Capture redacted end-to-end browser evidence for request submission, recommendation/SLA, admin status change, and student notification. |
| Team cohesion | 2 | Shared workflow and contribution record template in `PROJECT_CHECKLIST.md` | Not applicable | Two members must enter actual roles, reviewed work, commit/PR evidence and contribution notes; no evidence was supplied to fabricate. |

## Screenshot and command evidence to retain

- Azure VM Overview, showing the existing VM and Running state.
- Azure NSG inbound rules, showing only the approved web rules plus unchanged restricted SSH.
- Namecheap Advanced DNS records and the unchanged mail/SPF view.
- Redacted terminal output: OS/version, `apachectl configtest`, service status, public DNS checks, host-header HTTP responses, migrations/tables, grants, and the test-suite summary.
- Once TLS is installed: `openssl x509` subject/issuer/dates/SANs, HTTPS responses, HTTP redirects, `Set-Cookie` Secure flag, HSTS header, and renewal schedule.
- Browser evidence for main landing, student signup/login/dashboard, request tracking/notifications, admin authorization/status workflow.
- Separate Kali VM screenshots for CAT 2; Azure screenshots do not count as Kali VM evidence.
