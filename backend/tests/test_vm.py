from unittest.mock import AsyncMock, patch

import pytest

from app.core.security import hash_password
from app.db.models.user import User
from app.services.vm_client import VmAgentUnavailable


@pytest.fixture
def auth_headers(client, db_session):
    user = User(
        first_name="Ada",
        last_name="Lovelace",
        email="ada@example.com",
        analyst_level="n1",
        avatar="/avatar.png",
        password=hash_password("CorrectHorseBatteryStaple"),
        role="analyst",
    )
    db_session.add(user)
    db_session.commit()

    login = client.post("/auth/login", json={"email": "ada@example.com", "password": "CorrectHorseBatteryStaple"})
    token = login.json()["access_token"]
    return {"Authorization": f"Bearer {token}"}


def test_vm_routes_require_auth(client):
    assert client.get("/vm/state").status_code == 401
    assert client.post("/vm/poweroff").status_code == 401
    assert client.post("/vm/restore").status_code == 401
    assert client.post("/vm/start").status_code == 401
    assert client.post("/vm/enable-av").status_code == 401
    assert client.post("/vm/disable-av").status_code == 401
    assert client.post("/vm/restart-winlogbeat").status_code == 401


def test_vm_state_combines_info_and_ip(client, auth_headers):
    with patch(
        "app.routers.vm.vm_client.get_vm_info",
        new=AsyncMock(return_value={"name": "sandbox", "state": "poweroff", "snapshot": "Snapshot1"}),
    ), patch("app.routers.vm.vm_client.get_vm_ip", new=AsyncMock(return_value={"ip": "192.168.56.10"})):
        response = client.get("/vm/state", headers=auth_headers)

    assert response.status_code == 200
    assert response.json() == {
        "name": "sandbox",
        "state": "poweroff",
        "snapshot": "Snapshot1",
        "ip": "192.168.56.10",
    }


def test_vm_state_502_when_agent_unavailable(client, auth_headers):
    with patch("app.routers.vm.vm_client.get_vm_info", new=AsyncMock(side_effect=VmAgentUnavailable("down"))):
        response = client.get("/vm/state", headers=auth_headers)

    assert response.status_code == 502


@pytest.mark.parametrize(
    "path,client_fn",
    [
        ("/vm/poweroff", "poweroff"),
        ("/vm/restore", "restore"),
        ("/vm/start", "start_headless"),
        ("/vm/enable-av", "enable_av"),
        ("/vm/disable-av", "disable_av"),
        ("/vm/restart-winlogbeat", "restart_winlogbeat"),
    ],
)
def test_vm_action_success(client, auth_headers, path, client_fn):
    with patch(f"app.routers.vm.vm_client.{client_fn}", new=AsyncMock(return_value={"message": "ok"})):
        response = client.post(path, headers=auth_headers)

    assert response.status_code == 200
    assert "message" in response.json()


@pytest.mark.parametrize(
    "path,client_fn",
    [
        ("/vm/poweroff", "poweroff"),
        ("/vm/restore", "restore"),
        ("/vm/start", "start_headless"),
    ],
)
def test_vm_action_502_when_agent_unavailable(client, auth_headers, path, client_fn):
    with patch(f"app.routers.vm.vm_client.{client_fn}", new=AsyncMock(side_effect=VmAgentUnavailable("down"))):
        response = client.post(path, headers=auth_headers)

    assert response.status_code == 502


def test_execute_payload_requires_auth(client):
    response = client.post("/vm/execute-payload", json={"content": "whoami"})
    assert response.status_code == 401


def test_execute_payload_success(client, auth_headers):
    with patch("app.routers.vm.vm_client.execute_payload", new=AsyncMock(return_value={"message": "ok"})) as mock_fn:
        response = client.post("/vm/execute-payload", json={"content": "whoami"}, headers=auth_headers)

    assert response.status_code == 200
    mock_fn.assert_called_once_with("whoami")


def test_execute_payload_502_when_agent_unavailable(client, auth_headers):
    with patch("app.routers.vm.vm_client.execute_payload", new=AsyncMock(side_effect=VmAgentUnavailable("down"))):
        response = client.post("/vm/execute-payload", json={"content": "whoami"}, headers=auth_headers)

    assert response.status_code == 502


def test_forensic_requires_auth(client):
    response = client.post("/vm/forensic", json={"acquisition_type": "memory"})
    assert response.status_code == 401


def test_forensic_memory_success(client, auth_headers):
    with patch(
        "app.routers.vm.vm_client.forensic_acquisition",
        new=AsyncMock(return_value={"message": "ok", "output": "/opt/purplelab/data/forensic/sandbox.dmp"}),
    ) as mock_fn:
        response = client.post("/vm/forensic", json={"acquisition_type": "memory"}, headers=auth_headers)

    assert response.status_code == 200
    assert response.json()["output_path"] == "/opt/purplelab/data/forensic/sandbox.dmp"
    mock_fn.assert_called_once_with("memory")


def test_forensic_rejects_unknown_acquisition_type(client, auth_headers):
    response = client.post("/vm/forensic", json={"acquisition_type": "bogus"}, headers=auth_headers)
    assert response.status_code == 422


def test_forensic_502_when_agent_unavailable(client, auth_headers):
    with patch(
        "app.routers.vm.vm_client.forensic_acquisition", new=AsyncMock(side_effect=VmAgentUnavailable("down"))
    ):
        response = client.post("/vm/forensic", json={"acquisition_type": "disk"}, headers=auth_headers)

    assert response.status_code == 502
