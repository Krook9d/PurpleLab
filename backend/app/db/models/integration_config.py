from datetime import datetime

from sqlalchemy import DateTime, String, Text, func
from sqlalchemy.orm import Mapped, mapped_column

from app.db.session import Base


class IntegrationConfig(Base):
    __tablename__ = "integration_configs"

    # "connector:splunk" | "connector:opensearch" | "alienvault" | "mitre_ai"
    name: Mapped[str] = mapped_column(String(64), primary_key=True)
    # Encrypted JSON (app.core.crypto) — never a plaintext secret in the DB.
    encrypted_config: Mapped[str] = mapped_column(Text, nullable=False)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, server_default=func.now(), onupdate=func.now(), nullable=False
    )
