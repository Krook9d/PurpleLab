import httpx

DEFAULT_PORT = 9200


def _base_url(config: dict) -> str:
    scheme = "https" if config.get("ssl_enabled", True) else "http"
    port = config.get("port") or DEFAULT_PORT
    return f"{scheme}://{config['host']}:{port}"


def _auth(config: dict) -> tuple[str, str]:
    return (config.get("username", ""), config.get("password", ""))


async def test_connection(config: dict) -> bool:
    # verify=False deliberately kept (like the legacy opensearch.py):
    # self-hosted OpenSearch labs with self-signed certs, no certificate
    # pinning UI for now.
    async with httpx.AsyncClient(verify=False, timeout=5.0) as client:
        try:
            response = await client.get(f"{_base_url(config)}/_cluster/health", auth=_auth(config))
            return response.status_code == 200
        except httpx.HTTPError:
            return False


def _is_missing_alerting_index(response: httpx.Response) -> bool:
    if response.status_code != 404:
        return False
    try:
        return response.json().get("error", {}).get("type") == "alerting_exception"
    except ValueError:
        return False


async def list_rules(config: dict) -> list[dict]:
    """Reproduces opensearch.py list_all_triggers(): cross-references
    alerting monitors with active alerts to produce a flat list of
    "rules" uniform with the Splunk connector."""
    base_url = _base_url(config)
    auth = _auth(config)
    async with httpx.AsyncClient(verify=False, timeout=15.0) as client:
        monitors_resp = await client.post(
            f"{base_url}/_plugins/_alerting/monitors/_search",
            auth=auth,
            json={"query": {"match_all": {}}, "size": 100},
        )
        if _is_missing_alerting_index(monitors_resp):
            # No monitor has ever been created on this cluster: the
            # alerting config index is lazily created by OpenSearch on
            # the first monitor creation. This is a genuine "zero rules",
            # not a connectivity outage.
            return []
        monitors_resp.raise_for_status()
        alerts_resp = await client.get(f"{base_url}/_plugins/_alerting/monitors/alerts", auth=auth)
        alerts_resp.raise_for_status()

    active_alerts_by_key: dict[str, dict] = {}
    for alert in alerts_resp.json().get("alerts", []):
        if alert.get("state") != "ACTIVE":
            continue
        key = f"{alert.get('monitor_name')}|{alert.get('trigger_name')}"
        active_alerts_by_key[key] = alert

    rules: list[dict] = []
    for hit in monitors_resp.json().get("hits", {}).get("hits", []):
        monitor_id = hit["_id"]
        monitor = hit["_source"]
        monitor_name = monitor.get("name", monitor_id)
        for trigger_wrapper in monitor.get("triggers", []):
            trigger = (
                trigger_wrapper.get("query_level_trigger")
                or trigger_wrapper.get("bucket_level_trigger")
                or trigger_wrapper.get("document_level_trigger")
                or {}
            )
            trigger_name = trigger.get("name", "")
            active_alert = active_alerts_by_key.get(f"{monitor_name}|{trigger_name}")
            rules.append(
                {
                    "id": trigger.get("id") or f"{monitor_id}:{trigger_name}",
                    "name": trigger_name,
                    "monitor_name": monitor_name,
                    "monitor_id": monitor_id,
                    "monitor_enabled": monitor.get("enabled", False),
                    "severity": trigger.get("severity"),
                    "is_active": active_alert is not None,
                    "start_time": active_alert.get("start_time") if active_alert else None,
                    "last_notification_time": (
                        active_alert.get("last_notification_time") if active_alert else None
                    ),
                    "alert_id": active_alert.get("id") if active_alert else None,
                    "rule_type": "monitor",
                }
            )
    return rules
