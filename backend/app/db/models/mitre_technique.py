from sqlalchemy import Boolean, String, Text
from sqlalchemy.orm import Mapped, mapped_column

from app.db.session import Base


class MitreTechnique(Base):
    __tablename__ = "mitre_techniques"

    id: Mapped[str] = mapped_column(String(255), primary_key=True)
    stix_id: Mapped[str | None] = mapped_column(String(255))
    name: Mapped[str | None] = mapped_column(String(255))
    description: Mapped[str | None] = mapped_column(Text)
    url: Mapped[str | None] = mapped_column(String(255))
    created: Mapped[str | None] = mapped_column(String(255))
    last_modified: Mapped[str | None] = mapped_column(String(255))
    domain: Mapped[str | None] = mapped_column(String(255))
    version: Mapped[str | None] = mapped_column(String(255))
    tactics: Mapped[str | None] = mapped_column(Text)
    detection: Mapped[str | None] = mapped_column(Text)
    platforms: Mapped[str | None] = mapped_column(Text)
    data_sources: Mapped[str | None] = mapped_column(Text)
    is_sub_technique: Mapped[bool | None] = mapped_column(Boolean)
    sub_technique_of: Mapped[str | None] = mapped_column(String(255))
    defenses_bypassed: Mapped[str | None] = mapped_column(Text)
    contributors: Mapped[str | None] = mapped_column(Text)
    permissions_required: Mapped[str | None] = mapped_column(Text)
    supports_remote: Mapped[bool | None] = mapped_column(Boolean)
    system_requirements: Mapped[str | None] = mapped_column(Text)
    impact_type: Mapped[str | None] = mapped_column(Text)
    effective_permissions: Mapped[str | None] = mapped_column(Text)
    relationship_citations: Mapped[str | None] = mapped_column(Text)
