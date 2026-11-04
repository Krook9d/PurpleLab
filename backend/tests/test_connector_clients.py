import asyncio
from unittest.mock import MagicMock, patch

import httpx

from app.services.connectors import elasticsearch_client, graylog_client, opensearch_client, splunk_client, wazuh_client

OS_CONFIG = {"host": "opensearch.local", "port": 9200, "username": "admin", "password": "admin", "ssl_enabled": False}
SPLUNK_CONFIG = {"host": "splunk.local", "port": 8089, "username": "admin", "password": "changeme", "ssl_enabled": True}
WAZUH_CONFIG = {"host": "wazuh.local", "port": 55000, "username": "wazuh", "password": "wazuh", "ssl_enabled": False}
ES_CONFIG = {"host": "kibana.local", "port": 5601, "username": "elastic", "password": "changeme", "ssl_enabled": False}
GRAYLOG_CONFIG = {"host": "graylog.local", "port": 9000, "username": "admin", "password": "admin", "ssl_enabled": False}


class _FakeAsyncClient:
    def __init__(self, responses):
        self._responses = responses
        self.calls = []

    async def __aenter__(self):
        return self

    async def __aexit__(self, *args):
        return False

    async def get(self, url, **kwargs):
        self.calls.append(("GET", url))
        return self._responses.pop(0)

    async def post(self, url, **kwargs):
        self.calls.append(("POST", url))
        return self._responses.pop(0)


def _resp(status_code, json_data):
    request = httpx.Request("GET", "http://x")
    return httpx.Response(status_code=status_code, json=json_data, request=request)


def test_opensearch_test_connection_ok():
    fake = _FakeAsyncClient([_resp(200, {"status": "green"})])
    with patch("app.services.connectors.opensearch_client.httpx.AsyncClient", return_value=fake):
        assert asyncio.run(opensearch_client.test_connection(OS_CONFIG)) is True


def test_opensearch_test_connection_failure_returns_false():
    fake = _FakeAsyncClient([_resp(401, {"error": "unauthorized"})])
    with patch("app.services.connectors.opensearch_client.httpx.AsyncClient", return_value=fake):
        assert asyncio.run(opensearch_client.test_connection(OS_CONFIG)) is False


def test_opensearch_list_rules_empty_index_returns_empty_list():
    fake = _FakeAsyncClient(
        [_resp(404, {"error": {"type": "alerting_exception", "reason": "Configured indices are not found"}})]
    )
    with patch("app.services.connectors.opensearch_client.httpx.AsyncClient", return_value=fake):
        assert asyncio.run(opensearch_client.list_rules(OS_CONFIG)) == []


def test_opensearch_list_rules_flattens_monitor_and_alert():
    monitors = _resp(
        200,
        {
            "hits": {
                "hits": [
                    {
                        "_id": "monitor-1",
                        "_source": {
                            "name": "My Monitor",
                            "enabled": True,
                            "triggers": [
                                {
                                    "query_level_trigger": {
                                        "id": "trigger-1",
                                        "name": "My Trigger",
                                        "severity": "2",
                                    }
                                }
                            ],
                        },
                    }
                ]
            }
        },
    )
    alerts = _resp(
        200,
        {
            "alerts": [
                {
                    "monitor_name": "My Monitor",
                    "trigger_name": "My Trigger",
                    "state": "ACTIVE",
                    "id": "alert-1",
                    "start_time": 123,
                    "last_notification_time": 456,
                }
            ]
        },
    )
    fake = _FakeAsyncClient([monitors, alerts])
    with patch("app.services.connectors.opensearch_client.httpx.AsyncClient", return_value=fake):
        rules = asyncio.run(opensearch_client.list_rules(OS_CONFIG))
    assert rules == [
        {
            "id": "trigger-1",
            "name": "My Trigger",
            "monitor_name": "My Monitor",
            "monitor_id": "monitor-1",
            "monitor_enabled": True,
            "severity": "2",
            "is_active": True,
            "start_time": 123,
            "last_notification_time": 456,
            "alert_id": "alert-1",
            "rule_type": "monitor",
        }
    ]


def test_splunk_test_connection_ok():
    fake_service = MagicMock()
    fake_service.apps = ["search"]
    with patch("app.services.connectors.splunk_client.splunk_client.connect", return_value=fake_service):
        assert splunk_client.test_connection(SPLUNK_CONFIG) is True


def test_splunk_test_connection_failure_returns_false():
    with patch("app.services.connectors.splunk_client.splunk_client.connect", side_effect=Exception("auth failed")):
        assert splunk_client.test_connection(SPLUNK_CONFIG) is False


def test_splunk_list_rules_filters_unscheduled_and_system_apps():
    def make_search(name, is_scheduled, app):
        search = MagicMock()
        search.name = name
        search.content = {"is_scheduled": is_scheduled, "search": "index=*", "description": "d"}
        search.access.app = app
        return search

    fake_service = MagicMock()
    fake_service.fired_alerts = []
    fake_service.saved_searches = [
        make_search("real-alert", "1", "search_app"),
        make_search("not-scheduled", "0", "search_app"),
        make_search("system-alert", "1", "launcher"),
    ]
    with patch("app.services.connectors.splunk_client.splunk_client.connect", return_value=fake_service):
        rules = splunk_client.list_rules(SPLUNK_CONFIG)
    assert [r["id"] for r in rules] == ["real-alert"]


def test_wazuh_test_connection_ok():
    fake = _FakeAsyncClient([_resp(200, {"data": {"token": "jwt-token"}})])
    with patch("app.services.connectors.wazuh_client.httpx.AsyncClient", return_value=fake):
        assert asyncio.run(wazuh_client.test_connection(WAZUH_CONFIG)) is True


def test_wazuh_test_connection_failure_returns_false():
    fake = _FakeAsyncClient([_resp(401, {"title": "Unauthorized"})])
    with patch("app.services.connectors.wazuh_client.httpx.AsyncClient", return_value=fake):
        assert asyncio.run(wazuh_client.test_connection(WAZUH_CONFIG)) is False


def test_wazuh_list_rules_maps_fields():
    auth_resp = _resp(200, {"data": {"token": "jwt-token"}})
    rules_resp = _resp(
        200,
        {
            "data": {
                "affected_items": [
                    {
                        "id": 5502,
                        "level": 10,
                        "description": "Multiple failed logins",
                        "groups": ["authentication_failed"],
                        "filename": "0095-sshd_rules.xml",
                        "status": "enabled",
                    }
                ]
            }
        },
    )
    fake = _FakeAsyncClient([auth_resp, rules_resp])
    with patch("app.services.connectors.wazuh_client.httpx.AsyncClient", return_value=fake):
        rules = asyncio.run(wazuh_client.list_rules(WAZUH_CONFIG))
    assert rules == [
        {
            "id": 5502,
            "name": "Multiple failed logins",
            "level": 10,
            "severity": 10,
            "groups": ["authentication_failed"],
            "filename": "0095-sshd_rules.xml",
            "is_active": True,
            "rule_type": "wazuh_rule",
        }
    ]
    # POST for the JWT, then GET for the ruleset with it as a Bearer header.
    assert fake.calls == [("POST", "/security/user/authenticate"), ("GET", "/rules")]


def test_elasticsearch_test_connection_ok():
    fake = _FakeAsyncClient([_resp(200, {"version": {"number": "8.11.0"}})])
    with patch("app.services.connectors.elasticsearch_client.httpx.AsyncClient", return_value=fake):
        assert asyncio.run(elasticsearch_client.test_connection(ES_CONFIG)) is True


def test_elasticsearch_test_connection_failure_returns_false():
    fake = _FakeAsyncClient([_resp(401, {"message": "Unauthorized"})])
    with patch("app.services.connectors.elasticsearch_client.httpx.AsyncClient", return_value=fake):
        assert asyncio.run(elasticsearch_client.test_connection(ES_CONFIG)) is False


def test_elasticsearch_list_rules_maps_fields():
    fake = _FakeAsyncClient(
        [
            _resp(
                200,
                {
                    "data": [
                        {
                            "rule_id": "rule-uuid-1",
                            "name": "Suspicious PowerShell",
                            "description": "Detects encoded PowerShell",
                            "severity": "high",
                            "risk_score": 73,
                            "tags": ["Windows", "Execution"],
                            "enabled": True,
                            "type": "query",
                        }
                    ]
                },
            )
        ]
    )
    with patch("app.services.connectors.elasticsearch_client.httpx.AsyncClient", return_value=fake):
        rules = asyncio.run(elasticsearch_client.list_rules(ES_CONFIG))
    assert rules == [
        {
            "id": "rule-uuid-1",
            "name": "Suspicious PowerShell",
            "description": "Detects encoded PowerShell",
            "severity": "high",
            "risk_score": 73,
            "tags": ["Windows", "Execution"],
            "is_active": True,
            "rule_type": "query",
        }
    ]


def test_graylog_test_connection_ok():
    fake = _FakeAsyncClient([_resp(200, {"version": "5.2.0"})])
    with patch("app.services.connectors.graylog_client.httpx.AsyncClient", return_value=fake):
        assert asyncio.run(graylog_client.test_connection(GRAYLOG_CONFIG)) is True


def test_graylog_test_connection_failure_returns_false():
    fake = _FakeAsyncClient([_resp(401, {"message": "Unauthorized"})])
    with patch("app.services.connectors.graylog_client.httpx.AsyncClient", return_value=fake):
        assert asyncio.run(graylog_client.test_connection(GRAYLOG_CONFIG)) is False


def test_graylog_list_rules_maps_fields():
    fake = _FakeAsyncClient(
        [
            _resp(
                200,
                {
                    "event_definitions": [
                        {
                            "id": "def-1",
                            "title": "Brute force detected",
                            "description": "Multiple failed logins from one source",
                            "priority": 3,
                            "alert": True,
                        }
                    ]
                },
            )
        ]
    )
    with patch("app.services.connectors.graylog_client.httpx.AsyncClient", return_value=fake):
        rules = asyncio.run(graylog_client.list_rules(GRAYLOG_CONFIG))
    assert rules == [
        {
            "id": "def-1",
            "name": "Brute force detected",
            "description": "Multiple failed logins from one source",
            "priority": 3,
            "severity": 3,
            "alert": True,
            "is_active": True,
            "rule_type": "event_definition",
        }
    ]
