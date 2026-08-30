#!/usr/bin/env bash
#
# FireLam PHP Panel — one-shot bootstrap installer.
# Downloads the full repo from GitHub, then runs the real installer
# (php_panel/install/install_php_panel.sh) against it.
#
# Usage (as root, on a fresh Ubuntu/Debian VPS):
#   curl -fsSL https://raw.githubusercontent.com/untitledinfo/FireLam-1.5/main/install_firelam_panel.sh -o install_firelam_panel.sh
#   less install_firelam_panel.sh        # read it before running anything as root
#   bash install_firelam_panel.sh yourdomain.com
#   # or, with just an IP:
#   bash install_firelam_panel.sh 145.241.116.216
#
set -euo pipefail

REPO_URL="https://github.com/untitledinfo/FireLam-1.5"
REPO_TARBALL="${REPO_URL}/archive/refs/heads/main.tar.gz"
WORK_DIR="$(mktemp -d /tmp/firelam-install.XXXXXX)"
DOMAIN="${1:-}"

if [[ $EUID -ne 0 ]]; then
    echo "Run this as root: sudo bash install_firelam_panel.sh <domain-or-ip>" >&2
    exit 1
fi

if [[ -z "$DOMAIN" ]]; then
    read -rp "Domain or public IP this panel will be served on: " DOMAIN
fi

echo "==> Downloading FireLam-1.5 from $REPO_URL"
command -v curl  >/dev/null 2>&1 || { apt-get update -qq && apt-get install -y -qq curl; }
command -v tar   >/dev/null 2>&1 || { apt-get update -qq && apt-get install -y -qq tar; }

curl -fsSL "$REPO_TARBALL" -o "$WORK_DIR/firelam.tar.gz"
tar xzf "$WORK_DIR/firelam.tar.gz" -C "$WORK_DIR"

REPO_DIR="$(find "$WORK_DIR" -maxdepth 1 -type d -name 'FireLam-1.5-*' | head -n1)"
if [[ -z "$REPO_DIR" || ! -d "$REPO_DIR/php_panel" ]]; then
    echo "Could not find php_panel/ after extracting the repo. Aborting." >&2
    exit 1
fi

echo "==> Handing off to php_panel/install/install_php_panel.sh"
chmod +x "$REPO_DIR/php_panel/install/install_php_panel.sh"
bash "$REPO_DIR/php_panel/install/install_php_panel.sh" "$DOMAIN"

echo "==> Cleaning up temp files"
rm -rf "$WORK_DIR"

echo "Done. The downloaded repo copy in $WORK_DIR has been removed;"
echo "the running app lives at /var/www/firelam-panel (see install output above)."
