#!/usr/bin/env bash
# FireLam VPS installer.
#
# Sets up: Ollama + a base model, this repo, the FastAPI admin/chat panel as a
# systemd service, nginx reverse proxy, optional Cloudflare DNS record, and
# optional Let's Encrypt SSL via certbot.
#
# Run as root on a fresh Ubuntu 22.04/24.04 VPS:
#   sudo bash deploy/install.sh
#
# Configure via environment variables (all have defaults except where noted):
#   GITHUB_REPO      Repo URL to clone (default: this repo's own origin — set yours)
#   INSTALL_DIR       Where to install (default: /opt/firelam)
#   MODEL_NAME        Ollama model to pull: qwen2.5-coder:7b | llama3.1:8b |
#                     deepseek-r1:7b | firelam-1.5 (your own GGUF — see note below)
#   DOMAIN            Your domain, e.g. ai.yourdomain.com (leave empty to skip DNS+SSL)
#   CF_API_TOKEN      Cloudflare API token with Zone.DNS edit (optional, enables auto-DNS)
#   CF_ZONE_ID        Cloudflare Zone ID for your domain (optional, required with CF_API_TOKEN)
#   CF_RECORD_TYPE    A or AAAA (default: A)
#   CF_PROXIED        "true" to enable Cloudflare orange-cloud proxy (default: false —
#                     recommended off until certbot's HTTP-01 challenge succeeds once)
#   LETSENCRYPT_EMAIL Email for certbot (required if DOMAIN is set)
#
# Example:
#   sudo GITHUB_REPO=https://github.com/youruser/FireLam-1.5.git \
#        DOMAIN=ai.yourdomain.com \
#        CF_API_TOKEN=xxxx CF_ZONE_ID=yyyy \
#        LETSENCRYPT_EMAIL=you@yourdomain.com \
#        MODEL_NAME=qwen2.5-coder:7b \
#        bash deploy/install.sh

set -euo pipefail

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------
GITHUB_REPO="${GITHUB_REPO:?Set GITHUB_REPO to your FireLam repo git URL}"
INSTALL_DIR="${INSTALL_DIR:-/opt/firelam}"
MODEL_NAME="${MODEL_NAME:-qwen2.5-coder:7b}"
DOMAIN="${DOMAIN:-}"
CF_API_TOKEN="${CF_API_TOKEN:-}"
CF_ZONE_ID="${CF_ZONE_ID:-}"
CF_RECORD_TYPE="${CF_RECORD_TYPE:-A}"
CF_PROXIED="${CF_PROXIED:-false}"
LETSENCRYPT_EMAIL="${LETSENCRYPT_EMAIL:-}"

if [[ $EUID -ne 0 ]]; then
  echo "This script must be run as root (sudo bash deploy/install.sh)." >&2
  exit 1
fi

echo "=== FireLam VPS install ==="
echo "Repo:      $GITHUB_REPO"
echo "Install to: $INSTALL_DIR"
echo "Model:      $MODEL_NAME"
echo "Domain:     ${DOMAIN:-<none — will bind on server IP only>}"
echo ""

# ---------------------------------------------------------------------------
# 1. System packages
# ---------------------------------------------------------------------------
echo "--- Installing system packages ---"
apt-get update -y
apt-get install -y git curl python3 python3-venv python3-pip nginx ufw \
  certbot python3-certbot-nginx

# ---------------------------------------------------------------------------
# 2. Ollama + model
# ---------------------------------------------------------------------------
echo "--- Installing Ollama ---"
if ! command -v ollama >/dev/null 2>&1; then
  curl -fsSL https://ollama.com/install.sh | sh
fi
systemctl enable --now ollama

echo "--- Pulling model: $MODEL_NAME ---"
if [[ "$MODEL_NAME" == "firelam-1.5" ]]; then
  echo "MODEL_NAME=firelam-1.5 expects a GGUF you built yourself."
  echo "Run scripts/export_model.sh locally first, commit ollama/firelam-1.5.gguf"
  echo "(or scp it to the VPS), then this script will run 'ollama create' for you."
else
  ollama pull "$MODEL_NAME"
fi

# ---------------------------------------------------------------------------
# 3. Clone / update repo
# ---------------------------------------------------------------------------
echo "--- Fetching repo ---"
if [[ -d "$INSTALL_DIR/.git" ]]; then
  git -C "$INSTALL_DIR" pull
else
  git clone "$GITHUB_REPO" "$INSTALL_DIR"
fi

if [[ "$MODEL_NAME" == "firelam-1.5" && -f "$INSTALL_DIR/ollama/firelam-1.5.gguf" ]]; then
  (cd "$INSTALL_DIR" && ollama create firelam-1.5 -f ollama/Modelfile)
fi

# ---------------------------------------------------------------------------
# 4. Admin panel: venv + deps + .env
# ---------------------------------------------------------------------------
echo "--- Setting up admin panel ---"
cd "$INSTALL_DIR/admin_panel"
python3 -m venv .venv
./.venv/bin/pip install --upgrade pip
./.venv/bin/pip install -r requirements.txt

if [[ ! -f .env ]]; then
  cp .env.example .env
  GEN_PASSWORD="$(python3 -c 'import secrets; print(secrets.token_urlsafe(16))')"
  sed -i "s/ADMIN_PASSWORD=.*/ADMIN_PASSWORD=${GEN_PASSWORD}/" .env
  sed -i "s/MODEL_NAME=.*/MODEL_NAME=${MODEL_NAME}/" .env
  if [[ -n "$DOMAIN" ]]; then
    sed -i "s/DOMAIN=.*/DOMAIN=${DOMAIN}/" .env
  fi
  echo ""
  echo ">>> Generated admin password: ${GEN_PASSWORD}"
  echo ">>> Saved in ${INSTALL_DIR}/admin_panel/.env — back it up somewhere safe."
  echo ""
fi

mkdir -p data
chown -R www-data:www-data "$INSTALL_DIR"

# ---------------------------------------------------------------------------
# 5. systemd service
# ---------------------------------------------------------------------------
echo "--- Installing systemd service ---"
cp "$INSTALL_DIR/deploy/systemd/firelam-admin.service" /etc/systemd/system/firelam-admin.service
systemctl daemon-reload
systemctl enable --now firelam-admin

# ---------------------------------------------------------------------------
# 6. Firewall
# ---------------------------------------------------------------------------
echo "--- Configuring firewall ---"
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# ---------------------------------------------------------------------------
# 7. Cloudflare DNS (optional)
# ---------------------------------------------------------------------------
if [[ -n "$DOMAIN" && -n "$CF_API_TOKEN" && -n "$CF_ZONE_ID" ]]; then
  echo "--- Configuring Cloudflare DNS record ---"
  if [[ "$CF_RECORD_TYPE" == "AAAA" ]]; then
    SERVER_IP="$(curl -fsSL -6 https://ifconfig.co || true)"
  else
    SERVER_IP="$(curl -fsSL -4 https://ifconfig.co)"
  fi

  if [[ -z "$SERVER_IP" ]]; then
    echo "Could not auto-detect server IP for $CF_RECORD_TYPE — set the DNS record manually."
  else
    PROXIED_FLAG=""
    if [[ "$CF_PROXIED" == "true" ]]; then PROXIED_FLAG="--proxied"; fi
    python3 "$INSTALL_DIR/deploy/setup_cloudflare_dns.py" \
      --token "$CF_API_TOKEN" --zone-id "$CF_ZONE_ID" \
      --record "$DOMAIN" --ip "$SERVER_IP" --type "$CF_RECORD_TYPE" $PROXIED_FLAG
  fi
elif [[ -n "$DOMAIN" ]]; then
  DETECTED_IP="$(curl -fsSL -4 https://ifconfig.co 2>/dev/null || echo '<this server IP>')"
  echo ""
  echo ">>> DOMAIN is set but CF_API_TOKEN/CF_ZONE_ID were not — set your DNS record manually:"
  echo ">>>   Type: $CF_RECORD_TYPE   Name: $DOMAIN   Value: $DETECTED_IP"
  echo ""
fi

# ---------------------------------------------------------------------------
# 8. nginx + SSL (only if DOMAIN is set)
# ---------------------------------------------------------------------------
if [[ -n "$DOMAIN" ]]; then
  echo "--- Configuring nginx for $DOMAIN ---"
  sed "s/__DOMAIN__/${DOMAIN}/g" "$INSTALL_DIR/deploy/nginx_firelam.conf.template" \
    > /etc/nginx/sites-available/firelam.conf
  ln -sf /etc/nginx/sites-available/firelam.conf /etc/nginx/sites-enabled/firelam.conf
  rm -f /etc/nginx/sites-enabled/default
  nginx -t && systemctl reload nginx

  echo ""
  echo ">>> Waiting for DNS to resolve before requesting a certificate..."
  echo ">>> (If this hangs, your DNS record hasn't propagated yet — Ctrl+C and re-run"
  echo ">>>  'certbot --nginx -d ${DOMAIN}' manually once 'dig ${DOMAIN}' returns this IP.)"
  for i in {1..30}; do
    if getent hosts "$DOMAIN" >/dev/null 2>&1; then break; fi
    sleep 5
  done

  if [[ -n "$LETSENCRYPT_EMAIL" ]]; then
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$LETSENCRYPT_EMAIL" || \
      echo "certbot failed — DNS may not have propagated yet. Re-run: certbot --nginx -d ${DOMAIN}"
  else
    echo "LETSENCRYPT_EMAIL not set — skipping automatic SSL. Run manually:"
    echo "  certbot --nginx -d ${DOMAIN}"
  fi
fi

# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------
echo ""
echo "=== Install complete ==="
echo "Admin panel:  http${DOMAIN:+s}://${DOMAIN:-<server-ip>}${DOMAIN:+}/admin  (user: admin)"
echo "Chat UI:      http${DOMAIN:+s}://${DOMAIN:-<server-ip>}/"
echo "Health check: curl http://127.0.0.1:8000/api/health"
echo "Service logs: journalctl -u firelam-admin -f"
echo ""
echo "Admin password is in ${INSTALL_DIR}/admin_panel/.env — log into /admin and create"
echo "an API key there before using the chat UI or hitting /api/chat."
