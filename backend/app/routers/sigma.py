from fastapi import APIRouter, Depends, HTTPException

from app.core.deps import get_current_user
from app.schemas.sigma import BrowseOut, ConvertOut, ConvertRequest, RuleContentOut, SearchOut, StatsOut, UpdateOut
from app.services import sigma_service
from app.services.sigma_service import SigmaServiceError

router = APIRouter(prefix="/sigma", tags=["sigma"], dependencies=[Depends(get_current_user)])


# def (not async def) throughout this router: each function never
# awaits anything (synchronous file I/O + subprocess), FastAPI auto-
# threadpools sync routes — otherwise these blocking calls would run
# directly on the event loop and block all concurrent requests for
# their duration (same bug fixed on health.py in Milestone 3, never visible
# here as long as no page triggered several sigma.* calls in parallel —
# now the case with the dashboard polling /sigma/stats alongside other
# widgets).
@router.get("/browse", response_model=BrowseOut)
def browse(folder: str = "") -> BrowseOut:
    try:
        return BrowseOut(**sigma_service.browse_folder(folder))
    except SigmaServiceError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc


@router.get("/search", response_model=SearchOut)
def search(q: str) -> SearchOut:
    return SearchOut(matches=sigma_service.search_rules(q))


@router.get("/rule", response_model=RuleContentOut)
def rule(path: str) -> RuleContentOut:
    try:
        content = sigma_service.get_rule_content(path)
    except SigmaServiceError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc
    return RuleContentOut(path=path, content=content)


@router.post("/convert", response_model=ConvertOut)
def convert(payload: ConvertRequest) -> ConvertOut:
    try:
        output = sigma_service.convert_rule(payload.rule_path, payload.plugin)
    except SigmaServiceError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    return ConvertOut(output=output)


@router.get("/stats", response_model=StatsOut)
def stats() -> StatsOut:
    return StatsOut(**sigma_service.stats())


@router.post("/update", response_model=UpdateOut)
def update() -> UpdateOut:
    try:
        count = sigma_service.update_rules()
    except SigmaServiceError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc
    return UpdateOut(rule_count=count)
