from fastapi import APIRouter, Depends, HTTPException
from fastapi.concurrency import run_in_threadpool
from sqlalchemy import select
from sqlalchemy.orm import Session

from app.core.deps import get_current_user
from app.db.models.custom_payload import CustomPayload
from app.db.models.user import User
from app.db.session import get_db
from app.schemas.custom_payloads import ExecuteOut, PayloadCreate, PayloadOut
from app.services.vm_client import VmAgentUnavailable, execute_payload as agent_execute_payload

router = APIRouter(prefix="/custom-payloads", tags=["custom-payloads"], dependencies=[Depends(get_current_user)])


def _to_out(payload: CustomPayload, author: User) -> PayloadOut:
    return PayloadOut(
        id=payload.id,
        name=payload.name,
        content=payload.content,
        author_id=payload.author_id,
        author_first_name=author.first_name,
        author_last_name=author.last_name,
        created_at=payload.created_at,
    )


@router.get("", response_model=list[PayloadOut])
def list_payloads(db: Session = Depends(get_db)) -> list[PayloadOut]:
    # Catalog shared between all authenticated users (not filtered by
    # author), like the old custom_payloads.php.
    rows = db.execute(
        select(CustomPayload, User).join(User, CustomPayload.author_id == User.id).order_by(CustomPayload.created_at.desc())
    ).all()
    return [_to_out(payload, author) for payload, author in rows]


@router.post("", response_model=PayloadOut)
def create_payload(
    body: PayloadCreate, db: Session = Depends(get_db), user: User = Depends(get_current_user)
) -> PayloadOut:
    payload = CustomPayload(name=body.name, content=body.content, author_id=user.id)
    db.add(payload)
    db.commit()
    db.refresh(payload)
    return _to_out(payload, user)


@router.delete("/{payload_id}")
def delete_payload(
    payload_id: int, db: Session = Depends(get_db), user: User = Depends(get_current_user)
) -> dict:
    # Filters author_id directly in the deletion (like the old PHP,
    # already correct here): never reveals whether the ID exists and
    # belongs to someone else.
    payload = db.scalar(
        select(CustomPayload).where(CustomPayload.id == payload_id, CustomPayload.author_id == user.id)
    )
    if payload is not None:
        db.delete(payload)
        db.commit()
    return {"message": "deleted"}


@router.post("/{payload_id}/execute", response_model=ExecuteOut)
async def execute(
    payload_id: int, db: Session = Depends(get_db), user: User = Depends(get_current_user)
) -> ExecuteOut:
    # Reproduces the existing behavior: only the author can execute their
    # own payload, even though the catalog is visible to everyone (see
    # audit — not a reported bug, behavior preserved as-is).
    # run_in_threadpool: db.scalar is synchronous, this route is async def
    # (it later awaits the agent) — calling blocking SQLAlchemy code
    # directly here would block the event loop for all concurrent
    # requests (see app/routers/health.py for details).
    payload = await run_in_threadpool(
        db.scalar,
        select(CustomPayload).where(CustomPayload.id == payload_id, CustomPayload.author_id == user.id),
    )
    if payload is None:
        raise HTTPException(status_code=404, detail="payload not found")

    try:
        result = await agent_execute_payload(payload.content)
    except VmAgentUnavailable as exc:
        return ExecuteOut(status="error", error=str(exc))
    return ExecuteOut(status="success", output=result.get("output"))
