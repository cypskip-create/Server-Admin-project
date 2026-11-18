# CampusFlow deployment evidence log

This log records observations made during the current deployment. `Implemented`, `tested`, and `externally verified` are separate states. Update it only with direct output or screenshots; never include `.env`, passwords, EAB values, private keys, or account contact details.

## Verified so far

| Step | Actual command or observation | Result |
|---|---|---|
| Existing Azure VM | Azure portal VM `cyprianServer`, resource group `ServerAdmin-Labs` | Existing VM reused; Azure reported Running during this work. No VM, snapshot, or other billable resource was created. |
| VM image and capacity | Read-only Azure Run Command: `/etc/os-release`, `uname -r`, `uptime`, `free -h`, `df -h /` | Ubuntu 24.04.5 LTS, kernel `7.0.0-1017-azure`, about 3.8 GiB RAM, about 26 GiB free on the 30 GiB root disk at inspection. |
| Existing workloads | Read-only package/service/listener and path inventory, including `systemctl list-units`, `ss -lntup`, `ls -la /var/www /etc/apache2/sites-enabled`, and `ufw status verbose` | No existing `/var/www`, Apache site directory, web/database listener, or installed Apache/PHP/MariaDB database stack was found. UFW was inactive. The NSG's current inbound rules still restrict access to SSH; HTTP/HTTPS are closed. |
| Azure public IP | Azure Public IP resource `cyprianServer-ip` configuration blade | `102.37.155.177`, Standard SKU, Static IPv4 allocation; no IPv6 was configured. |
| Namecheap DNS | Authenticated Advanced DNS page for `fuadsaary.me` | Saved `@`, `students`, and `admin` A records to `102.37.155.177`, and `www` CNAME to `fuadsaary.me`. Namecheap still showed Email Forwarding and its existing SPF TXT record; neither was changed. |
| DNS resolution from VM | `for h in fuadsaary.me www.fuadsaary.me students.fuadsaary.me admin.fuadsaary.me; do printf '%s: ' "$h"; getent ahostsv4 "$h" | awk 'NR==1{print $1}'; done` | All four names returned `102.37.155.177` through the VM resolver. A separate public authoritative-resolver check remains outstanding. |
| Workstation reachability | `Test-NetConnection 102.37.155.177 -Port 80` and `-Port 443` | Both returned `False` at the time of the check; the Azure NSG has no HTTP/HTTPS allow rule. Public HTTP cannot yet be tested. |
| PHP syntax | `php scripts/lint.php` | Passed for all PHP files. |
| Security unit checks | `php tests/security_test.php` | Passed missing, empty, non-string, and mismatched CSRF token checks and admin authorization cases. |
| Database integration | `php tests/database_integration.php` | Skipped because no local `TEST_DB_HOST`, `TEST_DB_NAME`, or `TEST_DB_USER` is configured. It has not yet been run against MariaDB. |

## Remaining verified deployment steps

| Requirement | State and proof still needed |
|---|---|
| Cloud hosting (2 marks) | Install/configure the isolated Apache/PHP/MariaDB stack on the existing VM. Capture Azure VM overview and Run Command output with service health, PHP version, Apache config test, migration list, and table list. |
| DNS (8 marks) | Keep the Namecheap record list screenshot; run `dig NS`, `dig A`, and `dig CNAME` against authoritative and independent public resolvers. Confirm no stale A/AAAA results. |
| Subdomains (3 marks) | After Apache deployment, capture main, `students`, `admin`, and `www` host-header route checks; then capture each public hostname in a browser. |
| ZeroSSL HTTPS (6 marks) | The account owner must supply ZeroSSL EAB credentials at the server prompt. Capture redacted certificate SAN/issuer/validity, HTTPS response, HTTP redirect, Secure cookie, HSTS, and renewal schedule evidence. Never screenshot or log the EAB values or private key. |
| Database security (2 marks) | Run migrations and MariaDB integration tests using a dedicated test database or the fresh CampusFlow schema. Capture the database/table listing, `login_attempts`, least-privilege grants, and proof that stored passwords are hashes. |
| Innovation (7 marks) | Capture actual request submission/retrieval, recommended priority, SLA state, admin status change/history, and the student's persisted notification. |
| Team cohesion (2 marks) | The two project members must fill in actual roles, reviewed commits, and contribution notes in `PROJECT_CHECKLIST.md`; no contribution evidence is available yet. |
| CAT 2 LAMP/Kali (22 marks) | Separately run the Kali VM setup and capture service/package installation, CAT2 schema, both required student registrations and logins, and another form's POST/retrieval. Azure deployment is not proof of Kali VM testing. |

## Evidence capture guidance

- Keep screenshots of the Azure VM Overview and successful Run Command output. Crop or redact account identity and all secret material.
- Keep the Namecheap Advanced DNS record table and the unchanged SPF/email-forwarding view.
- Capture terminal output for public `dig` results, `apachectl configtest`, MariaDB migrations/tables, `curl -I` routes/redirects, `openssl x509` SAN/validity, and renewal cron state.
- Capture browser pages for the main landing site, student signup/login/dashboard, request tracking/notifications, and admin authorization/status workflow after HTTPS works.
- Capture separate Kali VM evidence for the CAT 2 items; do not label Azure evidence as Kali evidence.
