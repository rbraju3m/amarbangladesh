<?php

/*
 * Which upstream proxies may tell us the visitor's real IP (X-Forwarded-For) and scheme.
 * The rate limits key on $request->ip(), so behind a proxy that isn't trusted every visitor
 * shares one limit.
 *
 * TRUSTED_PROXIES:
 *   (empty)     no proxy, the app is reached directly
 *   cloudflare  Cloudflare's edge ranges below; refresh them with scripts/cloudflare-ips.sh
 *   *           trust whoever connects; only when the origin is firewalled to your own proxy
 *   a,b,c       explicit IPs/CIDRs (can be combined with "cloudflare")
 */

$cloudflare = [
    // https://www.cloudflare.com/ips-v4 (fetched 2026-10-07)
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
    '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
    '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    // https://www.cloudflare.com/ips-v6
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
    '2a06:98c0::/29', '2c0f:f248::/32',
];

$setting = trim((string) env('TRUSTED_PROXIES', ''));

if ($setting === '' || $setting === '*') {
    $proxies = $setting === '' ? null : '*';
} else {
    $proxies = [];
    foreach (array_filter(array_map('trim', explode(',', $setting))) as $entry) {
        array_push($proxies, ...($entry === 'cloudflare' ? $cloudflare : [$entry]));
    }
}

return [
    'proxies' => $proxies,
];
