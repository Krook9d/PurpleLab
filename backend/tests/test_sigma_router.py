from unittest.mock import patch

import pytest

from app.core.security import hash_password
from app.db.models.user import User
from app.services.sigma_service import SigmaServiceError


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


def test_sigma_routes_require_auth(client):
    assert client.get("/sigma/browse").status_code == 401
    assert client.get("/sigma/search", params={"q": "x"}).status_code == 401
    assert client.get("/sigma/rule", params={"path": "x.yml"}).status_code == 401
    assert client.post("/sigma/convert", json={"rule_path": "x.yml", "plugin": "splunk"}).status_code == 401
    assert client.post("/sigma/update").status_code == 401
    assert client.get("/sigma/stats").status_code == 401


def test_stats(client, auth_headers):
    fake_stats = {"total": 3, "by_category": [{"folder": "windows", "count": 3}]}
    with patch("app.routers.sigma.sigma_service.stats", return_value=fake_stats):
        response = client.get("/sigma/stats", headers=auth_headers)
    assert response.status_code == 200
    assert response.json() == fake_stats


def test_browse_success(client, auth_headers):
    with patch(
        "app.routers.sigma.sigma_service.browse_folder",
        return_value={"folders": ["windows"], "files": [], "rule_count": 3},
    ):
        response = client.get("/sigma/browse", headers=auth_headers)
    assert response.status_code == 200
    assert response.json() == {"folders": ["windows"], "files": [], "rule_count": 3}


def test_browse_404_when_folder_missing(client, auth_headers):
    with patch("app.routers.sigma.sigma_service.browse_folder", side_effect=SigmaServiceError("folder not found")):
        response = client.get("/sigma/browse", params={"folder": "nope"}, headers=auth_headers)
    assert response.status_code == 404


def test_search(client, auth_headers):
    with patch("app.routers.sigma.sigma_service.search_rules", return_value=["windows/test.yml"]):
        response = client.get("/sigma/search", params={"q": "whoami"}, headers=auth_headers)
    assert response.status_code == 200
    assert response.json() == {"matches": ["windows/test.yml"]}


def test_rule_content(client, auth_headers):
    with patch("app.routers.sigma.sigma_service.get_rule_content", return_value="title: Test\n"):
        response = client.get("/sigma/rule", params={"path": "windows/test.yml"}, headers=auth_headers)
    assert response.status_code == 200
    assert response.json() == {"path": "windows/test.yml", "content": "title: Test\n"}


def test_rule_content_404(client, auth_headers):
    with patch("app.routers.sigma.sigma_service.get_rule_content", side_effect=SigmaServiceError("rule not found")):
        response = client.get("/sigma/rule", params={"path": "nope.yml"}, headers=auth_headers)
    assert response.status_code == 404


def test_convert_success(client, auth_headers):
    with patch("app.routers.sigma.sigma_service.convert_rule", return_value="Image=*whoami.exe"):
        response = client.post(
            "/sigma/convert",
            json={"rule_path": "windows/test.yml", "plugin": "splunk"},
            headers=auth_headers,
        )
    assert response.status_code == 200
    assert response.json() == {"output": "Image=*whoami.exe"}


def test_convert_rejects_bad_plugin(client, auth_headers):
    with patch(
        "app.routers.sigma.sigma_service.convert_rule",
        side_effect=SigmaServiceError("unsupported plugin: evil"),
    ):
        response = client.post(
            "/sigma/convert",
            json={"rule_path": "windows/test.yml", "plugin": "evil"},
            headers=auth_headers,
        )
    assert response.status_code == 400


def test_update_success(client, auth_headers):
    with patch("app.routers.sigma.sigma_service.update_rules", return_value=42):
        response = client.post("/sigma/update", headers=auth_headers)
    assert response.status_code == 200
    assert response.json() == {"rule_count": 42}


def test_update_502_on_failure(client, auth_headers):
    with patch("app.routers.sigma.sigma_service.update_rules", side_effect=SigmaServiceError("git clone failed")):
        response = client.post("/sigma/update", headers=auth_headers)
    assert response.status_code == 502
