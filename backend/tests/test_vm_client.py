import asyncio
import base64
from unittest.mock import AsyncMock, patch

from app.services import vm_client


def test_execute_payload_encodes_utf16le_base64():
    with patch("app.services.vm_client._agent_request", new=AsyncMock(return_value={"message": "ok"})) as mock_req:
        asyncio.run(vm_client.execute_payload("whoami"))

    _, kwargs = mock_req.call_args
    sent = kwargs["json"]["encoded_command"]
    decoded = base64.b64decode(sent).decode("utf-16-le")
    assert decoded == "whoami"


def test_forensic_acquisition_calls_agent_with_generous_timeout():
    with patch("app.services.vm_client._agent_request", new=AsyncMock(return_value={"message": "ok"})) as mock_req:
        asyncio.run(vm_client.forensic_acquisition("memory"))

    args, kwargs = mock_req.call_args
    assert args[:2] == ("POST", "/vm/forensic")
    assert kwargs["json"] == {"acquisition_type": "memory"}
    assert kwargs["timeout"] >= 300
