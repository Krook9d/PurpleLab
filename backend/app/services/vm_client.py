import base64

import httpx

from app.core.config import get_settings
from app.core.errors import clean_error_message


class VmAgentUnavailable(Exception):
    pass


async def _agent_request(
    method: str, path: str, timeout: float | None = None, json: dict | None = None
) -> dict:
    settings = get_settings()
    headers = {"Authorization": f"Bearer {settings.resolved_vm_agent_token}"}
    effective_timeout = timeout if timeout is not None else settings.vm_agent_timeout_seconds
    try:
        async with httpx.AsyncClient(timeout=effective_timeout) as client:
            response = await client.request(
                method, f"{settings.vm_agent_url}{path}", headers=headers, json=json
            )
            response.raise_for_status()
            return response.json()
    except (httpx.HTTPError, ValueError) as exc:
        raise VmAgentUnavailable(clean_error_message(exc)) from exc


async def get_vm_info() -> dict:
    return await _agent_request("GET", "/vm/state")


async def get_vm_ip() -> dict:
    return await _agent_request("GET", "/vm/ip")


async def poweroff() -> dict:
    return await _agent_request("POST", "/vm/poweroff", timeout=15)


async def restore() -> dict:
    # poweroff + restore snapshot + headless restart on the agent side:
    # can take several tens of seconds.
    return await _agent_request("POST", "/vm/restore", timeout=90)


async def start_headless() -> dict:
    return await _agent_request("POST", "/vm/start-headless", timeout=40)


async def disable_av() -> dict:
    return await _agent_request("POST", "/vm/disable-av", timeout=40)


async def enable_av() -> dict:
    return await _agent_request("POST", "/vm/enable-av", timeout=40)


async def restart_winlogbeat() -> dict:
    return await _agent_request("POST", "/vm/restart-winlogbeat", timeout=40)


async def copy_file(filename: str) -> dict:
    return await _agent_request("POST", "/vm/copy-file", timeout=30, json={"filename": filename})


async def execute_file(filename: str) -> dict:
    return await _agent_request("POST", "/vm/execute-file", timeout=30, json={"filename": filename})


async def execute_payload(content: str) -> dict:
    # PowerShell -EncodedCommand convention: UTF-16LE then base64
    # (never a shell on the agent side, the command arrives already encoded).
    encoded = base64.b64encode(content.encode("utf-16-le")).decode("ascii")
    return await _agent_request(
        "POST", "/vm/execute-payload", timeout=30, json={"encoded_command": encoded}
    )


async def execute_atomic_test(technique_id: str, test_number: int | None = None) -> dict:
    return await _agent_request(
        "POST",
        "/vm/execute-atomic-test",
        timeout=30,
        json={"technique_id": technique_id, "test_number": test_number},
    )


async def forensic_acquisition(acquisition_type: str) -> dict:
    # Memory dump / disk clone: heavy operations, timeout aligned with
    # the agent's (300s) rather than the short default of other actions.
    return await _agent_request(
        "POST", "/vm/forensic", timeout=310, json={"acquisition_type": acquisition_type}
    )


async def get_host_system() -> dict:
    # Host metrics read on the agent side (native, not containerized) to
    # reflect the real server rather than the backend container.
    return await _agent_request("GET", "/host/system")
