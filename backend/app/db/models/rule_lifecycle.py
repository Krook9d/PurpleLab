from datetime import datetime

from sqlalchemy import JSON, DateTime, ForeignKey, Index, String, Text, func
from sqlalchemy.orm import Mapped, mapped_column

from app.db.session import Base


class RuleLifecyclePayload(Base):
    __tablename__ = "rule_lifecycle_payloads"

    id: Mapped[int] = mapped_column(primary_key=True)
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    description: Mapped[str | None] = mapped_column(Text)
    code: Mapped[str] = mapped_column(Text, nullable=False)
    created_at: Mapped[datetime | None] = mapped_column(DateTime, server_default=func.now())
    updated_at: Mapped[datetime | None] = mapped_column(DateTime, server_default=func.now())


class RuleLifecycleRule(Base):
    __tablename__ = "rule_lifecycle_rules"

    id: Mapped[int] = mapped_column(primary_key=True)
    connector: Mapped[str] = mapped_column(String(32), nullable=False)
    rule_id: Mapped[str] = mapped_column(Text, nullable=False)
    # sync_rules() deletes then reinserts all rules for a connector on
    # every call (no upsert): rule_id is not guaranteed unique today, so
    # no UNIQUE constraint here to avoid breaking existing behavior.
    # rule_data stays generic JSON (not Postgres-only JSONB) to remain
    # testable under SQLite.
    rule_data: Mapped[dict] = mapped_column(JSON, nullable=False)
    synced_at: Mapped[datetime | None] = mapped_column(DateTime, server_default=func.now())

    __table_args__ = (Index("ix_rule_lifecycle_rules_connector", "connector"),)


class RulePayloadMap(Base):
    __tablename__ = "rule_payload_map"

    id: Mapped[int] = mapped_column(primary_key=True)
    rule_id: Mapped[str] = mapped_column(String(255), nullable=False)
    # payload_id is properly FK'able (rule_lifecycle_payloads.id is a real
    # unique SERIAL) unlike rule_id above.
    payload_id: Mapped[int] = mapped_column(ForeignKey("rule_lifecycle_payloads.id"), nullable=False)
    created_at: Mapped[datetime | None] = mapped_column(DateTime, server_default=func.now())

    # UNIQUE constraint added in Milestone 4 (migration 0005): enables a real
    # upsert (INSERT ... ON CONFLICT) in connectors.py, fixes the TOCTOU
    # race in the legacy save_rule_payload() (separate SELECT then
    # UPDATE/INSERT, no lock).
    __table_args__ = (Index("ix_rule_payload_map_rule_id", "rule_id", unique=True),)
