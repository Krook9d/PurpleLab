from unittest.mock import AsyncMock, patch

from app.core.security import hash_password
from app.db.models.user import User

CONFIG_BODY = {"provider": "openai", "model": "gpt-x", "api_base": None, "api_key": "sk-abcd1234efgh5678"}


def make_user(db_session, email="ada@example.com", role="analyst"):
    user = User(
        first_name="Ada",
        last_name="Lovelace",
        email=email,
        analyst_level="n1",
        avatar="/avatar.png",
        password=hash_password("CorrectHorseBatteryStaple"),
        role=role,
    )
    db_session.add(user)
    db_session.commit()
    db_session.refresh(user)
    return user


def login(client, email):
    resp = client.post("/auth/login", json={"email": email, "password": "CorrectHorseBatteryStaple"})
    return {"Authorization": f"Bearer {resp.json()['access_token']}"}


def test_routes_require_auth(client):
    assert client.get("/mitre-ai/config").status_code == 401
    assert client.put("/mitre-ai/config", json=CONFIG_BODY).status_code == 401
    assert client.post("/mitre-ai/chat", json={"messages": []}).status_code == 401


def test_config_routes_require_admin(client, db_session):
    headers = login(client, make_user(db_session, role="analyst").email)
    assert client.get("/mitre-ai/config", headers=headers).status_code == 403
    assert client.put("/mitre-ai/config", json=CONFIG_BODY, headers=headers).status_code == 403
    assert client.delete("/mitre-ai/config", headers=headers).status_code == 403


def test_save_config_rejects_failed_connection_test(client, db_session):
    headers = login(client, make_user(db_session, role="admin").email)
    with patch("app.routers.mitre_ai.mitre_ai_service.test_connection", new=AsyncMock(return_value=False)):
        resp = client.put("/mitre-ai/config", json=CONFIG_BODY, headers=headers)
    assert resp.status_code == 400


def test_save_config_never_leaks_raw_key(client, db_session):
    headers = login(client, make_user(db_session, role="admin").email)
    with patch("app.routers.mitre_ai.mitre_ai_service.test_connection", new=AsyncMock(return_value=True)):
        resp = client.put("/mitre-ai/config", json=CONFIG_BODY, headers=headers)
    assert resp.status_code == 200
    assert CONFIG_BODY["api_key"] not in resp.text
    assert resp.json()["key_preview"] == "sk-a••••5678"

    get_resp = client.get("/mitre-ai/config", headers=headers)
    assert get_resp.json()["provider"] == "openai"
    assert get_resp.json()["key_preview"] == "sk-a••••5678"


def test_chat_requires_configuration(client, db_session):
    headers = login(client, make_user(db_session).email)
    resp = client.post("/mitre-ai/chat", json={"messages": [{"role": "user", "content": "hi"}]}, headers=headers)
    assert resp.status_code == 502


def test_chat_returns_provider_reply(client, db_session):
    admin_headers = login(client, make_user(db_session, email="admin@example.com", role="admin").email)
    with patch("app.routers.mitre_ai.mitre_ai_service.test_connection", new=AsyncMock(return_value=True)):
        client.put("/mitre-ai/config", json=CONFIG_BODY, headers=admin_headers)

    analyst_headers = login(client, make_user(db_session, email="bob@example.com").email)
    with patch("app.services.mitre_ai_service.call_provider", new=AsyncMock(return_value="hello there")):
        resp = client.post(
            "/mitre-ai/chat", json={"messages": [{"role": "user", "content": "hi"}]}, headers=analyst_headers
        )
    assert resp.status_code == 200
    assert resp.json()["message"] == {"role": "assistant", "content": "hello there"}
