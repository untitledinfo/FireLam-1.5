# Deploying FireLam to a VPS

This turns a fresh Ubuntu VPS into a running FireLam chat service: Ollama +
a model, a small FastAPI admin panel with API-key-gated chat, nginx, and
(optionally) your own domain with Cloudflare DNS and free SSL.

## What you need before starting

1. **A VPS** running Ubuntu 22.04 or 24.04, with at least the RAM/VRAM in
   `docs/HARDWARE.md`'s inference table for the model you're running.
   - Pure-CPU inference works fine for chat use at Q4 quantization — you
     don't need a GPU VPS unless you want fast responses on a 7B+ model.
2. **A domain** (optional but recommended) with its nameservers pointed at
   Cloudflare. If you don't have one yet, you can skip `DOMAIN` and just hit
   the server's IP address on port 80.
3. **A GitHub repo** with this code pushed to it (`git push` this repo to
   your own GitHub first — `install.sh` clones from a URL you provide).

## Step 1 — Push this repo to GitHub

```bash
cd FireLam-1.5
git init
git add .
git commit -m "Initial FireLam setup"
git remote add origin https://github.com/<you>/FireLam-1.5.git
git push -u origin main
```

## Step 2 — (Optional) Get Cloudflare API credentials

Only needed if you want `install.sh` to create the DNS record for you.
Otherwise, skip to Step 3 and point your `A`/`AAAA` record at the VPS IP
manually in the Cloudflare dashboard.

1. Cloudflare dashboard → your domain → **Overview** → copy the **Zone ID**
   (right sidebar).
2. Cloudflare dashboard → profile icon → **My Profile → API Tokens →
   Create Token** → use the "Edit zone DNS" template, scope it to your zone.
   Copy the token (shown once).

## Step 3 — Run the installer on the VPS

SSH into the VPS as root (or a sudo user), then:

```bash
git clone https://github.com/<you>/FireLam-1.5.git
cd FireLam-1.5

sudo GITHUB_REPO=https://github.com/<you>/FireLam-1.5.git \
     DOMAIN=ai.yourdomain.com \
     CF_API_TOKEN=<your-cloudflare-token> \
     CF_ZONE_ID=<your-zone-id> \
     LETSENCRYPT_EMAIL=you@yourdomain.com \
     MODEL_NAME=qwen2.5-coder:7b \
     bash deploy/install.sh
```

`MODEL_NAME` options:
- `qwen2.5-coder:7b` (or `1.5b`/`3b`/`14b`/`32b`) — coding-focused, Apache-2.0
- `deepseek-r1:7b` (or other sizes) — strong reasoning, MIT license
- `llama3.1:8b` — Meta license (see `docs/BASE_MODEL_CHOICES.md`)
- `firelam-1.5` — your own fine-tuned GGUF (build it first with
  `scripts/export_model.sh`, then `scp` `ollama/firelam-1.5.gguf` into
  `FireLam-1.5/ollama/` on the VPS before running the installer)

The script:
1. Installs nginx, certbot, Ollama.
2. Pulls the chosen model.
3. Clones/updates the repo into `/opt/firelam`.
4. Sets up the admin panel in a venv, generates a random admin password into
   `admin_panel/.env`.
5. Installs and starts the `firelam-admin` systemd service on port 8000.
6. Opens firewall ports 80/443 via `ufw`.
7. If `DOMAIN` + Cloudflare credentials are set, creates/updates the DNS
   record automatically.
8. Configures nginx as a reverse proxy for `DOMAIN`, waits for DNS to
   resolve, then requests a Let's Encrypt certificate via certbot.

## Step 4 — Manual DNS (if you skipped Cloudflare API credentials)

In the Cloudflare dashboard, under your domain's **DNS** tab, add:

| Type | Name | Content | Proxy status |
|---|---|---|---|
| A | `ai` (or whatever subdomain) | your VPS's public IPv4 | DNS only (grey cloud) until SSL is issued |

Use `AAAA` instead of `A` if you're pointing at an IPv6 address. Leave the
record **un-proxied** ("DNS only") until `certbot` successfully issues a
certificate — Cloudflare's proxy can interfere with the HTTP-01 challenge on
first issuance. You can switch it to proxied ("orange cloud") afterward.

## Step 5 — First login

1. Visit `https://yourdomain.com/admin` (or `http://<server-ip>:8000/admin`
   if you skipped the domain step).
2. Username `admin`, password from `/opt/firelam/admin_panel/.env`
   (`ADMIN_PASSWORD=`).
3. **Change that password** by editing `.env` directly, then
   `sudo systemctl restart firelam-admin`.
4. Create an API key (give it a name like "my laptop" or "chat widget").
5. Go to `/` (the chat page), paste the API key in, and start chatting.

## Operating it afterward

```bash
# Logs
journalctl -u firelam-admin -f

# Restart after editing .env or code
sudo systemctl restart firelam-admin

# Update to latest repo code
cd /opt/firelam && git pull && sudo systemctl restart firelam-admin

# Check Ollama models installed
ollama list

# Swap the default chat model without redeploying
# (edit MODEL_NAME= in /opt/firelam/admin_panel/.env, then restart the service)
```

## Calling the chat API directly (e.g. from a bot or script)

```bash
curl -X POST https://yourdomain.com/api/chat \
  -H "Content-Type: application/json" \
  -H "X-API-Key: fl-xxxxxxxxxxxxxxxxxxxxxxxx" \
  -d '{"messages": [{"role": "user", "content": "Write a bubble sort in Python."}]}'
```

## Security notes

- The admin panel binds to `127.0.0.1:8000` behind nginx — it's never
  directly exposed; only 80/443 are open in the firewall.
- API keys are stored as SHA-256 hashes in `admin_panel/data/api_keys.json`
  — the raw key is shown exactly once, at creation.
- Rotate the admin password and any leaked API keys immediately via
  `/admin` if you ever suspect exposure (e.g. pasted in a public chat log).
- This setup has no per-key rate limiting yet — if you're exposing the API
  key to end users (not just yourself), add rate limiting (e.g. via nginx
  `limit_req` or a Redis-backed check in `admin_panel/main.py`) before that.
