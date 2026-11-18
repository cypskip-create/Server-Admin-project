# DNS configuration for fuadsaary.me

The Azure Public IP observed for `cyprianServer` is **102.37.155.177**. Azure reports a Standard, static IPv4 allocation, so it is suitable as the DNS target while that Public IP resource remains attached. No IPv6 address is configured; do not publish AAAA records.

On 2026-11-19, the Namecheap Advanced DNS page showed the four records below saved. The existing `www` CNAME was restored after it was absent from the editor. The Azure VM's resolver returned `102.37.155.177` for all four hostnames. Verify against an independent public resolver and the authoritative nameservers before marking DNS propagation fully verified. The existing Email Forwarding SPF TXT value was left unchanged.

The current Namecheap Advanced DNS record set is:

| Type | Name/host | Value/target | Purpose |
|---|---|---|---|
| A | `@` | `102.37.155.177` | Main CampusFlow site |
| A | `students` | `102.37.155.177` | Student app |
| A | `admin` | `102.37.155.177` | Administration portal |
| CNAME | `www` | `fuadsaary.me` | Canonical redirect to the main site |

The Azure VM's DNS label setting does not update the registrar's DNS zone. Namecheap BasicDNS remains selected; nameservers, email forwarding and unrelated records were not intentionally changed. Preserve any unrelated records if further DNS edits are needed.

Verify delegation and propagation from a terminal:

```bash
dig NS fuadsaary.me
dig +short A fuadsaary.me
dig +short A students.fuadsaary.me
dig +short A admin.fuadsaary.me
dig +short CNAME www.fuadsaary.me
dig +short A www.fuadsaary.me
```

The three A queries should return `102.37.155.177`; the `www` CNAME should target `fuadsaary.me` (some resolvers also show its resolved A address). Check for stale A/AAAA records at the authoritative nameservers. Do not request the ZeroSSL certificate until all four names resolve correctly and inbound HTTP reaches the VM.
