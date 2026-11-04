from unittest.mock import AsyncMock, patch

from fastapi.testclient import TestClient

from app.core.security import hash_password
from app.db.models.user import User
from app.db.session import get_db
from app.main import app
from app.services.vm_client import VmAgentUnavailable


def test_health_db_ok_agent_ok(client):
    with patch("app.routers.health.get_vm_info", new=AsyncMock(return_value={"state": "poweroff"})):
        response = client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"db": "ok", "vm_agent": "ok"}


def test_health_agent_down_does_not_crash(client):
    with patch(
        "app.routers.health.get_vm_info",
        new=AsyncMock(side_effect=VmAgentUnavailable("connection refused")),
    ):
        response = client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"db": "ok", "vm_agent": "down"}


def test_health_db_down_does_not_crash():
    class BrokenSession:
        def execute(self, *_args, **_kwargs):
            raise RuntimeError("connection refused")

    def override_get_db():
        yield BrokenSession()

    app.dependency_overrides[get_db] = override_get_db
    try:
        with patch("app.routers.health.get_vm_info", new=AsyncMock(return_value={"state": "poweroff"})):
            with TestClient(app) as test_client:
                response = test_client.get("/health")
    finally:
        app.dependency_overrides.clear()

    assert response.status_code == 200
    assert response.json() == {"db": "down", "vm_agent": "ok"}


def test_health_system_requires_auth(client):
    assert client.get("/health/system").status_code == 401


def test_health_system_ok(client, db_session):
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
    headers = {"Authorization": f"Bearer {login.json()['access_token']}"}

    fake_metrics = {"memory": {"percent": 42.0}, "disk": {"percent": 61.0}, "cpu": {"percent": 13.0}}
    with patch("app.routers.health.get_host_system", new=AsyncMock(return_value=fake_metrics)):
        response = client.get("/health/system", headers=headers)
    assert response.status_code == 200
    assert response.json() == fake_metrics


def test_health_system_502_when_agent_unavailable(client, db_session):
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
    headers = {"Authorization": f"Bearer {login.json()['access_token']}"}

    with patch("app.routers.health.get_host_system", new=AsyncMock(side_effect=VmAgentUnavailable("down"))):
        response = client.get("/health/system", headers=headers)
    assert response.status_code == 502


def _auth_headers(client, db_session):
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
    return {"Authorization": f"Bearer {login.json()['access_token']}"}


def test_event_count_requires_auth(client):
    assert client.get("/health/event-count").status_code == 401


def test_event_count_ok(client, db_session):
    headers = _auth_headers(client, db_session)
    fake_response = AsyncMock()
    fake_response.status_code = 200
    fake_response.json = lambda: {"count": 4211}
    with patch("httpx.AsyncClient.get", new=AsyncMock(return_value=fake_response)):
        response = client.get("/health/event-count", headers=headers)
    assert response.status_code == 200
    assert response.json() == {"count": 4211}


def test_event_count_missing_index_is_zero_not_error(client, db_session):
    headers = _auth_headers(client, db_session)
    fake_response = AsyncMock()
    fake_response.status_code = 404
    with patch("httpx.AsyncClient.get", new=AsyncMock(return_value=fake_response)):
        response = client.get("/health/event-count", headers=headers)
    assert response.status_code == 200
    assert response.json() == {"count": 0}


def test_event_count_unreachable_is_502(client, db_session):
    import httpx

    headers = _auth_headers(client, db_session)
    with patch("httpx.AsyncClient.get", new=AsyncMock(side_effect=httpx.ConnectError("refused"))):
        response = client.get("/health/event-count", headers=headers)
    assert response.status_code == 502
