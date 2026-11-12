# DNS configuration

Replace `example.edu` with a valid domain you own and `203.0.113.10` with the VPS public IPv4 (documentation-only address shown). At the authoritative DNS provider, create A records for `@`, `app`, and `admin` to the IPv4. Add AAAA records only when IPv6 is configured end to end. Optionally CNAME `www` to `@`. Nameservers remain those assigned by your registrar/provider; do not change them unless migrating DNS.

Check authoritative delegation and propagation: `dig NS example.edu`, `dig A example.edu`, `dig A app.example.edu`, `dig A admin.example.edu`, `nslookup app.example.edu`. Wait for TTL expiry; check for stale A/AAAA records, incorrect nameservers, or firewall blocks on 80/443. These example values do not mean DNS is configured.
