from unittest.mock import AsyncMock, patch

import pytest

from app.core.security import hash_password
from app.db.models.user import User
from app.services.vm_client import VmAgentUnavailable


def make_user(db_session, email="ada@example.com", first_name="Ada", last_name="Lovelace"):
    user = User(
        first_name=first_name,
        last_name=last_name,
        email=email,
        analyst_level="n1",
        avatar="/avatar.png",
        password=hash_password("CorrectHorseBatteryStaple"),
        role="analyst",
    )
    db_session.add(user)
    db_session.commit()
    db_session.refresh(user)
    return user


def login(client, email):
    resp = client.post("/auth/login", json={"email": email, "password": "CorrectHorseBatteryStaple"})
    return {"Authorization": f"Bearer {resp.json()['access_token']}"}


def test_routes_require_auth(client):
    assert client.get("/custom-payloads").status_code == 401
    assert client.post("/custom-payloads", json={"name": "x", "content": "y"}).status_code == 401
    assert client.delete("/custom-payloads/1").status_code == 401
    assert client.post("/custom-payloads/1/execute").status_code == 401


def test_create_and_list_payload(client, db_session):
    user = make_user(db_session)
    headers = login(client, user.email)

    create_resp = client.post("/custom-payloads", json={"name": "whoami", "content": "whoami"}, headers=headers)
    assert create_resp.status_code == 200
    assert create_resp.json()["author_first_name"] == "Ada"

    list_resp = client.get("/custom-payloads", headers=headers)
    assert list_resp.status_code == 200
    assert len(list_resp.json()) == 1
    assert list_resp.json()[0]["name"] == "whoami"


def test_list_shows_payloads_from_all_users(client, db_session):
    ada = make_user(db_session, email="ada@example.com")
    bob = make_user(db_session, email="bob@example.com", first_name="Bob", last_name="Smith")
    ada_headers = login(client, ada.email)
    bob_headers = login(client, bob.email)

    client.post("/custom-payloads", json={"name": "ada-payload", "content": "x"}, headers=ada_headers)
    client.post("/custom-payloads", json={"name": "bob-payload", "content": "y"}, headers=bob_headers)

    resp = client.get("/custom-payloads", headers=ada_headers)
    names = {p["name"] for p in resp.json()}
    assert names == {"ada-payload", "bob-payload"}


def test_delete_only_own_payload(client, db_session):
    ada = make_user(db_session, email="ada@example.com")
    bob = make_user(db_session, email="bob@example.com", first_name="Bob", last_name="Smith")
    ada_headers = login(client, ada.email)
    bob_headers = login(client, bob.email)

    created = client.post("/custom-payloads", json={"name": "ada-payload", "content": "x"}, headers=ada_headers)
    payload_id = created.json()["id"]

    # bob tries to delete ada's payload: no explicit error, but the
    # payload must remain (non-leaking behavior of the original SQL).
    bob_delete = client.delete(f"/custom-payloads/{payload_id}", headers=bob_headers)
    assert bob_delete.status_code == 200
    still_there = client.get("/custom-payloads", headers=ada_headers)
    assert len(still_there.json()) == 1

    ada_delete = client.delete(f"/custom-payloads/{payload_id}", headers=ada_headers)
    assert ada_delete.status_code == 200
    gone = client.get("/custom-payloads", headers=ada_headers)
    assert len(gone.json()) == 0


def test_execute_requires_ownership(client, db_session):
    ada = make_user(db_session, email="ada@example.com")
    bob = make_user(db_session, email="bob@example.com", first_name="Bob", last_name="Smith")
    ada_headers = login(client, ada.email)
    bob_headers = login(client, bob.email)

    created = client.post("/custom-payloads", json={"name": "ada-payload", "content": "whoami"}, headers=ada_headers)
    payload_id = created.json()["id"]

    bob_exec = client.post(f"/custom-payloads/{payload_id}/execute", headers=bob_headers)
    assert bob_exec.status_code == 404

    with patch("app.routers.custom_payloads.agent_execute_payload", new=AsyncMock(return_value={"message": "ok"})):
        ada_exec = client.post(f"/custom-payloads/{payload_id}/execute", headers=ada_headers)
    assert ada_exec.status_code == 200
    assert ada_exec.json()["status"] == "success"


def test_execute_reports_agent_unavailable(client, db_session):
    user = make_user(db_session)
    headers = login(client, user.email)
    created = client.post("/custom-payloads", json={"name": "p", "content": "whoami"}, headers=headers)
    payload_id = created.json()["id"]

    with patch(
        "app.routers.custom_payloads.agent_execute_payload",
        new=AsyncMock(side_effect=VmAgentUnavailable("down")),
    ):
        resp = client.post(f"/custom-payloads/{payload_id}/execute", headers=headers)
    assert resp.status_code == 200
    assert resp.json()["status"] == "error"
