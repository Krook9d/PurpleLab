from datetime import datetime

from sqlalchemy import JSON, DateTime, func
from sqlalchemy.orm import Mapped, mapped_column

from app.db.session import Base


class AlienvaultDashboardCache(Base):
    __tablename__ = "alienvault_dashboard_cache"

    # Single row (id=1): replaces the legacy dashboard_data.json file,
    # no shared filesystem between containers at this scale.
    id: Mapped[int] = mapped_column(primary_key=True)
    data: Mapped[dict] = mapped_column(JSON, nullable=False)
    # onupdate is required, not just server_default: without it, refreshing
    # the dashboard updates `data` but this column silently keeps the very
    # first generation timestamp forever — confirmed live (data changed
    # across refreshes, generated_at never did).
    generated_at: Mapped[datetime] = mapped_column(
        DateTime, server_default=func.now(), onupdate=func.now(), nullable=False
    )
