"""add integration_configs and alienvault_dashboard_cache

Milestone 4: foundation of the single encryption service (app/core/crypto.py,
Fernet derived from MASTER_ENCRYPTION_KEY) which replaces the 4 incompatible
legacy secret storage schemes (encryption.php, mitre_ai_lib.php,
connector_manager.py, alienvault.py/saveAlienVaultConfig.php).
integration_configs stores an encrypted JSON blob per integration
("connector:splunk", "connector:opensearch", "alienvault", "mitre_ai").
alienvault_dashboard_cache replaces the dashboard_data.json file (no
shared filesystem between containers).

Revision ID: 0004
Revises: 0003
Create Date: 2026-08-23

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa

revision: str = "0004"
down_revision: Union[str, None] = "0003"
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    op.create_table(
        "integration_configs",
        sa.Column("name", sa.String(64), primary_key=True),
        sa.Column("encrypted_config", sa.Text, nullable=False),
        sa.Column("updated_at", sa.DateTime, nullable=False, server_default=sa.func.now()),
    )
    op.create_table(
        "alienvault_dashboard_cache",
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column("data", sa.JSON, nullable=False),
        sa.Column("generated_at", sa.DateTime, nullable=False, server_default=sa.func.now()),
    )


def downgrade() -> None:
    op.drop_table("alienvault_dashboard_cache")
    op.drop_table("integration_configs")
