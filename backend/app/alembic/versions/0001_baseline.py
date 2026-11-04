"""baseline: recreates the existing V3 schema (users, contents, custom_payloads,
atomic_tests, mitre_techniques) + formalizes the 3 tables that were until now
lazily created by scripts/php/connector_api.php (rule_lifecycle_payloads,
rule_lifecycle_rules, rule_payload_map) with real FK constraints.

For a V3 instance already in production whose schema already matches this
one: do not run `alembic upgrade head`, run `alembic stamp head` instead
after manually verifying the actual schema.

Revision ID: 0001
Revises:
Create Date: 2026-08-22

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa

revision: str = "0001"
down_revision: Union[str, None] = None
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    op.create_table(
        "users",
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column("first_name", sa.String(255), nullable=False),
        sa.Column("last_name", sa.String(255), nullable=False),
        sa.Column("email", sa.String(255), nullable=False, unique=True),
        sa.Column("analyst_level", sa.String(255), nullable=False),
        sa.Column("avatar", sa.String(255), nullable=False),
        sa.Column("password", sa.String(255), nullable=False),
        sa.Column("created_at", sa.DateTime, server_default=sa.func.now()),
    )

    op.create_table(
        "contents",
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column("author_id", sa.Integer, sa.ForeignKey("users.id"), nullable=False),
        sa.Column("content", sa.Text, nullable=False),
    )

    op.create_table(
        "custom_payloads",
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column("author_id", sa.Integer, sa.ForeignKey("users.id"), nullable=False),
        sa.Column("name", sa.String(255), nullable=False),
        sa.Column("content", sa.Text, nullable=False),
        sa.Column("created_at", sa.DateTime, server_default=sa.func.now()),
    )

    op.create_table(
        "atomic_tests",
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column("tactic", sa.String(255)),
        sa.Column("technique_id", sa.String(255)),
        sa.Column("technique_name", sa.String(255)),
        sa.Column("test", sa.String(255)),
        sa.Column("test_name", sa.String(255)),
        sa.Column("Test_GUID", sa.String(255)),
        sa.Column("Executor_Name", sa.String(255)),
        sa.Column("created_at", sa.DateTime, server_default=sa.func.now()),
    )

    op.create_table(
        "mitre_techniques",
        sa.Column("id", sa.String(255), primary_key=True),
        sa.Column("stix_id", sa.String(255)),
        sa.Column("name", sa.String(255)),
        sa.Column("description", sa.Text),
        sa.Column("url", sa.String(255)),
        sa.Column("created", sa.String(255)),
        sa.Column("last_modified", sa.String(255)),
        sa.Column("domain", sa.String(255)),
        sa.Column("version", sa.String(255)),
        sa.Column("tactics", sa.Text),
        sa.Column("detection", sa.Text),
        sa.Column("platforms", sa.Text),
        sa.Column("data_sources", sa.Text),
        sa.Column("is_sub_technique", sa.Boolean),
        sa.Column("sub_technique_of", sa.String(255)),
        sa.Column("defenses_bypassed", sa.Text),
        sa.Column("contributors", sa.Text),
        sa.Column("permissions_required", sa.Text),
        sa.Column("supports_remote", sa.Boolean),
        sa.Column("system_requirements", sa.Text),
        sa.Column("impact_type", sa.Text),
        sa.Column("effective_permissions", sa.Text),
        sa.Column("relationship_citations", sa.Text),
    )

    op.create_table(
        "rule_lifecycle_payloads",
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column("name", sa.String(255), nullable=False),
        sa.Column("description", sa.Text),
        sa.Column("code", sa.Text, nullable=False),
        sa.Column("created_at", sa.DateTime, server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime, server_default=sa.func.now()),
    )

    op.create_table(
        "rule_lifecycle_rules",
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column("connector", sa.String(32), nullable=False),
        sa.Column("rule_id", sa.Text, nullable=False),
        sa.Column("rule_data", sa.JSON, nullable=False),
        sa.Column("synced_at", sa.DateTime, server_default=sa.func.now()),
    )
    op.create_index(
        "ix_rule_lifecycle_rules_connector", "rule_lifecycle_rules", ["connector"]
    )

    op.create_table(
        "rule_payload_map",
        sa.Column("id", sa.Integer, primary_key=True),
        sa.Column("rule_id", sa.String(255), nullable=False),
        sa.Column(
            "payload_id",
            sa.Integer,
            sa.ForeignKey("rule_lifecycle_payloads.id"),
            nullable=False,
        ),
        sa.Column("created_at", sa.DateTime, server_default=sa.func.now()),
    )
    op.create_index("ix_rule_payload_map_rule_id", "rule_payload_map", ["rule_id"])


def downgrade() -> None:
    op.drop_table("rule_payload_map")
    op.drop_table("rule_lifecycle_rules")
    op.drop_table("rule_lifecycle_payloads")
    op.drop_table("mitre_techniques")
    op.drop_table("atomic_tests")
    op.drop_table("custom_payloads")
    op.drop_table("contents")
    op.drop_table("users")
