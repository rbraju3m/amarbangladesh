#!/usr/bin/env bash
# Prints Cloudflare's current edge ranges so config/trustedproxy.php can be compared/updated.
# They change rarely; check every few months: diff <(scripts/cloudflare-ips.sh) <(grep -oE "'[0-9a-f:.]+/[0-9]+'" config/trustedproxy.php | tr -d "'")
set -euo pipefail
curl -fsS https://www.cloudflare.com/ips-v4; echo
curl -fsS https://www.cloudflare.com/ips-v6; echo
