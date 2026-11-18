# DNS configuration for fuadsaary.me

The Azure public IPv4 for the existing VM `cyprianServer` is **102.37.155.177**. Azure identifies its Public IP resource as Standard SKU and Static allocation. No IPv6 address is configured, so do not publish AAAA records.

On 2026-11-19, the authenticated Namecheap Advanced DNS page showed the records below. The user approved changing the apex from its previous GitHub Pages A records to Azure. The `www` CNAME was restored after it was absent from the editor. Email Forwarding, the existing SPF TXT record, nameservers, and unrelated records were left unchanged.

| Type | Name/host | Value/target | Purpose |
|---|---|---|---|
| A | `@` | `102.37.155.177` | Main CampusFlow site |
| A | `students` | `102.37.155.177` | Student app |
| A | `admin` | `102.37.155.177` | Administration portal |
| CNAME | `www` | `fuadsaary.me` | Main-site alias; Apache redirects to the apex |

The Azure VM DNS label does not update the registrar zone. Namecheap BasicDNS remains selected. No nameserver change or domain transfer was made.

## Verification actually performed

On 2026-11-19, Google Public DNS checks returned `102.37.155.177` for A queries of the apex, `students`, `admin`, and `www`; the `www` CNAME resolves to the apex. Independent Check-Host probes reached the VM from multiple regions: apex HTTP `200`, `www` HTTP `301`, `students` HTTP `302`, and `admin` HTTP `302`. After the ZeroSSL certificate was installed, a public browser also loaded the apex, student login, and admin login routes successfully over HTTPS; `www` redirected to the apex. The redirects are expected for the alias and anonymous portal routes. The public evidence is recorded in `DEPLOYMENT_EVIDENCE.md`.

Repeat the checks with:

```bash
dig NS fuadsaary.me
dig @8.8.8.8 +short A fuadsaary.me
dig @8.8.8.8 +short A students.fuadsaary.me
dig @8.8.8.8 +short A admin.fuadsaary.me
dig @8.8.8.8 +short CNAME www.fuadsaary.me
dig @8.8.8.8 +short A www.fuadsaary.me
```

The expected result for the three A records is `102.37.155.177`; `www` should be a CNAME to `fuadsaary.me` and resolve to that same IPv4. If the static Public IP resource is replaced, update the A records and repeat independent DNS and HTTP checks. Do not remove or replace email records when editing this zone.

TLS is installed for all four names. The HTTPS certificate and redirect results are documented in `SSL_CONFIGURATION.md` and `DEPLOYMENT_EVIDENCE.md`; DNS resolution alone is not the certificate evidence.

