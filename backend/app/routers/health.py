import httpx
from fastapi import APIRouter, Depends, HTTPException
from fastapi.concurrency import run_in_threadpool
from sqlalchemy import text
from sqlalchemy.orm import Session

from app.core.config import get_settings
from app.core.deps import get_current_user
from app.db.session import get_db
from app.services.vm_client import VmAgentUnavailable, get_host_system, get_vm_info

router = APIRouter()


@router.get("/health")
async def health(db: Session = Depends(get_db)) -> dict:
    try:
        # db.execute is a synchronous/blocking SQLAlchemy call: in an
        # async def route, calling it directly would block the entire
        # event loop (i.e. ALL concurrent requests) for its duration —
        # went unnoticed as long as tests/manual checks only made one
        # request at a time; revealed by the React frontend which fires
        # several requests in parallel on page load. run_in_threadpool
        # takes it out of the event loop, like FastAPI would do
        # automatically for a plain `def` handler.
        await run_in_threadpool(db.execute, text("SELECT 1"))
        db_status = "ok"
    except Exception:
        db_status = "down"

    try:
        await get_vm_info()
        vm_agent_status = "ok"
    except VmAgentUnavailable:
        vm_agent_status = "down"

    return {"db": db_status, "vm_agent": vm_agent_status}


@router.get("/health/system", dependencies=[Depends(get_current_user)])
async def health_system() -> dict:
    try:
        return await get_host_system()
    except VmAgentUnavailable as exc:
        raise HTTPException(status_code=502, detail=f"vmbridge-agent unavailable: {exc}") from exc


@router.get("/health/event-count", dependencies=[Depends(get_current_user)])
async def event_count() -> dict:
    """Number of events ingested from the sandbox VM (Winlogbeat index
    of the internal docker-compose OpenSearch — not a configurable
    external SIEM connector, see app/services/connectors/). Replaces
    index.php's direct curl to https://localhost:9200/winlogbeat*/_count."""
    settings = get_settings()
    try:
        async with httpx.AsyncClient(timeout=5.0) as client:
            response = await client.get(f"{settings.opensearch_url}/winlogbeat*/_count")
    except httpx.HTTPError as exc:
        raise HTTPException(status_code=502, detail=f"opensearch unreachable: {exc}") from exc

    if response.status_code == 404:
        # No winlogbeat* index yet (VM never started with Winlogbeat
        # active): a genuine "zero events", not an outage.
        return {"count": 0}
    if response.status_code != 200:
        raise HTTPException(status_code=502, detail=f"opensearch error: {response.status_code}")
    return {"count": response.json().get("count", 0)}
