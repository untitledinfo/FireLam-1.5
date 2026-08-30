#!/usr/bin/env python3
"""Create or update a Cloudflare DNS A/AAAA record to point at this VPS.

Dependency-free (stdlib only) so it can run during early VPS bootstrap before
any pip packages are installed.

Requires a Cloudflare API token with "Zone > DNS > Edit" permission for the
target zone, and that zone's Zone ID (both from the Cloudflare dashboard:
My Profile > API Tokens, and the zone's Overview page respectively).

Usage:
    python3 setup_cloudflare_dns.py \
        --token <CF_API_TOKEN> \
        --zone-id <CF_ZONE_ID> \
        --record ai.yourdomain.com \
        --ip 203.0.113.10 \
        --type A \
        [--proxied] [--ttl 300]
"""
import argparse
import json
import sys
import urllib.error
import urllib.request

API_BASE = "https://api.cloudflare.com/client/v4"


def cf_request(method: str, path: str, token: str, data: dict | None = None) -> dict:
    url = f"{API_BASE}{path}"
    headers = {"Authorization": f"Bearer {token}", "Content-Type": "application/json"}
    body = json.dumps(data).encode() if data is not None else None
    req = urllib.request.Request(url, data=body, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req) as resp:
            return json.loads(resp.read())
    except urllib.error.HTTPError as e:
        return json.loads(e.read())


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--token", required=True, help="Cloudflare API token (Zone.DNS edit)")
    parser.add_argument("--zone-id", required=True, help="Cloudflare Zone ID for the domain")
    parser.add_argument("--record", required=True, help="Full record name, e.g. ai.yourdomain.com")
    parser.add_argument("--ip", required=True, help="VPS public IP address")
    parser.add_argument("--type", default="A", choices=["A", "AAAA"])
    parser.add_argument(
        "--proxied", action="store_true",
        help="Enable Cloudflare proxy (orange cloud). Leave off if you want certbot's "
             "HTTP-01 challenge to hit your server directly first.",
    )
    parser.add_argument("--ttl", type=int, default=300, help="TTL in seconds (ignored if proxied)")
    args = parser.parse_args()

    existing = cf_request(
        "GET", f"/zones/{args.zone_id}/dns_records?type={args.type}&name={args.record}", args.token
    )
    if not existing.get("success"):
        print("Cloudflare API error while looking up existing record:", existing.get("errors"))
        sys.exit(1)

    payload = {
        "type": args.type,
        "name": args.record,
        "content": args.ip,
        "ttl": 1 if args.proxied else args.ttl,  # Cloudflare requires ttl=1 ("auto") when proxied
        "proxied": args.proxied,
    }

    if existing["result"]:
        record_id = existing["result"][0]["id"]
        result = cf_request("PUT", f"/zones/{args.zone_id}/dns_records/{record_id}", args.token, payload)
        action = "updated"
    else:
        result = cf_request("POST", f"/zones/{args.zone_id}/dns_records", args.token, payload)
        action = "created"

    if result.get("success"):
        print(f"DNS record {action}: {args.record} -> {args.ip} ({args.type}, proxied={args.proxied})")
    else:
        print("Failed to write DNS record:", result.get("errors"))
        sys.exit(1)


if __name__ == "__main__":
    main()
