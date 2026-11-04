from fastapi import APIRouter, Depends, HTTPException

from app.core.deps import get_current_user
from app.schemas.vm import ExecutePayloadRequest, ForensicOut, ForensicRequest, MessageOut, VmStateOut
from app.services import vm_client
from app.services.vm_client import VmAgentUnavailable

router = APIRouter(prefix="/vm", tags=["vm"], dependencies=[Depends(get_current_user)])


async def _call(coro) -> dict:
    try:
        return await coro
    except VmAgentUnavailable as exc:
        raise HTTPException(status_code=502, detail=f"vmbridge-agent unavailable: {exc}") from exc


@router.get("/state", response_model=VmStateOut)
async def state() -> VmStateOut:
    info = await _call(vm_client.get_vm_info())
    ip_info = await _call(vm_client.get_vm_ip())
    return VmStateOut(name=info["name"], state=info["state"], snapshot=info.get("snapshot"), ip=ip_info.get("ip"))


@router.post("/poweroff", response_model=MessageOut)
async def poweroff() -> MessageOut:
    await _call(vm_client.poweroff())
    return MessageOut(message="The VM has been powered off.")


@router.post("/restore", response_model=MessageOut)
async def restore() -> MessageOut:
    await _call(vm_client.restore())
    return MessageOut(message="The VM has been restored.")


@router.post("/start", response_model=MessageOut)
async def start() -> MessageOut:
    await _call(vm_client.start_headless())
    return MessageOut(message="VM successfully started in headless mode.")


@router.post("/enable-av", response_model=MessageOut)
async def enable_av() -> MessageOut:
    await _call(vm_client.enable_av())
    return MessageOut(message="Antivirus successfully enabled.")


@router.post("/disable-av", response_model=MessageOut)
async def disable_av() -> MessageOut:
    await _call(vm_client.disable_av())
    return MessageOut(message="Antivirus successfully disabled.")


@router.post("/restart-winlogbeat", response_model=MessageOut)
async def restart_winlogbeat() -> MessageOut:
    await _call(vm_client.restart_winlogbeat())
    return MessageOut(message="Winlogbeat service successfully restarted.")


@router.post("/execute-payload", response_model=MessageOut)
async def execute_payload(payload: ExecutePayloadRequest) -> MessageOut:
    # Being authenticated is enough, no special role — one-click payload
    # execution for any analyst, intended lab feature.
    await _call(vm_client.execute_payload(payload.content))
    return MessageOut(message="Payload executed.")


@router.post("/forensic", response_model=ForensicOut)
async def forensic(payload: ForensicRequest) -> ForensicOut:
    # Ports scripts/forensic_acquisition.py (memory dump / disk clone)
    # into vmbridge-agent, missing since Milestone 2 while the 3 other
    # scripts touching VBoxManage already had been — gap closed at
    # Milestone 6. Being authenticated is enough, same policy as the other
    # VM actions.
    result = await _call(vm_client.forensic_acquisition(payload.acquisition_type))
    return ForensicOut(message="Forensic acquisition completed.", output_path=result.get("output"))
