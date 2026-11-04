from unittest.mock import AsyncMock, patch

from app.core.security import hash_password
from app.db.models.atomic_test import AtomicTest
from app.db.models.execution_history import ExecutionHistory
from app.db.models.mitre_technique import MitreTechnique
from app.db.models.user import User
from app.services.vm_client import VmAgentUnavailable


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


def seed_technique(db_session, technique_id="T1566", name="Phishing"):
    technique = MitreTechnique(id=technique_id, name=name, description="desc", tactics="Initial Access")
    db_session.add(technique)
    db_session.commit()
    return technique


def test_routes_require_auth(client):
    assert client.get("/mitre/techniques?q=phish").status_code == 401
    assert client.get("/mitre/techniques/T1566").status_code == 401
    assert client.get("/mitre/stats").status_code == 401
    assert client.post("/mitre/refresh").status_code == 401
    assert client.post("/mitre/execute-atomic/T1566").status_code == 401
    assert client.get("/mitre/matrix").status_code == 401


def test_search_requires_two_characters(client, db_session):
    headers = login(client, make_user(db_session).email)
    seed_technique(db_session)
    assert client.get("/mitre/techniques?q=p", headers=headers).json() == []
    resp = client.get("/mitre/techniques?q=phish", headers=headers)
    assert resp.json() == [
        {"id": "T1566", "name": "Phishing", "tactics": "Initial Access", "is_sub_technique": None}
    ]


def test_search_matches_name_not_just_id(client, db_session):
    headers = login(client, make_user(db_session).email)
    seed_technique(db_session, technique_id="T1566", name="Phishing")
    resp = client.get("/mitre/techniques?q=Phishing", headers=headers)
    assert [row["id"] for row in resp.json()] == ["T1566"]


def test_get_technique_404(client, db_session):
    headers = login(client, make_user(db_session).email)
    assert client.get("/mitre/techniques/T9999", headers=headers).status_code == 404


def test_get_technique_detail(client, db_session):
    headers = login(client, make_user(db_session).email)
    seed_technique(db_session)
    resp = client.get("/mitre/techniques/T1566", headers=headers)
    assert resp.status_code == 200
    assert resp.json()["name"] == "Phishing"


def test_get_atomic_tests(client, db_session):
    headers = login(client, make_user(db_session).email)
    db_session.add(AtomicTest(technique_id="T1566", technique_name="Phishing", test_name="Test 1", test="1"))
    db_session.commit()
    resp = client.get("/mitre/techniques/T1566/atomic-tests", headers=headers)
    assert resp.status_code == 200
    assert len(resp.json()) == 1
    assert resp.json()[0]["test_name"] == "Test 1"


def test_stats_counts_mitre_techniques(client, db_session):
    # technique_count reflects known MITRE ATT&CK techniques
    # (mitre_techniques), not Atomic Red Team test coverage — a technique
    # with zero atomic tests still counts, and duplicate atomic tests for
    # the same technique must not inflate it.
    headers = login(client, make_user(db_session).email)
    seed_technique(db_session, technique_id="T1566", name="Phishing")
    seed_technique(db_session, technique_id="T1059", name="Command and Scripting Interpreter")
    db_session.add_all(
        [
            AtomicTest(technique_id="T1566", test_name="a"),
            AtomicTest(technique_id="T1566", test_name="b"),
        ]
    )
    db_session.commit()
    resp = client.get("/mitre/stats", headers=headers)
    assert resp.json() == {"technique_count": 2}


def test_atomic_tests_by_tactic(client, db_session):
    headers = login(client, make_user(db_session).email)
    db_session.add_all(
        [
            AtomicTest(technique_id="T1566", tactic="Initial Access"),
            AtomicTest(technique_id="T1598", tactic="Initial Access"),
            AtomicTest(technique_id="T1059", tactic="Execution"),
        ]
    )
    db_session.commit()
    resp = client.get("/mitre/atomic-tests/by-tactic", headers=headers)
    assert resp.status_code == 200
    assert {"tactic": "Initial Access", "count": 2} in resp.json()
    assert {"tactic": "Execution", "count": 1} in resp.json()


def test_refresh_calls_service(client, db_session):
    headers = login(client, make_user(db_session).email)
    with patch(
        "app.routers.mitre.mitre_service.refresh",
        return_value={"created": 5, "updated": 2, "total": 7},
    ):
        resp = client.post("/mitre/refresh", headers=headers)
    assert resp.status_code == 200
    assert resp.json() == {"created": 5, "updated": 2, "total": 7}


def test_execute_atomic_success(client, db_session):
    headers = login(client, make_user(db_session).email)
    with patch(
        "app.routers.mitre.agent_execute_atomic_test", new=AsyncMock(return_value={"message": "ok"})
    ) as mock_fn:
        resp = client.post("/mitre/execute-atomic/T1566", json={"test_number": 2}, headers=headers)
    assert resp.status_code == 200
    assert resp.json()["status"] == "success"
    mock_fn.assert_called_once_with("T1566", 2)


def test_execute_atomic_surfaces_real_guest_output(client, db_session):
    headers = login(client, make_user(db_session).email)
    with patch(
        "app.routers.mitre.agent_execute_atomic_test",
        new=AsyncMock(return_value={"message": "ok", "output": "PathToAtomicsFolder ...\nDone.\n"}),
    ):
        resp = client.post("/mitre/execute-atomic/T1566", headers=headers)
    assert resp.json()["output"] == "PathToAtomicsFolder ...\nDone.\n"


def test_execute_atomic_reports_agent_unavailable(client, db_session):
    headers = login(client, make_user(db_session).email)
    with patch(
        "app.routers.mitre.agent_execute_atomic_test",
        new=AsyncMock(side_effect=VmAgentUnavailable("down")),
    ):
        resp = client.post("/mitre/execute-atomic/T1566", headers=headers)
    assert resp.status_code == 200
    assert resp.json()["status"] == "error"


def test_execute_atomic_records_history(client, db_session):
    headers = login(client, make_user(db_session).email)
    with patch("app.routers.mitre.agent_execute_atomic_test", new=AsyncMock(return_value={"output": "Done."})):
        client.post("/mitre/execute-atomic/T1059.001", json={"test_number": 1}, headers=headers)
    with patch(
        "app.routers.mitre.agent_execute_atomic_test", new=AsyncMock(side_effect=VmAgentUnavailable("down"))
    ):
        client.post("/mitre/execute-atomic/T1059.001", headers=headers)

    rows = db_session.query(ExecutionHistory).order_by(ExecutionHistory.id).all()
    assert [(r.source, r.rule_id, r.status) for r in rows] == [
        ("atomic_test", "T1059.001", "success"),
        ("atomic_test", "T1059.001", "error"),
    ]
    assert rows[0].output == "Done."
    assert rows[1].error == "down"


def test_matrix_returns_every_technique_with_coverage(client, db_session):
    headers = login(client, make_user(db_session).email)
    seed_technique(db_session, technique_id="T1059", name="Command and Scripting Interpreter")
    db_session.add(
        MitreTechnique(id="T1059.001", name="PowerShell", tactics="Execution", is_sub_technique=True)
    )
    db_session.add_all(
        [
            AtomicTest(technique_id="T1059.001", test="1"),
            AtomicTest(technique_id="T1059.001", test="2"),
            ExecutionHistory(source="atomic_test", rule_id="T1059.001", status="success"),
            ExecutionHistory(source="atomic_test", rule_id="T1059.001", status="error"),
            # Rule lifecycle runs never count as technique executions.
            ExecutionHistory(source="rule_payload", rule_id="T1059", status="success"),
        ]
    )
    db_session.commit()

    with patch(
        "app.services.mitre_service.sigma_service.technique_rule_counts", return_value={"T1059": 4, "T1059.001": 7}
    ):
        resp = client.get("/mitre/matrix", headers=headers)

    assert resp.status_code == 200
    by_id = {row["id"]: row for row in resp.json()}
    assert by_id["T1059"]["atomic_tests"] == 0
    assert by_id["T1059"]["sigma_rules"] == 4
    assert by_id["T1059"]["executions"] == 0
    assert by_id["T1059"]["last_execution_status"] is None
    assert by_id["T1059.001"]["atomic_tests"] == 2
    assert by_id["T1059.001"]["sigma_rules"] == 7
    assert by_id["T1059.001"]["executions"] == 2
    assert by_id["T1059.001"]["last_execution_status"] == "error"
    assert by_id["T1059.001"]["is_sub_technique"] is True
