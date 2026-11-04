import base64
import hashlib
from functools import lru_cache

from cryptography.fernet import Fernet

from app.core.config import get_settings


@lru_cache
def _fernet() -> Fernet:
    # The configured secret (MASTER_ENCRYPTION_KEY) is not guaranteed to
    # be a valid Fernet key (32 urlsafe-base64 bytes): we derive one via
    # SHA256, robust regardless of its actual format/length.
    settings = get_settings()
    digest = hashlib.sha256(settings.resolved_master_encryption_key.encode()).digest()
    return Fernet(base64.urlsafe_b64encode(digest))


def encrypt(plaintext: str) -> str:
    return _fernet().encrypt(plaintext.encode()).decode()


def decrypt(ciphertext: str) -> str:
    return _fernet().decrypt(ciphertext.encode()).decode()
