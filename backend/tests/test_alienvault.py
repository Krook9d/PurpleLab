import asyncio
from unittest.mock import AsyncMock, MagicMock, patch

import httpx

from app.core.security import hash_password
from app.db.models.alienvault_dashboard import AlienvaultDashboardCache
from app.db.models.user import User
from app.services.alienvault_service import AlienvaultError, _get_pulse_enrichment


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
    assert client.get("/alienvault/dashboard").status_code == 401
    assert client.get("/alienvault/config").status_code == 401
    assert client.put("/alienvault/config", json={"api_key": "x"}).status_code == 401
    assert client.post("/alienvault/refresh").status_code == 401


def test_config_routes_require_admin(client, db_session):
    headers = login(client, make_user(db_session, role="analyst").email)
    assert client.get("/alienvault/config", headers=headers).status_code == 403
    assert client.put("/alienvault/config", json={"api_key": "x"}, headers=headers).status_code == 403
    assert client.delete("/alienvault/config", headers=headers).status_code == 403
    assert client.post("/alienvault/refresh", headers=headers).status_code == 403


def test_save_config_rejects_invalid_key(client, db_session):
    headers = login(client, make_user(db_session, role="admin").email)
    with patch("app.routers.alienvault.test_api_key", new=AsyncMock(return_value=False)):
        resp = client.put("/alienvault/config", json={"api_key": "bad-key"}, headers=headers)
    assert resp.status_code == 400


def test_save_config_never_leaks_raw_key(client, db_session):
    headers = login(client, make_user(db_session, role="admin").email)
    with patch("app.routers.alienvault.test_api_key", new=AsyncMock(return_value=True)):
        resp = client.put("/alienvault/config", json={"api_key": "abcd1234efgh5678"}, headers=headers)
    assert resp.status_code == 200
    body = resp.json()
    assert body["configured"] is True
    assert "abcd1234efgh5678" not in resp.text
    assert body["key_preview"] == "abcd••••5678"

    get_resp = client.get("/alienvault/config", headers=headers)
    assert get_resp.json()["key_preview"] == "abcd••••5678"


def test_delete_config(client, db_session):
    headers = login(client, make_user(db_session, role="admin").email)
    with patch("app.routers.alienvault.test_api_key", new=AsyncMock(return_value=True)):
        client.put("/alienvault/config", json={"api_key": "abcd1234efgh5678"}, headers=headers)
    client.delete("/alienvault/config", headers=headers)
    assert client.get("/alienvault/config", headers=headers).json()["configured"] is False


def test_refresh_requires_configured_key(client, db_session):
    headers = login(client, make_user(db_session, role="admin").email)
    resp = client.post("/alienvault/refresh", headers=headers)
    assert resp.status_code == 400


def test_refresh_accepted_when_configured(client, db_session):
    headers = login(client, make_user(db_session, role="admin").email)
    with patch("app.routers.alienvault.test_api_key", new=AsyncMock(return_value=True)):
        client.put("/alienvault/config", json={"api_key": "abcd1234efgh5678"}, headers=headers)

    # TestClient runs BackgroundTasks before returning control: we mock
    # SessionLocal (otherwise the background task would open a real
    # Postgres connection, absent in the test environment) and
    # generate_dashboard_data (no real OTX API call here, covered
    # separately by test_background_refresh_*).
    with patch("app.routers.alienvault.SessionLocal", return_value=db_session), patch.object(
        db_session, "close"
    ), patch("app.routers.alienvault.generate_dashboard_data", new=AsyncMock(return_value={"summary": {}})):
        resp = client.post("/alienvault/refresh", headers=headers)
    assert resp.status_code == 200
    assert resp.json() == {"message": "refresh started"}


def test_dashboard_404_before_first_refresh(client, db_session):
    headers = login(client, make_user(db_session).email)
    resp = client.get("/alienvault/dashboard", headers=headers)
    assert resp.status_code == 404


def test_dashboard_serves_cache(client, db_session):
    headers = login(client, make_user(db_session).email)
    db_session.add(AlienvaultDashboardCache(id=1, data={"summary": {"reports": 3}}))
    db_session.commit()
    resp = client.get("/alienvault/dashboard", headers=headers)
    assert resp.status_code == 200
    assert resp.json()["data"] == {"summary": {"reports": 3}}


def test_background_refresh_persists_dashboard_and_handles_errors(db_session):
    from app.routers import alienvault as alienvault_router

    with patch("app.routers.alienvault.SessionLocal", return_value=db_session), patch.object(
        db_session, "close"
    ), patch("app.routers.alienvault._get_api_key", return_value="a-real-key"), patch(
        "app.routers.alienvault.generate_dashboard_data",
        new=AsyncMock(return_value={"summary": {"reports": 1}}),
    ):
        alienvault_router._run_refresh_in_background()

    row = db_session.get(AlienvaultDashboardCache, 1)
    assert row.data == {"summary": {"reports": 1}}


def test_background_refresh_swallows_alienvault_error(db_session):
    from app.routers import alienvault as alienvault_router

    with patch("app.routers.alienvault.SessionLocal", return_value=db_session), patch.object(
        db_session, "close"
    ), patch("app.routers.alienvault._get_api_key", return_value="a-real-key"), patch(
        "app.routers.alienvault.generate_dashboard_data",
        new=AsyncMock(side_effect=AlienvaultError("otx down")),
    ):
        alienvault_router._run_refresh_in_background()  # must not raise

    assert db_session.get(AlienvaultDashboardCache, 1) is None


def _indicators_response(items):
    resp = MagicMock()
    resp.raise_for_status = MagicMock()
    resp.json.return_value = {"results": items}
    return resp


def _geo_response(country_name):
    resp = MagicMock()
    resp.status_code = 200
    resp.json.return_value = {"country_name": country_name}
    return resp


def test_pulse_enrichment_reads_structured_cve_and_ip_indicators_from_own_feed():
    # Previously top_cves came from an unrelated global OTX search
    # (?q=CVE across the whole platform), disconnected from the user's own
    # subscribed pulses — confusing, and it regex-scanned free text instead
    # of using OTX's own structured `type: "CVE"` indicators. Both CVEs and
    # geo now come off the same per-pulse indicators fetch already used for
    # the threat map.
    pulses = [{"id": "pulse-1"}, {"id": "pulse-2"}]

    async def fake_get(url, headers=None, params=None):
        if url.endswith("/pulses/pulse-1/indicators"):
            return _indicators_response(
                [
                    {"type": "CVE", "indicator": "cve-2024-12345"},
                    {"type": "CVE", "indicator": "cve-2024-12345"},  # deduped within the same pulse
                    {"type": "IPv4", "indicator": "1.2.3.4"},
                    {"type": "domain", "indicator": "evil.example"},  # ignored type
                ]
            )
        if url.endswith("/pulses/pulse-2/indicators"):
            return _indicators_response([{"type": "CVE", "indicator": "CVE-2023-9999"}])
        if url.endswith("/indicators/IPv4/1.2.3.4/geo"):
            return _geo_response("France")
        raise AssertionError(f"unexpected URL: {url}")

    client = MagicMock()
    client.get = AsyncMock(side_effect=fake_get)

    geo_data, top_cves = asyncio.run(_get_pulse_enrichment(client, "fake-key", pulses))

    assert geo_data == {"France": 1}
    assert {"cve": "CVE-2024-12345", "count": 1} in top_cves
    assert {"cve": "CVE-2023-9999", "count": 1} in top_cves


def test_pulse_enrichment_tolerates_one_pulse_failing():
    pulses = [{"id": "flaky"}, {"id": "ok"}]

    async def fake_get(url, headers=None, params=None):
        if url.endswith("/pulses/flaky/indicators"):
            raise httpx.ConnectError("transient 502")
        if url.endswith("/pulses/ok/indicators"):
            return _indicators_response([{"type": "CVE", "indicator": "CVE-2021-0001"}])
        raise AssertionError(f"unexpected URL: {url}")

    client = MagicMock()
    client.get = AsyncMock(side_effect=fake_get)

    geo_data, top_cves = asyncio.run(_get_pulse_enrichment(client, "fake-key", pulses))

    assert geo_data == {}
    assert top_cves == [{"cve": "CVE-2021-0001", "count": 1}]
