# FireLam PHP Panel

A self-contained PHP admin + chat panel for a locally-hosted FireLam model
served through [Ollama](https://ollama.com). No framework, no Composer
dependencies — plain PHP 8.1+, PDO/SQLite, and vanilla JS.

## What it does

- **Chat** — a streaming chat UI (`/chat.php`) for signed-in users, with
  per-user conversation history saved to SQLite.
- **Admin** — `/admin/` dashboard, user management (create/disable/reset
  password/delete), model + prompt settings, and a searchable activity log
  (logins, admin actions, errors).
- Talks to your model through Ollama's `/api/chat` endpoint and streams the
  reply back to the browser over Server-Sent Events as it's generated.

## Requirements on the VPS

- Ubuntu/Debian, nginx, PHP 8.1+ with `php-fpm`, `php-sqlite3`,
  `php-curl`, `php-mbstring`
- [Ollama](https://ollama.com) installed and running the FireLam model
  (see `../ollama/Modelfile` in this repo)

## Install

```bash
sudo bash install/install_php_panel.sh yourdomain.com
```

This installs nginx + PHP-FPM, copies the app to `/var/www/firelam-panel`,
initializes the SQLite database, prompts you to create the first admin
account, and wires up the nginx site (streaming-safe config: buffering is
disabled so replies stream token-by-token instead of arriving all at once).

Then load your model into Ollama, if you haven't already:

```bash
ollama create firelam-1.5 -f ollama/Modelfile
ollama run firelam-1.5 --keepalive 60m &
```

Open `http://yourdomain.com/`, sign in with the admin account you created,
and check **Admin → Settings** — confirm `model_name` matches what
`ollama list` shows, and `ollama_url` points at wherever Ollama is running
(default `http://127.0.0.1:11434`, i.e. the same box).

For HTTPS:

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com
```

## Manual install (no script)

1. Copy this folder to `/var/www/firelam-panel`, `chown -R www-data:www-data`.
2. Copy `.env.example` to `.env` and adjust `FIRELAM_OLLAMA_URL` / `FIRELAM_MODEL`.
3. `mkdir storage && chown www-data:www-data storage` (SQLite db + WAL files live here).
4. `sudo -u www-data php install/seed_admin.php admin 'a-strong-password'`
5. Point nginx at `public/` using `install/nginx_firelam_panel.conf.template`
   as a starting point (note the `fastcgi_buffering off` — required for streaming).
6. `systemctl reload nginx && systemctl restart php-fpm`

## Notes

- Passwords are hashed with PHP's `password_hash()` (bcrypt). Sessions are
  HttpOnly, SameSite=Lax cookies; forms are CSRF-protected.
- The database is a single SQLite file (`storage/firelam.db`) — back it up
  by copying that file (stop writes first, or use `sqlite3 .backup`).
- Self-registration is intentionally off: admins create accounts from
  **Admin → Users**. This is a deliberate scope choice for a small
  self-hosted deployment; if you need public signup, it isn't wired up yet.
- Everything here is unauthenticated to the *model*, but authenticated to
  the *panel* — anyone who can log in can chat, but only admins reach
  `/admin/`. Chat access is not exposed publicly without an account.
