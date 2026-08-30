"""Simple JSON-file API key store.

Good enough for a single-VPS deployment. If you outgrow this (many keys,
need rate-limiting, multiple admin panel replicas), swap this module for a
real database — every function signature here can stay the same.
"""
import hashlib
import json
import secrets
from datetime import datetime, timezone
from pathlib import Path

DB_PATH = Path(__file__).parent / "data" / "api_keys.json"


def _load() -> dict:
    if not DB_PATH.exists():
        return {"keys": {}}
    return json.loads(DB_PATH.read_text())


def _save(db: dict) -> None:
    DB_PATH.parent.mkdir(parents=True, exist_ok=True)
    DB_PATH.write_text(json.dumps(db, indent=2))


def _hash(key: str) -> str:
    return hashlib.sha256(key.encode()).hexdigest()


def create_key(name: str) -> str:
    """Create a new API key. Returns the RAW key — shown once, never stored raw."""
    raw_key = "fl-" + secrets.token_urlsafe(32)
    db = _load()
    db["keys"][_hash(raw_key)] = {
        "name": name,
        "created": datetime.now(timezone.utc).isoformat(),
        "revoked": False,
        "prefix": raw_key[:10] + "...",
    }
    _save(db)
    return raw_key


def list_keys() -> list[dict]:
    db = _load()
    return [{"id": key_hash, **info} for key_hash, info in db["keys"].items()]


def revoke_key(key_id: str) -> bool:
    db = _load()
    if key_id in db["keys"]:
        db["keys"][key_id]["revoked"] = True
        _save(db)
        return True
    return False


def is_valid(raw_key: str) -> bool:
    db = _load()
    entry = db["keys"].get(_hash(raw_key))
    return bool(entry and not entry["revoked"])
