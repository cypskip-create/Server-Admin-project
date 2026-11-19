# Free ZeroSSL ACME certificate setup and renewal

ZeroSSL's current Free plan offers ACME-issued 90-day certificates at no charge, including multi-domain certificates; no annual/premium certificate is selected by this runbook. At the time of account inspection, the dashboard showed `0 / 3` manual 90-day certificate credits and an unverified account email. ACME account registration and issuance nevertheless succeeded using the account contact. The free ACME path is separate from the manual certificate-order wizard. Sources: [ZeroSSL ACME documentation](https://zerossl.com/documentation/acme), [ACME feature details](https://zerossl.com/features/acme), and [Free plan pricing](https://zerossl.com/pricing).

The assignment mentions a six-month trial certificate, while the free ACME service issues 90-day certificates. The deployed certificate was issued by ZeroSSL ECC DV SSL CA 2 and is valid from `2026-11-18 00:00:00 UTC` through `2027-02-16 23:59:59 UTC`. Do not claim six-month validity unless the account shows and issues a separately verified entitlement.

## Prerequisites

1. Confirm DNS for `fuadsaary.me`, `www.fuadsaary.me`, `students.fuadsaary.me`, and `admin.fuadsaary.me` resolves to `102.37.155.177`; see `DNS_CONFIGURATION.md`.
2. Allow inbound TCP 80 and 443 at the Azure NSG and host firewall. The user's approval added Azure rules `1020` and `1030`; the existing SSH restriction remains unchanged. Apache must serve the port 80 vhost for HTTP-01 challenges.
3. Use the monitored contact email associated with the ZeroSSL account or another approved mailbox. The account dashboard reported that its email was unverified; ACME registration and issuance succeeded regardless. The account owner can verify it in ZeroSSL to ensure account notices reach the mailbox.
4. Install the official acme.sh client as root at `/root/.acme.sh/acme.sh` using the [official repository](https://github.com/acmesh-official/acme.sh). Keep the default root config home `/root/.acme.sh` so the installed renewal cron uses the same account and certificate state.

## Issue and install the certificate

On the existing Ubuntu VM, from a trusted repository checkout, run as root and replace only the email argument with the monitored address:

```bash
sudo bash scripts/issue-zerossl.sh fuadsaary.me contact@example.com
```

The script registers against ZeroSSL's ACME directory `https://acme.zerossl.com/v2/DV90` and requests one SAN certificate for the apex, `www`, `students`, and `admin` hostnames using HTTP-01. It uses acme.sh's ZeroSSL EAB flow through an ephemeral copy in RAM-backed `/dev/shm`. The script replaces only EAB-save calls with no-ops in that one-shot copy, does not pass EAB credentials in command-line arguments, checks that no EAB values were written to `ca.conf`, and deletes the temporary copy after registration. ACME account keys and normal renewal metadata are required for certificate renewal and remain under the root-only ACME config home; the Apache private key is root-owned and group-readable only by `www-data`.

The issue script first backs up the CampusFlow Apache site file. After issuance, it installs the full chain and private key to `/etc/ssl/certs/campusflow-fullchain.pem` and `/etc/ssl/private/campusflow-key.pem`, configures TLS vhosts and HTTP→HTTPS redirects, checks Apache syntax, and verifies certificate hostname coverage and HTTPS responses for every host. It then changes `APP_URL` to `https://students.fuadsaary.me` and enables `SESSION_SECURE=true` before checking the Secure cookie and HSTS response. If Apache rejects the TLS configuration, the script restores the backed-up CampusFlow HTTP site file. A later verification failure stops for manual review and leaves the installed certificate/vhost in place.

## Verified deployment result

The live Azure VM served the installed certificate for all four configured hostnames. `openssl x509 -checkend 86400` passed and the SAN list matched the requested names. Public browser checks loaded the apex landing page and the student/admin login routes; `www` redirected to the apex. VM checks confirmed HTTP-to-HTTPS `301` redirects, `SESSION_SECURE=true`, the Secure session-cookie flag, HSTS, the ACME cron, the Apache reload command in the renewal configuration, and no persisted EAB values. The root cron currently runs ACME at 03:25, 09:25, 15:25, and 21:25 UTC. This verifies renewal configuration, not a future successful renewal cycle.

The first deployment attempt stopped before ACME registration because an upstream acme.sh update changed the shape of its EAB-save block. The guard was updated and both the temporary copy and deploy script passed shell syntax checks. The issued certificate run completed its checks; a trailing carriage return from the GitHub file update caused a shell error after the success marker. The repository file was normalized to LF and CI passed afterward. Independent read-only checks confirmed the live installation.

## Verify and monitor renewal

After successful issuance, retain redacted output for all checks and never screenshot or print private keys or `.env`:

```bash
sudo /root/.acme.sh/acme.sh --list
sudo crontab -l
sudo apachectl configtest
for host in fuadsaary.me www.fuadsaary.me students.fuadsaary.me admin.fuadsaary.me; do
  curl -fsSI "https://$host/" | head -n 1
  openssl s_client -connect "$host:443" -servername "$host" </dev/null 2>/dev/null | openssl x509 -noout -subject -issuer -dates -ext subjectAltName
done
curl -sSI https://students.fuadsaary.me/login.php | grep -iE '^(set-cookie:|strict-transport-security:)'
```

Confirm all four SANs and valid dates, `301` HTTP→HTTPS redirects, a Secure session cookie, HSTS, and a daily acme.sh cron entry with Apache reload configured. Renewal is automatic, but it still needs monitoring. For a renewal rehearsal, follow the staging/test option appropriate to the installed acme.sh version and confirm the deploy/reload hook without replacing a working production certificate. If ZeroSSL rejects account registration because the account contact is unverified, stop and have the account owner verify it; do not use a paid order or bypass the check.
