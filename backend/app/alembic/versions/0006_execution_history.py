"""add execution_history

Milestone 5 vague 2c: persists the execution history of rule-lifecycle
payloads (the "Execution & Results" tab of rule_lifecycle.php) — the
legacy version had no persistence at all, each execution returned an
ephemeral result lost on refresh (decision agreed with the user: real
persistence rather than React session state).

Revision ID: 0006
Revises: 0005
Create Date: 2026-08-25

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa

revision: str = "0006"
down_revision: Union[str, None] = "0005"
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    op.create_table(
        "execution_history",
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column("source", sa.String(32), nullable=False),
        sa.Column(
            "payload_id",
            sa.Integer,
            sa.ForeignKey("rule_lifecycle_payloads.id", ondelete="SET NULL"),
            nullable=True,
        ),
        sa.Column("rule_id", sa.String(255), nullable=True),
        sa.Column("connector", sa.String(32), nullable=True),
        sa.Column("status", sa.String(16), nullable=False),
        sa.Column("output", sa.Text, nullable=True),
        sa.Column("error", sa.Text, nullable=True),
        sa.Column("executed_by", sa.Integer, sa.ForeignKey("users.id"), nullable=True),
        sa.Column("executed_at", sa.DateTime, nullable=False, server_default=sa.func.now()),
    )
    op.create_index("ix_execution_history_connector", "execution_history", ["connector"])
    op.create_index("ix_execution_history_executed_at", "execution_history", ["executed_at"])


def downgrade() -> None:
    op.drop_index("ix_execution_history_executed_at", table_name="execution_history")
    op.drop_index("ix_execution_history_connector", table_name="execution_history")
    op.drop_table("execution_history")
