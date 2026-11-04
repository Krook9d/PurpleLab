"""seed mitre_techniques from the bundled ATT&CK xlsx export

Milestone 6 follow-up, same root cause as 0007: the legacy Ansible pipeline
imported the full enterprise-attack-techniques.xlsx once at install time
(see the comments in app/services/mitre_service.py referring to "the full
xlsx snapshot imported once by Ansible"), but that step was never
re-created when Ansible's database/webserver roles were removed. Result:
mitre_techniques was empty on any fresh Docker install, and the MITRE
ATT&CK page showed no techniques until someone manually clicked "Update
database" (POST /mitre/refresh) — a live network fetch from GitHub that
also can't populate detection/data_sources/defenses_bypassed/
permissions_required/system_requirements/effective_permissions (see the
_COLUMN_MAP comment in mitre_service.py: those fields only come from this
xlsx, not from the lightweight STIX refresh path).

Idempotent: only seeds when the table is currently empty, and clicking
"Update database" afterward still works normally (upserts on top of this
seed) to pick up techniques added/changed since this xlsx was generated.

Revision ID: 0008
Revises: 0007
Create Date: 2026-09-21

"""
import os
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa

revision: str = "0008"
down_revision: Union[str, None] = "0007"
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None

XLSX_PATH = os.environ.get(
    "MITRE_TECHNIQUES_XLSX", "/data/enterprise-attack/enterprise-attack-techniques.xlsx"
)

# xlsx column -> mitre_techniques column (same mapping as
# mitre_service._COLUMN_MAP, extended with the fields only this full
# export provides).
_COLUMN_MAP = {
    "ID": "id",
    "STIX ID": "stix_id",
    "name": "name",
    "description": "description",
    "url": "url",
    "created": "created",
    "last modified": "last_modified",
    "domain": "domain",
    "version": "version",
    "tactics": "tactics",
    "detection": "detection",
    "platforms": "platforms",
    "data sources": "data_sources",
    "is sub-technique": "is_sub_technique",
    "sub-technique of": "sub_technique_of",
    "defenses bypassed": "defenses_bypassed",
    "contributors": "contributors",
    "permissions required": "permissions_required",
    "supports remote": "supports_remote",
    "system requirements": "system_requirements",
    "impact type": "impact_type",
    "effective permissions": "effective_permissions",
    "relationship citations": "relationship_citations",
}

_BOOLEAN_COLUMNS = {"is_sub_technique", "supports_remote"}

mitre_techniques_table = sa.table(
    "mitre_techniques",
    sa.column("id", sa.String),
    sa.column("stix_id", sa.String),
    sa.column("name", sa.String),
    sa.column("description", sa.Text),
    sa.column("url", sa.String),
    sa.column("created", sa.String),
    sa.column("last_modified", sa.String),
    sa.column("domain", sa.String),
    sa.column("version", sa.String),
    sa.column("tactics", sa.Text),
    sa.column("detection", sa.Text),
    sa.column("platforms", sa.Text),
    sa.column("data_sources", sa.Text),
    sa.column("is_sub_technique", sa.Boolean),
    sa.column("sub_technique_of", sa.String),
    sa.column("defenses_bypassed", sa.Text),
    sa.column("contributors", sa.Text),
    sa.column("permissions_required", sa.Text),
    sa.column("supports_remote", sa.Boolean),
    sa.column("system_requirements", sa.Text),
    sa.column("impact_type", sa.Text),
    sa.column("effective_permissions", sa.Text),
    sa.column("relationship_citations", sa.Text),
)


def _clean(value, is_boolean: bool = False):
    # pandas represents Excel blanks as float NaN regardless of the
    # column's "real" type, and "supports remote" is read as 0.0/1.0/NaN
    # (no native Excel boolean formatting) rather than True/False.
    try:
        import math

        if isinstance(value, float) and math.isnan(value):
            return None
    except TypeError:
        pass
    if is_boolean:
        return bool(value)
    return value


def upgrade() -> None:
    bind = op.get_bind()
    existing = bind.execute(sa.text("SELECT COUNT(*) FROM mitre_techniques")).scalar()
    if existing:
        return
    if not os.path.isfile(XLSX_PATH):
        # Not fatal: an install that doesn't bind-mount enterprise-attack/
        # (e.g. a CI/test environment) just keeps an empty table, same
        # fallback as 0007 for atomic_tests. "Update database" in the UI
        # remains available to populate it afterward.
        return

    import pandas as pd

    df = pd.read_excel(XLSX_PATH)

    rows = []
    for _, raw_row in df.iterrows():
        technique_id = _clean(raw_row.get("ID"))
        if not technique_id:
            continue
        row = {}
        for column, mapped in _COLUMN_MAP.items():
            if mapped == "id":
                continue
            row[mapped] = _clean(raw_row.get(column), is_boolean=mapped in _BOOLEAN_COLUMNS)
        row["id"] = technique_id
        if row.get("version") is not None:
            row["version"] = str(row["version"])
        rows.append(row)

    if rows:
        op.bulk_insert(mitre_techniques_table, rows)


def downgrade() -> None:
    op.execute("DELETE FROM mitre_techniques")
