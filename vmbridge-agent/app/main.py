import hmac
import os

from fastapi import Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel

from app.config import get_settings
from app.host_metrics import get_system_metrics
from app.vbox import (
    VBoxCommandError,
    clone_disk,
    copy_file_to_vm,
    dump_memory,
    execute_atomic_test,
    execute_file_in_vm,
    execute_powershell_payload,
    get_vm_info,
    get_vm_ip,
    poweroff_vm,
    restart_winlogbeat_service,
    restore_vm,
    set_defender_realtime_monitoring,
    start_vm_headless,
)

app = FastAPI(title="vmbridge-agent")


def require_token(authorization: str = Header(default="")) -> None:
    settings = get_settings()
    expected = f"Bearer {settings.resolved_token}"
    if not settings.resolved_token or not hmac.compare_digest(authorization, expected):
        raise HTTPException(status_code=401, detail="invalid or missing token")


def _run_or_502(action) -> dict:
    try:
        result = action()
        # Some actions (execute_powershell_payload, execute_atomic_test)
        # return the stdout captured by guestcontrol run; others (e.g.
        # poweroff_vm) return nothing — in that case there's no "output"
        # key.
        return {"message": "ok", "output": result} if isinstance(result, str) else {"message": "ok"}
    except VBoxCommandError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/vm/state", dependencies=[Depends(require_token)])
def vm_state() -> dict:
    try:
        return get_vm_info()
    except VBoxCommandError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/vm/ip", dependencies=[Depends(require_token)])
def vm_ip() -> dict:
    try:
        return {"ip": get_vm_ip()}
    except VBoxCommandError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.post("/vm/poweroff", dependencies=[Depends(require_token)])
def vm_poweroff() -> dict:
    return _run_or_502(poweroff_vm)


@app.post("/vm/restore", dependencies=[Depends(require_token)])
def vm_restore() -> dict:
    return _run_or_502(restore_vm)


@app.post("/vm/start-headless", dependencies=[Depends(require_token)])
def vm_start_headless() -> dict:
    return _run_or_502(start_vm_headless)


@app.post("/vm/disable-av", dependencies=[Depends(require_token)])
def vm_disable_av() -> dict:
    return _run_or_502(lambda: set_defender_realtime_monitoring(enabled=False))


@app.post("/vm/enable-av", dependencies=[Depends(require_token)])
def vm_enable_av() -> dict:
    return _run_or_502(lambda: set_defender_realtime_monitoring(enabled=True))


@app.post("/vm/restart-winlogbeat", dependencies=[Depends(require_token)])
def vm_restart_winlogbeat() -> dict:
    return _run_or_502(restart_winlogbeat_service)


class FilenamePayload(BaseModel):
    filename: str


def _safe_filename(filename: str) -> str:
    # The filename comes from an HTTP request (relayed from a user upload
    # on the backend side): reject any attempt to escape the shared
    # folder before building a local path.
    base = os.path.basename(filename)
    if not base or base != filename or base in {".", ".."}:
        raise HTTPException(status_code=400, detail="invalid filename")
    return base


@app.post("/vm/copy-file", dependencies=[Depends(require_token)])
def vm_copy_file(payload: FilenamePayload) -> dict:
    settings = get_settings()
    filename = _safe_filename(payload.filename)
    # Two possible shared folders on the backend side (samples downloaded
    # via MalwareBazaar vs manually uploaded files) — check both rather
    # than requiring the caller to know which one to use.
    for directory in (settings.samples_dir, settings.malware_upload_dir):
        candidate = os.path.join(directory, filename)
        if os.path.isfile(candidate):
            return _run_or_502(lambda: copy_file_to_vm(candidate, filename))
    raise HTTPException(status_code=404, detail=f"file not found in shared directories: {filename}")


@app.post("/vm/execute-file", dependencies=[Depends(require_token)])
def vm_execute_file(payload: FilenamePayload) -> dict:
    filename = _safe_filename(payload.filename)
    return _run_or_502(lambda: execute_file_in_vm(filename))


class EncodedCommandPayload(BaseModel):
    encoded_command: str


@app.post("/vm/execute-payload", dependencies=[Depends(require_token)])
def vm_execute_payload(payload: EncodedCommandPayload) -> dict:
    return _run_or_502(lambda: execute_powershell_payload(payload.encoded_command))


class AtomicTestPayload(BaseModel):
    technique_id: str
    test_number: int | None = None


@app.post("/vm/execute-atomic-test", dependencies=[Depends(require_token)])
def vm_execute_atomic_test(payload: AtomicTestPayload) -> dict:
    return _run_or_502(lambda: execute_atomic_test(payload.technique_id, payload.test_number))


class ForensicPayload(BaseModel):
    acquisition_type: str


@app.post("/vm/forensic", dependencies=[Depends(require_token)])
def vm_forensic(payload: ForensicPayload) -> dict:
    if payload.acquisition_type == "memory":
        return _run_or_502(dump_memory)
    if payload.acquisition_type == "disk":
        return _run_or_502(clone_disk)
    raise HTTPException(status_code=400, detail="acquisition_type must be 'memory' or 'disk'")


@app.get("/host/system", dependencies=[Depends(require_token)])
def host_system() -> dict:
    return get_system_metrics()
