import json

from sqlalchemy.orm import Session

from app.core.crypto import decrypt, encrypt
from app.db.models.integration_config import IntegrationConfig


def get_config(db: Session, name: str) -> dict | None:
    row = db.get(IntegrationConfig, name)
    if row is None:
        return None
    return json.loads(decrypt(row.encrypted_config))


def save_config(db: Session, name: str, config: dict) -> IntegrationConfig:
    encrypted = encrypt(json.dumps(config))
    row = db.get(IntegrationConfig, name)
    if row is None:
        row = IntegrationConfig(name=name, encrypted_config=encrypted)
        db.add(row)
    else:
        row.encrypted_config = encrypted
    db.commit()
    db.refresh(row)
    return row


def delete_config(db: Session, name: str) -> None:
    row = db.get(IntegrationConfig, name)
    if row is not None:
        db.delete(row)
        db.commit()
