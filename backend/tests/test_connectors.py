from unittest.mock import AsyncMock, patch

from app.core.security import hash_password
from app.db.models.user import User
from app.services.vm_client import VmAgentUnavailable

OS_CONFIG = {"host": "opensearch", "port": 9200, "username": "admin", "password": "admin", "ssl_enabled": False}


def make_user(db_session, email="ada@example.com"):
    user = User(
        first_name="Ada",
        last_name="Lovelace",
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
    assert client.get("/connectors").status_code == 401
    assert client.put("/connectors/opensearch", json=OS_CONFIG).status_code == 401
    assert client.get("/rule-payload-map").status_code == 401
    assert client.get("/payloads").status_code == 401


def test_unknown_connector_type_404(client, db_session):
    headers = login(client, make_user(db_session).email)
    assert client.put("/connectors/bogus", json=OS_CONFIG, headers=headers).status_code == 404


def test_save_get_delete_connector_never_leaks_password(client, db_session):
    headers = login(client, make_user(db_session).email)

    save_resp = client.put("/connectors/opensearch", json=OS_CONFIG, headers=headers)
    assert save_resp.status_code == 200
    assert save_resp.json()["config"]["password"] != "admin"

    list_resp = client.get("/connectors", headers=headers)
    statuses = {row["type"]: row for row in list_resp.json()}
    assert statuses["opensearch"]["configured"] is True
    assert statuses["opensearch"]["config"]["password"] != "admin"
    assert statuses["splunk"]["configured"] is False

    del_resp = client.delete("/connectors/opensearch", headers=headers)
    assert del_resp.status_code == 200
    list_resp = client.get("/connectors", headers=headers)
    assert {row["type"]: row for row in list_resp.json()}["opensearch"]["configured"] is False


def test_test_connector_dispatches_to_opensearch_client(client, db_session):
    headers = login(client, make_user(db_session).email)
    with patch("app.routers.connectors.opensearch_client.test_connection", new=AsyncMock(return_value=True)):
        resp = client.post("/connectors/opensearch/test", json=OS_CONFIG, headers=headers)
    assert resp.status_code == 200
    assert resp.json()["ok"] is True


def test_live_rules_requires_saved_config(client, db_session):
    headers = login(client, make_user(db_session).email)
    resp = client.get("/connectors/opensearch/rules/live", headers=headers)
    assert resp.status_code == 404


def test_live_rules_translates_connection_errors_to_502(client, db_session):
    headers = login(client, make_user(db_session).email)
    client.put("/connectors/opensearch", json=OS_CONFIG, headers=headers)
    with patch(
        "app.routers.connectors.opensearch_client.list_rules",
        new=AsyncMock(side_effect=ConnectionError("unreachable")),
    ):
        resp = client.get("/connectors/opensearch/rules/live", headers=headers)
    assert resp.status_code == 502


def test_sync_rules_replaces_previous_snapshot(client, db_session):
    headers = login(client, make_user(db_session).email)
    client.put("/connectors/opensearch", json=OS_CONFIG, headers=headers)

    first_batch = [{"id": "rule-1", "name": "Rule One"}]
    with patch("app.routers.connectors.opensearch_client.list_rules", new=AsyncMock(return_value=first_batch)):
        resp = client.post("/connectors/opensearch/rules/sync", headers=headers)
    assert resp.json() == {"synced_count": 1}

    persisted = client.get("/connectors/opensearch/rules", headers=headers).json()
    assert len(persisted) == 1
    assert persisted[0]["rule_id"] == "rule-1"

    second_batch = [{"id": "rule-2", "name": "Rule Two"}, {"id": "rule-3", "name": "Rule Three"}]
    with patch("app.routers.connectors.opensearch_client.list_rules", new=AsyncMock(return_value=second_batch)):
        resp = client.post("/connectors/opensearch/rules/sync", headers=headers)
    assert resp.json() == {"synced_count": 2}

    persisted = client.get("/connectors/opensearch/rules", headers=headers).json()
    assert {row["rule_id"] for row in persisted} == {"rule-2", "rule-3"}


def test_rule_payload_map_set_and_unset(client, db_session):
    headers = login(client, make_user(db_session).email)
    payload = client.post(
        "/payloads", json={"name": "p", "description": None, "code": "whoami"}, headers=headers
    ).json()

    set_resp = client.put("/rule-payload-map/rule-1", json={"payload_id": payload["id"]}, headers=headers)
    assert set_resp.status_code == 200
    assert set_resp.json() == {"rule_id": "rule-1", "payload_id": payload["id"]}

    listed = client.get("/rule-payload-map", headers=headers).json()
    assert listed == [{"rule_id": "rule-1", "payload_id": payload["id"]}]

    # Re-assigning the same rule must update, not duplicate.
    other_payload = client.post(
        "/payloads", json={"name": "p2", "description": None, "code": "id"}, headers=headers
    ).json()
    client.put("/rule-payload-map/rule-1", json={"payload_id": other_payload["id"]}, headers=headers)
    listed = client.get("/rule-payload-map", headers=headers).json()
    assert listed == [{"rule_id": "rule-1", "payload_id": other_payload["id"]}]

    unset_resp = client.put("/rule-payload-map/rule-1", json={"payload_id": None}, headers=headers)
    assert unset_resp.json() == {"rule_id": "rule-1", "payload_id": None}
    assert client.get("/rule-payload-map", headers=headers).json() == []


def test_payload_crud(client, db_session):
    headers = login(client, make_user(db_session).email)

    created = client.post(
        "/payloads", json={"name": "recon", "description": "desc", "code": "whoami"}, headers=headers
    )
    assert created.status_code == 200
    payload_id = created.json()["id"]

    listed = client.get("/payloads", headers=headers).json()
    assert len(listed) == 1

    updated = client.put(
        f"/payloads/{payload_id}",
        json={"name": "recon2", "description": "desc2", "code": "hostname"},
        headers=headers,
    )
    assert updated.json()["name"] == "recon2"

    client.delete(f"/payloads/{payload_id}", headers=headers)
    assert client.get(f"/payloads/{payload_id}", headers=headers).status_code == 404


def test_execute_payload_success(client, db_session):
    headers = login(client, make_user(db_session).email)
    payload = client.post(
        "/payloads", json={"name": "p", "description": None, "code": "whoami"}, headers=headers
    ).json()

    with patch("app.routers.connectors.agent_execute_payload", new=AsyncMock(return_value={"message": "ok"})):
        resp = client.post(f"/payloads/{payload['id']}/execute", headers=headers)
    assert resp.status_code == 200
    assert resp.json()["status"] == "success"


def test_execute_payload_reports_agent_unavailable(client, db_session):
    headers = login(client, make_user(db_session).email)
    payload = client.post(
        "/payloads", json={"name": "p", "description": None, "code": "whoami"}, headers=headers
    ).json()

    with patch(
        "app.routers.connectors.agent_execute_payload",
        new=AsyncMock(side_effect=VmAgentUnavailable("down")),
    ):
        resp = client.post(f"/payloads/{payload['id']}/execute", headers=headers)
    assert resp.status_code == 200
    assert resp.json()["status"] == "error"


def test_execute_payload_404_for_unknown_id(client, db_session):
    headers = login(client, make_user(db_session).email)
    resp = client.post("/payloads/999/execute", headers=headers)
    assert resp.status_code == 404


def test_execute_payload_persists_history_with_context(client, db_session):
    headers = login(client, make_user(db_session).email)
    payload = client.post(
        "/payloads", json={"name": "p", "description": None, "code": "whoami"}, headers=headers
    ).json()

    with patch(
        "app.routers.connectors.agent_execute_payload",
        new=AsyncMock(return_value={"message": "ok", "output": "purplelab\\ada"}),
    ):
        resp = client.post(
            f"/payloads/{payload['id']}/execute",
            json={"rule_id": "rule-42", "connector": "splunk"},
            headers=headers,
        )
    assert resp.status_code == 200

    history = client.get("/execution-history", headers=headers).json()
    assert len(history) == 1
    entry = history[0]
    assert entry["payload_id"] == payload["id"]
    assert entry["rule_id"] == "rule-42"
    assert entry["connector"] == "splunk"
    assert entry["status"] == "success"
    assert entry["output"] == "purplelab\\ada"
    assert entry["source"] == "rule_payload"


def test_execute_payload_persists_failure_too(client, db_session):
    headers = login(client, make_user(db_session).email)
    payload = client.post(
        "/payloads", json={"name": "p", "description": None, "code": "whoami"}, headers=headers
    ).json()

    with patch(
        "app.routers.connectors.agent_execute_payload",
        new=AsyncMock(side_effect=VmAgentUnavailable("down")),
    ):
        client.post(f"/payloads/{payload['id']}/execute", headers=headers)

    history = client.get("/execution-history", headers=headers).json()
    assert history[0]["status"] == "error"
    assert history[0]["error"] == "down"


def test_deleting_payload_does_not_break_when_history_references_it(client, db_session):
    # The payload_id -> rule_lifecycle_payloads.id FK constraint is
    # ON DELETE SET NULL on the Postgres side (validated live separately
    # — SQLite does not enforce FK constraints by default, so this test
    # only verifies that the route never breaks, not the exact value
    # Postgres would put in payload_id afterward).
    headers = login(client, make_user(db_session).email)
    payload = client.post(
        "/payloads", json={"name": "p", "description": None, "code": "whoami"}, headers=headers
    ).json()

    with patch("app.routers.connectors.agent_execute_payload", new=AsyncMock(return_value={"message": "ok"})):
        client.post(f"/payloads/{payload['id']}/execute", headers=headers)

    delete_resp = client.delete(f"/payloads/{payload['id']}", headers=headers)
    assert delete_resp.status_code == 200

    history = client.get("/execution-history", headers=headers).json()
    assert len(history) == 1


def test_execution_history_filters_by_connector_and_status(client, db_session):
    headers = login(client, make_user(db_session).email)
    payload = client.post(
        "/payloads", json={"name": "p", "description": None, "code": "whoami"}, headers=headers
    ).json()

    with patch("app.routers.connectors.agent_execute_payload", new=AsyncMock(return_value={"message": "ok"})):
        client.post(
            f"/payloads/{payload['id']}/execute", json={"connector": "splunk"}, headers=headers
        )
    with patch(
        "app.routers.connectors.agent_execute_payload", new=AsyncMock(side_effect=VmAgentUnavailable("down"))
    ):
        client.post(
            f"/payloads/{payload['id']}/execute", json={"connector": "opensearch"}, headers=headers
        )

    splunk_only = client.get("/execution-history", params={"connector": "splunk"}, headers=headers).json()
    assert len(splunk_only) == 1
    assert splunk_only[0]["connector"] == "splunk"

    errors_only = client.get("/execution-history", params={"status": "error"}, headers=headers).json()
    assert len(errors_only) == 1
    assert errors_only[0]["connector"] == "opensearch"


def test_execution_history_filters_by_source(client, db_session):
    from app.db.models.execution_history import ExecutionHistory

    headers = login(client, make_user(db_session).email)
    db_session.add_all(
        [
            ExecutionHistory(source="rule_payload", rule_id="r1", status="success"),
            ExecutionHistory(source="atomic_test", rule_id="T1059", status="success"),
        ]
    )
    db_session.commit()
    rows = client.get("/execution-history", params={"source": "rule_payload"}, headers=headers).json()
    assert [r["rule_id"] for r in rows] == ["r1"]
    assert len(client.get("/execution-history", headers=headers).json()) == 2
