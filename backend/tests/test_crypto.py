from app.core.crypto import decrypt, encrypt


def test_round_trip():
    ciphertext = encrypt("super-secret-value")
    assert ciphertext != "super-secret-value"
    assert decrypt(ciphertext) == "super-secret-value"


def test_ciphertext_differs_each_call():
    # Fernet includes a random nonce: two encryptions of the same text
    # must never produce the same blob.
    assert encrypt("same-value") != encrypt("same-value")
