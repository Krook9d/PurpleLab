"""seed atomic_tests from the bundled Atomic Red Team CSV index

Milestone 6 follow-up: atomic_tests had no seed mechanism in the Docker-only
install flow (the legacy Ansible `database` role imported
enterprise-attack/index_atomic.csv once at install time via `\\COPY`,
that step was never re-created when Ansible's database/webserver roles
were removed). Restores feature parity: the "Atomic tests by tactic"
dashboard chart and the MITRE technique detail page's atomic test list
were effectively empty on any fresh install.

Idempotent: only seeds when the table is currently empty, so it's a
no-op on a database that already has real data (from this migration
having already run, or any other legitimate source).

Revision ID: 0007
Revises: 0006
Create Date: 2026-08-25

"""
import csv
import os
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa

revision: str = "0007"
down_revision: Union[str, None] = "0006"
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None

CSV_PATH = os.environ.get("ATOMIC_TESTS_CSV", "/data/enterprise-attack/index_atomic.csv")

atomic_tests_table = sa.table(
    "atomic_tests",
    sa.column("tactic", sa.String),
    sa.column("technique_id", sa.String),
    sa.column("technique_name", sa.String),
    sa.column("test", sa.String),
    sa.column("test_name", sa.String),
    sa.column("Test_GUID", sa.String),
    sa.column("Executor_Name", sa.String),
)


def upgrade() -> None:
    bind = op.get_bind()
    existing = bind.execute(sa.text("SELECT COUNT(*) FROM atomic_tests")).scalar()
    if existing:
        return
    if not os.path.isfile(CSV_PATH):
        # Not fatal: an install that doesn't bind-mount enterprise-attack/
        # (e.g. a CI/test environment) just keeps an empty table, exactly
        # like before this migration existed.
        return

    rows = []
    with open(CSV_PATH, newline="", encoding="utf-8") as f:
        for record in csv.DictReader(f, delimiter=";"):
            rows.append(
                {
                    "tactic": record.get("Tactic") or None,
                    "technique_id": record.get("ID") or None,
                    "technique_name": record.get("Technique Name") or None,
                    "test": record.get("Test") or None,
                    "test_name": record.get("Test Name") or None,
                    "Test_GUID": record.get("Test GUID") or None,
                    "Executor_Name": record.get("Executor Name") or None,
                }
            )
    if rows:
        op.bulk_insert(atomic_tests_table, rows)


def downgrade() -> None:
    op.execute("DELETE FROM atomic_tests")
