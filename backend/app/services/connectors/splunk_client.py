import splunklib.client as splunk_client

DEFAULT_PORT = 8089

# Subset of internal Splunk apps to exclude from the rule list
# (reproduces the spirit of splunk_alerts.py's is_system_alert()
# denylist, simplified: the scheduled alerts/searches of these apps are
# internal Splunk maintenance, not detection rules created by the user).
SYSTEM_APPS = {"launcher", "splunk_instrumentation", "splunk_httpinput", "search", "learned"}


def _connect(config: dict) -> splunk_client.Service:
    scheme = "https" if config.get("ssl_enabled", True) else "http"
    return splunk_client.connect(
        host=config["host"],
        port=config.get("port") or DEFAULT_PORT,
        username=config.get("username", ""),
        password=config.get("password", ""),
        scheme=scheme,
    )


def test_connection(config: dict) -> bool:
    try:
        service = _connect(config)
        list(service.apps)  # forces a real round trip, like the legacy version
        return True
    except Exception:
        return False


def list_rules(config: dict) -> list[dict]:
    """Reproduces get_saved_searches_with_trigger_info() from
    splunk_alerts.py: scheduled/alerting saved searches, cross-referenced
    with recently triggered alerts."""
    service = _connect(config)

    triggered_by_name: dict[str, dict] = {}
    try:
        for alert in service.fired_alerts:
            triggered_by_name[alert.name] = {
                "trigger_time": getattr(alert, "trigger_time", None),
            }
    except Exception:
        pass

    rules: list[dict] = []
    for saved_search in service.saved_searches:
        content = saved_search.content
        if content.get("is_scheduled") not in ("1", 1, True):
            continue
        app = getattr(saved_search.access, "app", "") if hasattr(saved_search, "access") else ""
        if app in SYSTEM_APPS:
            continue
        trigger_info = triggered_by_name.get(saved_search.name)
        rules.append(
            {
                "id": saved_search.name,
                "name": saved_search.name,
                "search": content.get("search"),
                "description": content.get("description"),
                "cron_schedule": content.get("cron_schedule"),
                "alert_type": content.get("alert_type"),
                "alert_threshold": content.get("alert_threshold"),
                "severity": content.get("alert.severity"),
                "is_active": trigger_info is not None,
                "last_notification_time": trigger_info.get("trigger_time") if trigger_info else None,
                "app": app,
                "rule_type": "alert",
            }
        )
    return rules
