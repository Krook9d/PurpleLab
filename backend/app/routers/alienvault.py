import asyncio
import json
import logging

from fastapi import APIRouter, BackgroundTasks, Depends, HTTPException
from sqlalchemy.orm import Session

from app.core.crypto import decrypt, encrypt
from app.core.deps import get_current_user, require_admin
from app.db.models.alienvault_dashboard import AlienvaultDashboardCache
from app.db.models.integration_config import IntegrationConfig
from app.db.session import SessionLocal, get_db
from app.schemas.alienvault import AlienvaultConfigSave, AlienvaultConfigStatus, DashboardOut, RefreshStarted
from app.services.alienvault_service import AlienvaultError, generate_dashboard_data, test_api_key

router = APIRouter(prefix="/alienvault", tags=["alienvault"], dependencies=[Depends(get_current_user)])

_CONFIG_NAME = "alienvault"
logger = logging.getLogger(__name__)


def _get_api_key(db: Session) -> str | None:
    row = db.get(IntegrationConfig, _CONFIG_NAME)
    if row is None:
        return None
    return json.loads(decrypt(row.encrypted_config))["api_key"]


def _mask(api_key: str) -> str:
    if len(api_key) <= 8:
        return "•" * len(api_key)
    return f"{api_key[:4]}{'•' * 4}{api_key[-4:]}"


@router.get("/config", response_model=AlienvaultConfigStatus, dependencies=[Depends(require_admin)])
def get_config(db: Session = Depends(get_db)) -> AlienvaultConfigStatus:
    row = db.get(IntegrationConfig, _CONFIG_NAME)
    if row is None:
        return AlienvaultConfigStatus(configured=False)
    api_key = json.loads(decrypt(row.encrypted_config))["api_key"]
    return AlienvaultConfigStatus(configured=True, key_preview=_mask(api_key), updated_at=row.updated_at)


@router.put("/config", response_model=AlienvaultConfigStatus, dependencies=[Depends(require_admin)])
async def save_config(body: AlienvaultConfigSave, db: Session = Depends(get_db)) -> AlienvaultConfigStatus:
    # Tests the key live before persisting it, like testApiKey() in the
    # legacy saveAlienVaultConfig.php.
    if not await test_api_key(body.api_key):
        raise HTTPException(status_code=400, detail="invalid AlienVault OTX API key")

    encrypted = encrypt(json.dumps({"api_key": body.api_key}))
    row = db.get(IntegrationConfig, _CONFIG_NAME)
    if row is None:
        row = IntegrationConfig(name=_CONFIG_NAME, encrypted_config=encrypted)
        db.add(row)
    else:
        row.encrypted_config = encrypted
    db.commit()
    db.refresh(row)
    return AlienvaultConfigStatus(configured=True, key_preview=_mask(body.api_key), updated_at=row.updated_at)


@router.delete("/config", dependencies=[Depends(require_admin)])
def delete_config(db: Session = Depends(get_db)) -> dict:
    row = db.get(IntegrationConfig, _CONFIG_NAME)
    if row is not None:
        db.delete(row)
        db.commit()
    return {"message": "deleted"}


@router.get("/dashboard", response_model=DashboardOut)
def get_dashboard(db: Session = Depends(get_db)) -> DashboardOut:
    row = db.get(AlienvaultDashboardCache, 1)
    if row is None:
        raise HTTPException(status_code=404, detail="dashboard not generated yet — trigger a refresh first")
    return DashboardOut(data=row.data, generated_at=row.generated_at)


def _run_refresh_in_background() -> None:
    db = SessionLocal()
    try:
        api_key = _get_api_key(db)
        if api_key is None:
            logger.warning("alienvault refresh skipped: no API key configured")
            return
        data = asyncio.run(generate_dashboard_data(api_key))
        row = db.get(AlienvaultDashboardCache, 1)
        if row is None:
            db.add(AlienvaultDashboardCache(id=1, data=data))
        else:
            row.data = data
        db.commit()
    except AlienvaultError as exc:
        logger.warning("alienvault refresh failed: %s", exc)
    finally:
        db.close()


@router.post("/refresh", response_model=RefreshStarted, dependencies=[Depends(require_admin)])
def refresh(background_tasks: BackgroundTasks, db: Session = Depends(get_db)) -> RefreshStarted:
    if _get_api_key(db) is None:
        raise HTTPException(status_code=400, detail="AlienVault not configured")
    # FastAPI BackgroundTasks rather than the legacy fire-and-forget
    # shell_exec('... &') — and above all, this endpoint requires admin
    # auth (the legacy Flask /refresh_alienvault checked nothing at all).
    background_tasks.add_task(_run_refresh_in_background)
    return RefreshStarted(message="refresh started")
