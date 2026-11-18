# ZeroSSL ACME certificate setup and renewal

ZeroSSL currently documents free ACME issuance as unlimited **90-day** certificates, including multi-domain certificates; that ACME offer does not provide a single six-month certificate. The assignment's “six-month trial” wording therefore needs lecturer clarification or an account-specific product/entitlement check before claiming six months. See [ZeroSSL ACME documentation](https://zerossl.com/documentation/acme) and [ZeroSSL ACME overview](https://zerossl.com/features/acme). This guide makes no claim that a certificate has been issued.

## Before issuance

1. Publish the A and CNAME records for `fuadsaary.me`, `www`, `students`, and `admin` as listed in `DNS_CONFIGURATION.md`. Do not publish AAAA without end-to-end IPv6.
2. Allow inbound TCP 80 and 443 through the cloud firewall and host firewall. Serve the port 80 Apache vhost first (the Ubuntu deployment script does this).
3. Confirm from an external resolver: `for host in fuadsaary.me www.fuadsaary.me students.fuadsaary.me admin.fuadsaary.me; do dig +short A "$host"; done` and check `curl -I http://students.fuadsaary.me/.well-known/acme-challenge/check` (a 404 for the deliberately nonexistent token is expected; it confirms the hostname reaches Apache).
4. Install a current acme.sh client from its [official repository](https://github.com/acmesh-official/acme.sh). One supported method is `sudo git clone https://github.com/acmesh-official/acme.sh.git /opt/acme.sh && sudo /opt/acme.sh/acme.sh --install -m contact@example.com`. Replace the example with a monitored contact address. Run as root for this system-wide Apache installation; the installer sets a daily renewal cron job. Verify it installed at `/root/.acme.sh/acme.sh` or is available as `acme.sh` in root's PATH.
5. In the ZeroSSL dashboard, generate EAB credentials in Developer → EAB Credentials. Save the KID and HMAC securely; ZeroSSL says these are not shown again after generation and are reusable. Do not put them in Git, `.env`, shell history, or screenshots.

## Issue the SAN certificate

Use the actual main domain and ACME contact email. Running as root, invoke the provided script; it prompts for the EAB KID and HMAC without echoing them:

```bash
sudo bash scripts/issue-zerossl.sh fuadsaary.me contact@example.com
```

The script selects ZeroSSL's ACME directory `https://acme.zerossl.com/v2/DV90`, registers the ACME account with EAB, and uses HTTP-01 webroot validation to request one certificate covering `fuadsaary.me`, `www.fuadsaary.me`, `students.fuadsaary.me`, and `admin.fuadsaary.me`. The relevant authoritative source for endpoint/EAB requirements is [ZeroSSL's ACME documentation](https://zerossl.com/documentation/acme). The HTTP challenge must work for every requested hostname. The script installs the full chain and private key into the Apache paths in `deploy/apache/campusflow.conf.example`, configures HTTP-to-HTTPS redirects/TLS vhosts, checks Apache config, and makes HTTPS requests for all four hostnames. It sets `APP_URL=https://students.fuadsaary.me` and `SESSION_SECURE=true` only after HTTPS, redirect, HSTS, SAN and Secure-cookie checks pass.

## Renewal and verification

acme.sh installs an automatic scheduled renewal check. `--install-cert` records the installed file paths and Apache reload command so renewal updates the served files; the upstream [acme.sh README](https://github.com/acmesh-official/acme.sh#3-install-the-certificate-to-apachenginx) specifically requires installing certificates to service paths and a reload command. Check client state and schedule:

```bash
sudo /root/.acme.sh/acme.sh --list
sudo crontab -l
sudo apachectl configtest
for host in fuadsaary.me www.fuadsaary.me students.fuadsaary.me admin.fuadsaary.me; do
  curl -fsSI "https://$host/" | head -n 1
  openssl s_client -connect "$host:443" -servername "$host" </dev/null 2>/dev/null | openssl x509 -noout -subject -issuer -dates -ext subjectAltName
done
```

For a renewal rehearsal, use acme.sh's staging/test option appropriate to the installed version before relying on production. Check the client log and make sure the successful renewal hook reloads Apache. On failure, retain the last working certificate, inspect DNS/port 80, EAB registration, ACME logs and system clock, and do not remove the installed files until a replacement validates. Protect `/etc/ssl/private/campusflow-key.pem` as root-owned and group-readable only by the Apache group. Set `SESSION_SECURE=true` in `.env` after HTTPS works.

The ACME certificate is 90 days under the current ZeroSSL free ACME documentation, so renewal must be automated and verified. Do not describe it as six months unless a separately verified account entitlement issues a certificate of that duration.
