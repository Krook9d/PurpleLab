import json

from sqlalchemy.orm import Session

from app.core.crypto import decrypt, encrypt
from app.db.models.integration_config import IntegrationConfig

CONNECTOR_TYPES = {"splunk", "opensearch", "wazuh", "elasticsearch", "graylog"}


def _config_name(connector_type: str) -> str:
    return f"connector:{connector_type}"


def save_connector(db: Session, connector_type: str, config: dict) -> None:
    name = _config_name(connector_type)
    encrypted = encrypt(json.dumps(config))
    row = db.get(IntegrationConfig, name)
    if row is None:
        db.add(IntegrationConfig(name=name, encrypted_config=encrypted))
    else:
        row.encrypted_config = encrypted
    db.commit()


def get_connector(db: Session, connector_type: str) -> dict | None:
    row = db.get(IntegrationConfig, _config_name(connector_type))
    if row is None:
        return None
    return json.loads(decrypt(row.encrypted_config))


def delete_connector(db: Session, connector_type: str) -> None:
    row = db.get(IntegrationConfig, _config_name(connector_type))
    if row is not None:
        db.delete(row)
        db.commit()


def list_connector_types(db: Session) -> list[str]:
    names = db.query(IntegrationConfig.name).filter(IntegrationConfig.name.like("connector:%")).all()
    return [name.split(":", 1)[1] for (name,) in names]


def mask_config(config: dict) -> dict:
    # Never return a plaintext secret to the client — fixes the leak in
    # connector_api.php's `get` action, which sent the password in
    # plaintext to the browser (rule_lifecycle.php:1499-1502).
    masked = dict(config)
    if masked.get("password"):
        masked["password"] = "••••••••"
    return masked
