"""rule_payload_map.rule_id becomes unique

Milestone 4: enables a real upsert (INSERT ... ON CONFLICT) in connectors.py,
fixes the TOCTOU race in the legacy save_rule_payload() (separate SELECT
then UPDATE/INSERT, no lock, connector_api.php:706-734). Table still
empty in production at this stage (feature not yet ported to FastAPI),
safe to apply directly.

Revision ID: 0005
Revises: 0004
Create Date: 2026-08-23

"""
from typing import Sequence, Union

from alembic import op

revision: str = "0005"
down_revision: Union[str, None] = "0004"
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    op.drop_index("ix_rule_payload_map_rule_id", table_name="rule_payload_map")
    op.create_index(
        "ix_rule_payload_map_rule_id", "rule_payload_map", ["rule_id"], unique=True
    )


def downgrade() -> None:
    op.drop_index("ix_rule_payload_map_rule_id", table_name="rule_payload_map")
    op.create_index(
        "ix_rule_payload_map_rule_id", "rule_payload_map", ["rule_id"], unique=False
    )
