from datetime import datetime

from sqlalchemy import DateTime, String, func
from sqlalchemy.orm import Mapped, mapped_column

from app.db.session import Base


class AtomicTest(Base):
    __tablename__ = "atomic_tests"

    id: Mapped[int] = mapped_column(primary_key=True)
    tactic: Mapped[str | None] = mapped_column(String(255))
    technique_id: Mapped[str | None] = mapped_column(String(255))
    technique_name: Mapped[str | None] = mapped_column(String(255))
    test: Mapped[str | None] = mapped_column(String(255))
    test_name: Mapped[str | None] = mapped_column(String(255))
    test_guid: Mapped[str | None] = mapped_column("Test_GUID", String(255))
    executor_name: Mapped[str | None] = mapped_column("Executor_Name", String(255))
    created_at: Mapped[datetime | None] = mapped_column(DateTime, server_default=func.now())
