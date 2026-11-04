from datetime import datetime

from sqlalchemy import DateTime, ForeignKey, Index, String, Text, func
from sqlalchemy.orm import Mapped, mapped_column

from app.db.session import Base


class ExecutionHistory(Base):
    __tablename__ = "execution_history"

    id: Mapped[int] = mapped_column(primary_key=True)
    # "rule_payload" (Execution & Results tab of the Rule Lifecycle page)
    # or "atomic_test" (Atomic Red Team runs from the MITRE page, where
    # rule_id holds the ATT&CK technique ID) — field designed to cover
    # other execution sources without changing the schema.
    source: Mapped[str] = mapped_column(String(32), nullable=False)
    # ON DELETE SET NULL: deleting a payload must never fail/erase its
    # past execution history — the history keeps output/status/rule_id/
    # connector, only the link to the payload (which no longer exists)
    # becomes NULL.
    payload_id: Mapped[int | None] = mapped_column(ForeignKey("rule_lifecycle_payloads.id", ondelete="SET NULL"))
    rule_id: Mapped[str | None] = mapped_column(String(255))
    connector: Mapped[str | None] = mapped_column(String(32))
    status: Mapped[str] = mapped_column(String(16), nullable=False)
    output: Mapped[str | None] = mapped_column(Text)
    error: Mapped[str | None] = mapped_column(Text)
    executed_by: Mapped[int | None] = mapped_column(ForeignKey("users.id"))
    executed_at: Mapped[datetime] = mapped_column(DateTime, server_default=func.now(), nullable=False)

    __table_args__ = (
        Index("ix_execution_history_connector", "connector"),
        Index("ix_execution_history_executed_at", "executed_at"),
    )
