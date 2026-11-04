from datetime import datetime, timedelta, timezone

import bcrypt
import jwt

from app.core.config import get_settings

JWT_ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_HOURS = 8


def verify_password(plain_password: str, password_hash: str) -> bool:
    """Compatible with hashes produced by PHP password_hash() ($2y$/$2a$/$2b$
    prefixes): the bcrypt lib normalizes these prefixes internally, so
    existing accounts do not need to reset their password when moving to
    this backend."""
    try:
        return bcrypt.checkpw(plain_password.encode("utf-8"), password_hash.encode("utf-8"))
    except ValueError:
        return False


def hash_password(plain_password: str) -> str:
    return bcrypt.hashpw(plain_password.encode("utf-8"), bcrypt.gensalt()).decode("utf-8")


def create_access_token(user_id: int, email: str, role: str) -> str:
    settings = get_settings()
    now = datetime.now(timezone.utc)
    payload = {
        "sub": str(user_id),
        "email": email,
        "role": role,
        "iat": now,
        "exp": now + timedelta(hours=ACCESS_TOKEN_EXPIRE_HOURS),
    }
    return jwt.encode(payload, settings.resolved_jwt_secret, algorithm=JWT_ALGORITHM)


def decode_access_token(token: str) -> dict:
    settings = get_settings()
    return jwt.decode(token, settings.resolved_jwt_secret, algorithms=[JWT_ALGORITHM])
