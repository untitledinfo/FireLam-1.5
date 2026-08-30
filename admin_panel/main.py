"""FireLam admin panel + chat API.

Serves:
  GET  /              — public chat UI (needs an API key entered client-side)
  GET  /admin         — admin panel (HTTP Basic auth), manage API keys
  POST /admin/keys    — create a new API key (admin only)
  POST /admin/keys/{key_id}/revoke — revoke a key (admin only)
  POST /api/chat      — chat endpoint, requires X-API-Key header, proxies to Ollama
  GET  /api/health    — health check

Run with: uvicorn main:app --host 0.0.0.0 --port 8000
"""
import os
import secrets

import httpx
from dotenv import load_dotenv
from fastapi import Depends, FastAPI, Header, HTTPException, Request
from fastapi.responses import HTMLResponse, JSONResponse
from fastapi.security import HTTPBasic, HTTPBasicCredentials
from fastapi.templating import Jinja2Templates

import store

load_dotenv()

ADMIN_USER = os.getenv("ADMIN_USER", "admin")
ADMIN_PASSWORD = os.getenv("ADMIN_PASSWORD", "changeme")
OLLAMA_HOST = os.getenv("OLLAMA_HOST", "http://localhost:11434")
MODEL_NAME = os.getenv("MODEL_NAME", "firelam-1.5")

if ADMIN_PASSWORD == "changeme":
    print(
        "\n*** WARNING: ADMIN_PASSWORD is still the default 'changeme'. "
        "Set a real password in .env before exposing this publicly. ***\n"
    )

app = FastAPI(title="FireLam Admin & Chat API")
templates = Jinja2Templates(directory="templates")
security = HTTPBasic()


def require_admin(credentials: HTTPBasicCredentials = Depends(security)) -> str:
    correct_user = secrets.compare_digest(credentials.username, ADMIN_USER)
    correct_pass = secrets.compare_digest(credentials.password, ADMIN_PASSWORD)
    if not (correct_user and correct_pass):
        raise HTTPException(
            status_code=401,
            detail="Invalid admin credentials",
            headers={"WWW-Authenticate": "Basic"},
        )
    return credentials.username


def require_api_key(x_api_key: str = Header(default="")) -> str:
    if not x_api_key or not store.is_valid(x_api_key):
        raise HTTPException(status_code=401, detail="Missing or invalid API key")
    return x_api_key


@app.get("/", response_class=HTMLResponse)
async def chat_page(request: Request):
    return templates.TemplateResponse(request, "chat.html", {"model_name": MODEL_NAME})


@app.get("/admin", response_class=HTMLResponse)
async def admin_page(request: Request, user: str = Depends(require_admin)):
    keys = store.list_keys()
    return templates.TemplateResponse(request, "admin.html", {"keys": keys})


@app.post("/admin/keys")
async def admin_create_key(request: Request, user: str = Depends(require_admin)):
    form = await request.form()
    name = (form.get("name") or "unnamed").strip()
    raw_key = store.create_key(name)
    return JSONResponse(
        {"api_key": raw_key, "warning": "Save this now — it will not be shown again."}
    )


@app.post("/admin/keys/{key_id}/revoke")
async def admin_revoke_key(key_id: str, user: str = Depends(require_admin)):
    if not store.revoke_key(key_id):
        raise HTTPException(status_code=404, detail="Key not found")
    return {"revoked": key_id}


@app.post("/api/chat")
async def chat(request: Request, api_key: str = Depends(require_api_key)):
    payload = await request.json()
    messages = payload.get("messages")
    if not messages:
        raise HTTPException(status_code=400, detail="Request body must include 'messages'")

    model = payload.get("model", MODEL_NAME)

    async with httpx.AsyncClient(timeout=120) as client:
        try:
            resp = await client.post(
                f"{OLLAMA_HOST}/api/chat",
                json={"model": model, "messages": messages, "stream": False},
            )
        except httpx.ConnectError:
            raise HTTPException(
                status_code=503,
                detail=f"Could not reach Ollama at {OLLAMA_HOST}. Is it running?",
            )

    if resp.status_code != 200:
        raise HTTPException(status_code=502, detail=f"Ollama error: {resp.text}")

    data = resp.json()
    return {"reply": data.get("message", {}).get("content", ""), "model": model}


@app.get("/api/health")
async def health():
    return {"status": "ok", "model": MODEL_NAME, "ollama_host": OLLAMA_HOST}
