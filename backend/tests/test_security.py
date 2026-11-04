import jwt
import pytest

from app.core.security import (
    JWT_ALGORITHM,
    create_access_token,
    decode_access_token,
    hash_password,
    verify_password,
)


def test_hash_and_verify_roundtrip():
    hashed = hash_password("CorrectHorseBatteryStaple")
    assert verify_password("CorrectHorseBatteryStaple", hashed)
    assert not verify_password("wrong-password", hashed)


def test_verify_against_real_php_password_hash():
    # Generated on this machine with:
    #   php -r "echo password_hash('CorrectHorseBatteryStaple', PASSWORD_DEFAULT);"
    # Validates the explicit risk point of the migration plan: existing
    # accounts ($2y$ hashes produced by PHP) must be accepted as-is by
    # this backend, without a password reset.
    php_hash = "$2y$10$XJdH1V3nf9TFiOLMgNnj3uCUa.mawleaZNfWxCDd2NChz97f11aqK"
    assert verify_password("CorrectHorseBatteryStaple", php_hash)
    assert not verify_password("wrong-password", php_hash)


def test_verify_password_rejects_garbage_hash():
    assert not verify_password("anything", "not-a-bcrypt-hash")


def test_create_and_decode_access_token():
    token = create_access_token(user_id=42, email="ada@example.com", role="analyst")
    payload = decode_access_token(token)
    assert payload["sub"] == "42"
    assert payload["email"] == "ada@example.com"
    assert payload["role"] == "analyst"


def test_decode_expired_token_raises():
    token = create_access_token(user_id=1, email="a@example.com", role="analyst")
    # Forces an exp in the past to simulate expiration without waiting 8h.
    payload = jwt.decode(token, options={"verify_signature": False})
    payload["exp"] = 0
    from app.core.config import get_settings

    expired_token = jwt.encode(payload, get_settings().resolved_jwt_secret, algorithm=JWT_ALGORITHM)
    with pytest.raises(jwt.ExpiredSignatureError):
        decode_access_token(expired_token)


def test_decode_token_with_wrong_secret_raises():
    token = jwt.encode({"sub": "1", "email": "a@example.com", "role": "analyst"}, "wrong-secret", algorithm=JWT_ALGORITHM)
    with pytest.raises(jwt.InvalidSignatureError):
        decode_access_token(token)
