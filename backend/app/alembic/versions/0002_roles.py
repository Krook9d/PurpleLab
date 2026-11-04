"""add users.role (analyst | admin), backfilled from email = 'admin@local.com'

Revision ID: 0002
Revises: 0001
Create Date: 2026-08-22

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa

revision: str = "0002"
down_revision: Union[str, None] = "0001"
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    op.add_column(
        "users",
        sa.Column("role", sa.String(20), nullable=False, server_default="analyst"),
    )
    op.execute("UPDATE users SET role = 'admin' WHERE email = 'admin@local.com'")


def downgrade() -> None:
    op.drop_column("users", "role")
