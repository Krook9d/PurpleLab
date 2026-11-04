from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from app.core.deps import get_current_user, require_admin
from app.db.session import get_db
from app.schemas.mitre_ai import ChatMessage, ChatRequest, ChatResponse, MitreAiConfigSave, MitreAiConfigStatus
from app.services import integration_configs, mitre_ai_service

router = APIRouter(prefix="/mitre-ai", tags=["mitre-ai"], dependencies=[Depends(get_current_user)])

_CONFIG_NAME = "mitre_ai"


def _mask(api_key: str) -> str:
    if len(api_key) <= 8:
        return "•" * len(api_key)
    return f"{api_key[:4]}{'•' * 4}{api_key[-4:]}"


@router.get("/config", response_model=MitreAiConfigStatus, dependencies=[Depends(require_admin)])
def get_config(db: Session = Depends(get_db)) -> MitreAiConfigStatus:
    config = integration_configs.get_config(db, _CONFIG_NAME)
    if config is None:
        return MitreAiConfigStatus(configured=False)
    return MitreAiConfigStatus(
        configured=True,
        provider=config["provider"],
        model=config["model"],
        api_base=config.get("api_base"),
        key_preview=_mask(config["api_key"]),
    )


@router.put("/config", response_model=MitreAiConfigStatus, dependencies=[Depends(require_admin)])
async def save_config(body: MitreAiConfigSave, db: Session = Depends(get_db)) -> MitreAiConfigStatus:
    config = body.model_dump()
    # Tests the connection live before persisting, like
    # saveMitreAiConfig.php (mitre_ai_test_connection()) — rejects an
    # invalid config instead of silently storing it.
    if not await mitre_ai_service.test_connection(config):
        raise HTTPException(status_code=400, detail="could not connect with the given provider/model/key")

    integration_configs.save_config(db, _CONFIG_NAME, config)
    return MitreAiConfigStatus(
        configured=True,
        provider=config["provider"],
        model=config["model"],
        api_base=config.get("api_base"),
        key_preview=_mask(config["api_key"]),
    )


@router.delete("/config", dependencies=[Depends(require_admin)])
def delete_config(db: Session = Depends(get_db)) -> dict:
    integration_configs.delete_config(db, _CONFIG_NAME)
    return {"message": "deleted"}


@router.post("/chat", response_model=ChatResponse)
async def chat(body: ChatRequest, db: Session = Depends(get_db)) -> ChatResponse:
    messages = [m.model_dump() for m in body.messages]
    try:
        reply, related_techniques = await mitre_ai_service.chat(db, messages)
    except mitre_ai_service.MitreAiError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc
    return ChatResponse(message=ChatMessage(role="assistant", content=reply), related_techniques=related_techniques)
