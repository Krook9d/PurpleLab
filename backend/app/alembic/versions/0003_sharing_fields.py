"""add contents.title/description/language/created_at

Fixes two bugs in Web/sharing.php found while migrating to React: the
form captures title/description/language for a "Code" post but the PHP
silently dropped them on save (only the raw text was kept); the table
had no creation date, so the page always displayed "Just now" even for
old posts.

Revision ID: 0003
Revises: 0002
Create Date: 2026-08-23

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa

revision: str = "0003"
down_revision: Union[str, None] = "0002"
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    op.add_column("contents", sa.Column("title", sa.String(255), nullable=True))
    op.add_column("contents", sa.Column("description", sa.Text, nullable=True))
    op.add_column("contents", sa.Column("language", sa.String(50), nullable=True))
    op.add_column(
        "contents",
        sa.Column("created_at", sa.DateTime, nullable=False, server_default=sa.func.now()),
    )


def downgrade() -> None:
    op.drop_column("contents", "created_at")
    op.drop_column("contents", "language")
    op.drop_column("contents", "description")
    op.drop_column("contents", "title")
