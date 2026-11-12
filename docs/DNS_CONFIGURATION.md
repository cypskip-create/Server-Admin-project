# DNS configuration

Replace `example.edu` with a valid domain you own and `203.0.113.10` with the VPS public IPv4 (documentation-only address shown). At the authoritative DNS provider, create A records for `@`, `www`, `app`, and `admin` to the IPv4. Add matching AAAA records only when IPv6 is configured end to end. Alternatively make `www` a CNAME to the apex. Configure the actual nameservers at the registrar only if you are intentionally delegating DNS to a different provider.

Check authoritative delegation and propagation: `dig NS example.edu`; then run `dig A example.edu`, `dig A www.example.edu`, `dig A app.example.edu`, `dig A admin.example.edu` and equivalent AAAA queries if enabled. `nslookup app.example.edu` provides a second resolver check. Wait for TTL expiry; check stale A/AAAA records, incorrect nameservers, or firewall blocks on 80/443. These example values do not mean DNS is configured.
