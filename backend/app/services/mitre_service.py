import pandas as pd
from mitreattack.attackToExcel import attackToExcel as attack_to_excel
from mitreattack.attackToExcel import stixToDf
from sqlalchemy import func, or_
from sqlalchemy.orm import Session

from app.db.models.atomic_test import AtomicTest
from app.db.models.execution_history import ExecutionHistory
from app.db.models.mitre_technique import MitreTechnique
from app.services import sigma_service

# ExecutionHistory.source of Atomic Red Team runs (rule_id holds the ATT&CK
# technique ID for this source).
ATOMIC_HISTORY_SOURCE = "atomic_test"

# Columns provided by stixToDf.techniquesToDf() -> MitreTechnique model
# columns. Does not cover detection/data_sources/defenses_bypassed/
# permissions_required/system_requirements/effective_permissions: these
# fields come from the full xlsx imported once by Ansible at
# provisioning, not from this lightweight path (see refresh() below).
_COLUMN_MAP = {
    "STIX ID": "stix_id",
    "name": "name",
    "description": "description",
    "url": "url",
    "created": "created",
    "last modified": "last_modified",
    "domain": "domain",
    "version": "version",
    "tactics": "tactics",
    "platforms": "platforms",
    "is sub-technique": "is_sub_technique",
    "sub-technique of": "sub_technique_of",
    "contributors": "contributors",
    "supports remote": "supports_remote",
    "impact type": "impact_type",
    "relationship citations": "relationship_citations",
}


def _clean(value):
    if isinstance(value, float) and pd.isna(value):
        return None
    return value


def search_techniques(db: Session, query: str, limit: int = 50) -> list[MitreTechnique]:
    # Search on id AND name (the legacy search_techniques.php only
    # matched on id — minor, risk-free improvement, the search field of a
    # MITRE explorer benefits from finding a technique by its name too).
    pattern = f"%{query.upper()}%"
    return (
        db.query(MitreTechnique)
        .filter(or_(func.upper(MitreTechnique.id).like(pattern), func.upper(MitreTechnique.name).like(pattern)))
        .order_by(MitreTechnique.id)
        .limit(limit)
        .all()
    )


def get_technique(db: Session, technique_id: str) -> MitreTechnique | None:
    return db.get(MitreTechnique, technique_id)


def get_atomic_tests(db: Session, technique_id: str) -> list[AtomicTest]:
    return db.query(AtomicTest).filter(AtomicTest.technique_id == technique_id).all()


def atomic_tests_by_tactic(db: Session) -> list[dict]:
    """Replaces the inline SQL GROUP BY tactic query from index.php."""
    rows = (
        db.query(AtomicTest.tactic, func.count(AtomicTest.technique_id))
        .group_by(AtomicTest.tactic)
        .order_by(func.count(AtomicTest.technique_id).desc())
        .all()
    )
    return [{"tactic": tactic or "(unknown)", "count": count} for tactic, count in rows]


def matrix(db: Session) -> list[dict]:
    """Every technique with its coverage counters for the ATT&CK matrix:
    Atomic tests available, Sigma rules tagged with it, and Atomic runs
    recorded in the execution history (with the latest run's status)."""
    atomic_counts = dict(
        db.query(AtomicTest.technique_id, func.count(AtomicTest.id)).group_by(AtomicTest.technique_id).all()
    )
    sigma_counts = sigma_service.technique_rule_counts()

    executions: dict[str, dict] = {}
    runs = (
        db.query(ExecutionHistory.rule_id, ExecutionHistory.status, ExecutionHistory.executed_at)
        .filter(ExecutionHistory.source == ATOMIC_HISTORY_SOURCE)
        .order_by(ExecutionHistory.executed_at)
        .all()
    )
    for technique_id, status, executed_at in runs:
        entry = executions.setdefault(technique_id, {"count": 0})
        entry["count"] += 1
        entry["last_status"] = status
        entry["last_executed_at"] = executed_at

    rows = db.query(
        MitreTechnique.id, MitreTechnique.name, MitreTechnique.tactics, MitreTechnique.is_sub_technique
    ).all()
    return [
        {
            "id": technique_id,
            "name": name,
            "tactics": tactics,
            "is_sub_technique": is_sub_technique,
            "atomic_tests": atomic_counts.get(technique_id, 0),
            "sigma_rules": sigma_counts.get(technique_id.upper(), 0),
            "executions": executions.get(technique_id, {}).get("count", 0),
            "last_execution_status": executions.get(technique_id, {}).get("last_status"),
            "last_executed_at": executions.get(technique_id, {}).get("last_executed_at"),
        }
        for technique_id, name, tactics, is_sub_technique in rows
    ]


def stats(db: Session) -> dict:
    # Real count of known MITRE ATT&CK techniques (mitre_techniques,
    # populated by refresh()/the initial Ansible import) — NOT the number
    # of techniques covered by an Atomic Red Team test (atomic_tests can
    # be near-empty, e.g. no seed mechanism in the Docker-only install
    # flow), which is what this incorrectly queried before.
    count = db.query(func.count(MitreTechnique.id)).scalar()
    return {"technique_count": count or 0}


def refresh(db: Session) -> dict:
    """Fetches up-to-date ATT&CK STIX data (enterprise-attack domain,
    from github.com/mitre/cti) and upserts mitre_techniques. Replaces the
    legacy "Update database" button which was broken end to end
    (update_mitre.php shelled out to attackToExcel.py with -db-host flags
    that don't exist in the real script, which only generates .xlsx files
    and never writes to the database — see audit). Upsert rather than
    TRUNCATE+reload: preserves detection/data_sources/etc. already present
    on existing rows (coming from the full xlsx snapshot imported once by
    Ansible), which this lightweight path cannot provide."""
    src = attack_to_excel.get_stix_data("enterprise-attack")
    techniques_df = stixToDf.techniquesToDf(src, "enterprise-attack")["techniques"]

    existing = {row.id: row for row in db.query(MitreTechnique).all()}
    created = 0
    updated = 0
    for _, raw_row in techniques_df.iterrows():
        technique_id = _clean(raw_row["ID"])
        if not technique_id:
            continue
        fields = {mapped: _clean(raw_row[column]) for column, mapped in _COLUMN_MAP.items()}
        row = existing.get(technique_id)
        if row is None:
            db.add(MitreTechnique(id=technique_id, **fields))
            created += 1
        else:
            for attr, value in fields.items():
                setattr(row, attr, value)
            updated += 1
    db.commit()
    return {"created": created, "updated": updated, "total": len(techniques_df)}
