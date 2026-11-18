# Free ZeroSSL ACME certificate setup and renewal

ZeroSSL's current Free plan offers ACME-issued 90-day certificates at no charge, including multi-domain certificates; no annual/premium certificate is selected by this runbook. The ZeroSSL dashboard currently shows `0 / 3` manual 90-day certificate credits and an unverified account email. The free ACME path is separate from the manual certificate-order wizard. Sources: [ZeroSSL ACME documentation](https://zerossl.com/documentation/acme), [ACME feature details](https://zerossl.com/features/acme), and [Free plan pricing](https://zerossl.com/pricing).

The assignment mentions a six-month trial certificate, while the free ACME service issues 90-day certificates. Do not claim six-month validity unless the account shows and issues a separately verified entitlement. No certificate is installed as of this log; HTTP and DNS checks are not proof of HTTPS.

## Prerequisites

1. Confirm DNS for `fuadsaary.me`, `www.fuadsaary.me`, `students.fuadsaary.me`, and `admin.fuadsaary.me` resolves to `102.37.155.177`; see `DNS_CONFIGURATION.md`.
2. Allow inbound TCP 80 and 443 at the Azure NSG and host firewall. The user's approval added Azure rules `1020` and `1030`; the existing SSH restriction remains unchanged. Apache must serve the port 80 vhost for HTTP-01 challenges.
3. Use the monitored contact email associated with the ZeroSSL account or another approved mailbox. The account currently reports that its email is not verified. If ZeroSSL requires verification, the account owner must confirm the email from the message ZeroSSL sends.
4. Install the official acme.sh client as root at `/root/.acme.sh/acme.sh` using the [official repository](https://github.com/acmesh-official/acme.sh). Keep the default root config home `/root/.acme.sh` so the installed renewal cron uses the same account and certificate state.

## Issue and install the certificate

On the existing Ubuntu VM, from a trusted repository checkout, run as root and replace only the email argument with the monitored address:

```bash
sudo bash scripts/issue-zerossl.sh fuadsaary.me contact@example.com
```

The script registers against ZeroSSL's ACME directory `https://acme.zerossl.com/v2/DV90` and requests one SAN certificate for the apex, `www`, `students`, and `admin` hostnames using HTTP-01. It uses acme.sh's ZeroSSL EAB flow through an ephemeral copy in RAM-backed `/dev/shm`. The script removes only EAB-save statements from that one-shot copy, does not pass EAB credentials in command-line arguments, checks that no EAB values were written to `ca.conf`, and deletes the temporary copy after registration. ACME account keys and normal renewal metadata are required for certificate renewal and remain under the root-only ACME config home; the Apache private key is root-owned and group-readable only by `www-data`.

The issue script first backs up the CampusFlow Apache site file. After issuance, it installs the full chain and private key to `/etc/ssl/certs/campusflow-fullchain.pem` and `/etc/ssl/private/campusflow-key.pem`, configures TLS vhosts and HTTP→HTTPS redirects, checks Apache syntax, and verifies certificate hostname coverage and HTTPS responses for every host. It changes `APP_URL` to `https://students.fuadsaary.me` and enables `SESSION_SECURE=true` only after the HTTPS checks pass. If checks fail, it restores the previous CampusFlow HTTP site file.

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
