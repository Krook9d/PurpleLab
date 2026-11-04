from fastapi import APIRouter, Depends, HTTPException
from fastapi.concurrency import run_in_threadpool
from sqlalchemy.orm import Session

from app.core.deps import get_current_user
from app.db.models.atomic_test import AtomicTest
from app.db.models.execution_history import ExecutionHistory
from app.db.models.mitre_technique import MitreTechnique
from app.db.models.user import User
from app.db.session import get_db
from app.schemas.mitre import (
    AtomicTestOut,
    ExecuteAtomicRequest,
    ExecuteOut,
    MatrixTechnique,
    MitreStats,
    RefreshResult,
    TacticCount,
    TechniqueDetail,
    TechniqueSummary,
)
from app.services import mitre_service
from app.services.vm_client import VmAgentUnavailable, execute_atomic_test as agent_execute_atomic_test

router = APIRouter(prefix="/mitre", tags=["mitre"], dependencies=[Depends(get_current_user)])


def _atomic_to_out(row: AtomicTest) -> AtomicTestOut:
    return AtomicTestOut(
        id=row.id,
        tactic=row.tactic,
        technique_id=row.technique_id,
        technique_name=row.technique_name,
        test=row.test,
        test_name=row.test_name,
        test_guid=row.test_guid,
        executor_name=row.executor_name,
    )


@router.get("/techniques", response_model=list[TechniqueSummary])
def search_techniques(q: str, db: Session = Depends(get_db)) -> list[TechniqueSummary]:
    if len(q) < 2:
        return []
    rows = mitre_service.search_techniques(db, q)
    return [
        TechniqueSummary(id=row.id, name=row.name, tactics=row.tactics, is_sub_technique=row.is_sub_technique)
        for row in rows
    ]


@router.get("/techniques/{technique_id}", response_model=TechniqueDetail)
def get_technique(technique_id: str, db: Session = Depends(get_db)) -> TechniqueDetail:
    technique = mitre_service.get_technique(db, technique_id)
    if technique is None:
        raise HTTPException(status_code=404, detail="technique not found")
    return TechniqueDetail.model_validate(technique, from_attributes=True)


@router.get("/techniques/{technique_id}/atomic-tests", response_model=list[AtomicTestOut])
def get_atomic_tests(technique_id: str, db: Session = Depends(get_db)) -> list[AtomicTestOut]:
    rows = mitre_service.get_atomic_tests(db, technique_id)
    return [_atomic_to_out(row) for row in rows]


@router.get("/matrix", response_model=list[MatrixTechnique])
def get_matrix(db: Session = Depends(get_db)) -> list[MatrixTechnique]:
    # def (not async def): the Sigma tag scan is file I/O, threadpooled.
    return [MatrixTechnique(**row) for row in mitre_service.matrix(db)]


@router.get("/stats", response_model=MitreStats)
def get_stats(db: Session = Depends(get_db)) -> MitreStats:
    return MitreStats(**mitre_service.stats(db))


@router.get("/atomic-tests/by-tactic", response_model=list[TacticCount])
def atomic_tests_by_tactic(db: Session = Depends(get_db)) -> list[TacticCount]:
    return [TacticCount(**row) for row in mitre_service.atomic_tests_by_tactic(db)]


@router.post("/refresh", response_model=RefreshResult)
def refresh(db: Session = Depends(get_db)) -> RefreshResult:
    # def (not async def): FastAPI auto-threadpools sync routes,
    # important here given the potentially long network call + pandas
    # processing (full STIX fetch from GitHub) — same reason as
    # list_payloads()/list_posts() elsewhere in the backend.
    return RefreshResult(**mitre_service.refresh(db))


@router.post("/execute-atomic/{technique_id}", response_model=ExecuteOut)
async def execute_atomic(
    technique_id: str,
    body: ExecuteAtomicRequest = ExecuteAtomicRequest(),
    db: Session = Depends(get_db),
    user: User = Depends(get_current_user),
) -> ExecuteOut:
    # Being authenticated is enough, like execute-payload — this is also the fix for the most serious flaw of
    # this milestone: the legacy version executed Invoke-AtomicTest in
    # the VM with NO authentication whatsoever (Web/app.py
    # /mitre_attack_execution, called directly by mittre.php's JS).
    try:
        result = await agent_execute_atomic_test(technique_id, body.test_number)
        outcome = ExecuteOut(status="success", output=result.get("output"))
    except VmAgentUnavailable as exc:
        outcome = ExecuteOut(status="error", error=str(exc))

    # Recorded for the "executed" coverage of the ATT&CK matrix; rule_id
    # holds the technique ID for this source.
    def _log_history() -> None:
        db.add(
            ExecutionHistory(
                source=mitre_service.ATOMIC_HISTORY_SOURCE,
                rule_id=technique_id,
                status=outcome.status,
                output=outcome.output,
                error=outcome.error,
                executed_by=user.id,
            )
        )
        db.commit()

    # Same rule as payload executions: never block the result on the
    # history write.
    try:
        await run_in_threadpool(_log_history)
    except Exception:
        pass

    return outcome
