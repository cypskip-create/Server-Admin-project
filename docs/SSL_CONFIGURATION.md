# HTTPS with ZeroSSL

ZeroSSL trial terms, certificate lifetimes and account entitlements can change; check the current ZeroSSL dashboard and official issuance docs before relying on a six-month certificate. ACME issuance may require account email plus External Account Binding (EAB) key ID and HMAC from the ZeroSSL account. Keep these secrets private.

For Apache, use an ACME client that supports ZeroSSL's ACME directory and EAB (for example, current Certbot with the appropriate plugin or acme.sh). Issue certificates only after DNS resolves to the VPS and inbound 80/443 work. Configure TLS vhost for the main, `app`, and `admin` names covered by certificate SANs; redirect HTTP to HTTPS; enable modern TLS protocols; set `SESSION_SECURE=true`. Follow the ACME client's renewal mechanism, then verify with `curl -I https://app.example.edu` and `openssl s_client -connect app.example.edu:443 -servername app.example.edu`. Never commit EAB credentials or private keys.
