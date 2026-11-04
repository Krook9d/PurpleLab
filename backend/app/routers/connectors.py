import httpx
from fastapi import APIRouter, Depends, HTTPException
from fastapi.concurrency import run_in_threadpool
from sqlalchemy import select
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from app.core.deps import get_current_user
from app.core.errors import clean_error_message
from app.db.models.execution_history import ExecutionHistory
from app.db.models.rule_lifecycle import RuleLifecyclePayload, RuleLifecycleRule, RulePayloadMap
from app.db.models.user import User
from app.db.session import get_db
from app.schemas.connectors import (
    ConnectorConfig,
    ConnectorStatus,
    ConnectorTestResult,
    ExecuteContext,
    ExecuteOut,
    ExecutionHistoryOut,
    RuleLifecyclePayloadCreate,
    RuleLifecyclePayloadOut,
    RuleLifecyclePayloadUpdate,
    RulePayloadMapOut,
    RulePayloadMapUpdate,
    RulesSyncResult,
    SyncedRuleOut,
)
from app.services.connectors import (
    elasticsearch_client,
    graylog_client,
    manager,
    opensearch_client,
    splunk_client,
    wazuh_client,
)
from app.services.vm_client import VmAgentUnavailable, execute_payload as agent_execute_payload

router = APIRouter(tags=["connectors"], dependencies=[Depends(get_current_user)])


async def _splunk_test(config: dict) -> bool:
    return await run_in_threadpool(splunk_client.test_connection, config)


async def _splunk_list(config: dict) -> list[dict]:
    return await run_in_threadpool(splunk_client.list_rules, config)


def _connector_clients() -> dict[str, tuple]:
    # Built fresh on every call (cheap: just attribute lookups) rather
    # than once at import time, so tests that monkeypatch e.g.
    # opensearch_client.test_connection still take effect — a dict built
    # once at import time would freeze in the original function objects.
    # splunklib is sync, hence the run_in_threadpool wrappers above; every
    # other connector is httpx-based async already.
    return {
        "splunk": (_splunk_test, _splunk_list),
        "opensearch": (opensearch_client.test_connection, opensearch_client.list_rules),
        "wazuh": (wazuh_client.test_connection, wazuh_client.list_rules),
        "elasticsearch": (elasticsearch_client.test_connection, elasticsearch_client.list_rules),
        "graylog": (graylog_client.test_connection, graylog_client.list_rules),
    }


def _require_known_type(connector_type: str) -> str:
    if connector_type not in manager.CONNECTOR_TYPES:
        raise HTTPException(status_code=404, detail=f"unknown connector type: {connector_type}")
    return connector_type


def _require_config(db: Session, connector_type: str) -> dict:
    config = manager.get_connector(db, connector_type)
    if config is None:
        raise HTTPException(status_code=404, detail=f"connector '{connector_type}' not configured")
    return config


async def _test(connector_type: str, config: dict) -> bool:
    test_fn, _ = _connector_clients()[connector_type]
    return await test_fn(config)


async def _list_live_rules(connector_type: str, config: dict) -> list[dict]:
    _, list_fn = _connector_clients()[connector_type]
    try:
        return await list_fn(config)
    except httpx.HTTPError as exc:
        raise HTTPException(status_code=502, detail=f"{connector_type} unreachable: {clean_error_message(exc)}") from exc
    except Exception as exc:  # splunklib raises its own exception types
        raise HTTPException(status_code=502, detail=f"{connector_type} unreachable: {clean_error_message(exc)}") from exc


@router.get("/connectors", response_model=list[ConnectorStatus])
def list_connectors(db: Session = Depends(get_db)) -> list[ConnectorStatus]:
    configured_types = set(manager.list_connector_types(db))
    return [
        ConnectorStatus(
            type=connector_type,
            configured=connector_type in configured_types,
            config=(
                manager.mask_config(manager.get_connector(db, connector_type))
                if connector_type in configured_types
                else None
            ),
        )
        for connector_type in sorted(manager.CONNECTOR_TYPES)
    ]


@router.post("/connectors/{connector_type}/test", response_model=ConnectorTestResult)
async def test_connector(connector_type: str, body: ConnectorConfig) -> ConnectorTestResult:
    _require_known_type(connector_type)
    ok = await _test(connector_type, body.model_dump())
    return ConnectorTestResult(ok=ok)


@router.put("/connectors/{connector_type}", response_model=ConnectorStatus)
def save_connector(connector_type: str, body: ConnectorConfig, db: Session = Depends(get_db)) -> ConnectorStatus:
    _require_known_type(connector_type)
    config = body.model_dump()
    manager.save_connector(db, connector_type, config)
    return ConnectorStatus(type=connector_type, configured=True, config=manager.mask_config(config))


@router.delete("/connectors/{connector_type}")
def delete_connector(connector_type: str, db: Session = Depends(get_db)) -> dict:
    _require_known_type(connector_type)
    manager.delete_connector(db, connector_type)
    return {"message": "deleted"}


@router.get("/connectors/{connector_type}/rules/live", response_model=list[dict])
async def live_rules(connector_type: str, db: Session = Depends(get_db)) -> list[dict]:
    _require_known_type(connector_type)
    config = _require_config(db, connector_type)
    return await _list_live_rules(connector_type, config)


@router.get("/connectors/{connector_type}/rules", response_model=list[SyncedRuleOut])
def synced_rules(connector_type: str, db: Session = Depends(get_db)) -> list[SyncedRuleOut]:
    _require_known_type(connector_type)
    rows = db.scalars(
        select(RuleLifecycleRule)
        .where(RuleLifecycleRule.connector == connector_type)
        .order_by(RuleLifecycleRule.synced_at.desc())
    ).all()
    return [SyncedRuleOut.model_validate(row, from_attributes=True) for row in rows]


@router.post("/connectors/{connector_type}/rules/sync", response_model=RulesSyncResult)
async def sync_rules(connector_type: str, db: Session = Depends(get_db)) -> RulesSyncResult:
    _require_known_type(connector_type)
    config = _require_config(db, connector_type)
    live_rules_data = await _list_live_rules(connector_type, config)

    def _persist() -> int:
        db.query(RuleLifecycleRule).filter(RuleLifecycleRule.connector == connector_type).delete()
        for rule in live_rules_data:
            db.add(
                RuleLifecycleRule(
                    connector=connector_type,
                    rule_id=str(rule.get("id", rule.get("name", ""))),
                    rule_data=rule,
                )
            )
        db.commit()
        return len(live_rules_data)

    synced_count = await run_in_threadpool(_persist)
    return RulesSyncResult(synced_count=synced_count)


@router.get("/rule-payload-map", response_model=list[RulePayloadMapOut])
def get_rule_payload_map(db: Session = Depends(get_db)) -> list[RulePayloadMapOut]:
    rows = db.scalars(select(RulePayloadMap)).all()
    return [RulePayloadMapOut(rule_id=row.rule_id, payload_id=row.payload_id) for row in rows]


@router.put("/rule-payload-map/{rule_id}", response_model=RulePayloadMapOut)
def set_rule_payload_map(rule_id: str, body: RulePayloadMapUpdate, db: Session = Depends(get_db)) -> RulePayloadMapOut:
    if body.payload_id is None:
        db.query(RulePayloadMap).filter(RulePayloadMap.rule_id == rule_id).delete()
        db.commit()
        return RulePayloadMapOut(rule_id=rule_id, payload_id=None)

    existing = db.scalar(select(RulePayloadMap).where(RulePayloadMap.rule_id == rule_id))
    if existing is not None:
        existing.payload_id = body.payload_id
        db.commit()
        return RulePayloadMapOut(rule_id=rule_id, payload_id=body.payload_id)

    # UNIQUE constraint on rule_id (migration 0005): under a concurrent
    # race, the losing INSERT fails cleanly instead of creating a
    # duplicate (fixes the TOCTOU race in the legacy save_rule_payload()).
    db.add(RulePayloadMap(rule_id=rule_id, payload_id=body.payload_id))
    try:
        db.commit()
    except IntegrityError:
        db.rollback()
        existing = db.scalar(select(RulePayloadMap).where(RulePayloadMap.rule_id == rule_id))
        existing.payload_id = body.payload_id
        db.commit()
    return RulePayloadMapOut(rule_id=rule_id, payload_id=body.payload_id)


def _payload_to_out(payload: RuleLifecyclePayload) -> RuleLifecyclePayloadOut:
    return RuleLifecyclePayloadOut(
        id=payload.id,
        name=payload.name,
        description=payload.description,
        code=payload.code,
        created_at=payload.created_at,
        updated_at=payload.updated_at,
    )


@router.get("/payloads", response_model=list[RuleLifecyclePayloadOut])
def list_payloads(db: Session = Depends(get_db)) -> list[RuleLifecyclePayloadOut]:
    rows = db.scalars(select(RuleLifecyclePayload).order_by(RuleLifecyclePayload.created_at.desc())).all()
    return [_payload_to_out(row) for row in rows]


@router.post("/payloads", response_model=RuleLifecyclePayloadOut)
def create_payload(body: RuleLifecyclePayloadCreate, db: Session = Depends(get_db)) -> RuleLifecyclePayloadOut:
    payload = RuleLifecyclePayload(name=body.name, description=body.description, code=body.code)
    db.add(payload)
    db.commit()
    db.refresh(payload)
    return _payload_to_out(payload)


@router.get("/payloads/{payload_id}", response_model=RuleLifecyclePayloadOut)
def get_payload(payload_id: int, db: Session = Depends(get_db)) -> RuleLifecyclePayloadOut:
    payload = db.get(RuleLifecyclePayload, payload_id)
    if payload is None:
        raise HTTPException(status_code=404, detail="payload not found")
    return _payload_to_out(payload)


@router.put("/payloads/{payload_id}", response_model=RuleLifecyclePayloadOut)
def update_payload(
    payload_id: int, body: RuleLifecyclePayloadUpdate, db: Session = Depends(get_db)
) -> RuleLifecyclePayloadOut:
    payload = db.get(RuleLifecyclePayload, payload_id)
    if payload is None:
        raise HTTPException(status_code=404, detail="payload not found")
    payload.name = body.name
    payload.description = body.description
    payload.code = body.code
    db.commit()
    db.refresh(payload)
    return _payload_to_out(payload)


@router.delete("/payloads/{payload_id}")
def delete_payload(payload_id: int, db: Session = Depends(get_db)) -> dict:
    payload = db.get(RuleLifecyclePayload, payload_id)
    if payload is not None:
        db.delete(payload)
        db.commit()
    return {"message": "deleted"}


@router.post("/payloads/{payload_id}/execute", response_model=ExecuteOut)
async def execute_payload(
    payload_id: int,
    context: ExecuteContext = ExecuteContext(),
    db: Session = Depends(get_db),
    user: User = Depends(get_current_user),
) -> ExecuteOut:
    payload = await run_in_threadpool(db.get, RuleLifecyclePayload, payload_id)
    if payload is None:
        raise HTTPException(status_code=404, detail="payload not found")
    try:
        result = await agent_execute_payload(payload.code)
        outcome = ExecuteOut(status="success", output=result.get("output"))
    except VmAgentUnavailable as exc:
        outcome = ExecuteOut(status="error", error=str(exc))

    def _log_history() -> None:
        db.add(
            ExecutionHistory(
                source="rule_payload",
                payload_id=payload_id,
                rule_id=context.rule_id,
                connector=context.connector,
                status=outcome.status,
                output=outcome.output,
                error=outcome.error,
                executed_by=user.id,
            )
        )
        db.commit()

    # Never block the execution response on the history write — the
    # user must see the result even if persistence fails.
    try:
        await run_in_threadpool(_log_history)
    except Exception:
        pass

    return outcome


@router.get("/execution-history", response_model=list[ExecutionHistoryOut])
def get_execution_history(
    connector: str | None = None,
    status: str | None = None,
    source: str | None = None,
    limit: int = 50,
    offset: int = 0,
    db: Session = Depends(get_db),
) -> list[ExecutionHistoryOut]:
    query = select(ExecutionHistory).order_by(ExecutionHistory.executed_at.desc())
    if connector:
        query = query.where(ExecutionHistory.connector == connector)
    if status:
        query = query.where(ExecutionHistory.status == status)
    if source:
        query = query.where(ExecutionHistory.source == source)
    rows = db.scalars(query.offset(offset).limit(limit)).all()
    return [ExecutionHistoryOut.model_validate(row, from_attributes=True) for row in rows]
