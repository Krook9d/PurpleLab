import httpx

DEFAULT_PORT = 9000


def _base_url(config: dict) -> str:
    scheme = "https" if config.get("ssl_enabled", True) else "http"
    port = config.get("port") or DEFAULT_PORT
    return f"{scheme}://{config['host']}:{port}"


def _auth(config: dict) -> tuple[str, str]:
    return (config.get("username", ""), config.get("password", ""))


def _headers() -> dict:
    # Graylog's CSRF protection rejects API requests without this header
    # (any non-empty value works — Graylog's own docs/CLI examples just
    # pass the calling tool's name).
    return {"X-Requested-By": "purplelab", "Accept": "application/json"}


async def test_connection(config: dict) -> bool:
    async with httpx.AsyncClient(verify=False, timeout=5.0) as client:
        try:
            response = await client.get(
                f"{_base_url(config)}/api/system", auth=_auth(config), headers=_headers()
            )
            return response.status_code == 200
        except httpx.HTTPError:
            return False


async def list_rules(config: dict) -> list[dict]:
    """Graylog's Event Definitions (GET /api/events/definitions) — search/
    aggregation conditions that fire Events when matched, Graylog's actual
    detection-rule mechanism (the modern "Alerts" system is built on top
    of these since Graylog 3.x). Unlike Elastic/Wazuh there's no single
    enabled/disabled field on this base listing endpoint that's stable
    across Graylog versions, so every definition returned here is treated
    as active — it exists and Graylog is evaluating it."""
    async with httpx.AsyncClient(verify=False, timeout=15.0) as client:
        response = await client.get(
            f"{_base_url(config)}/api/events/definitions",
            auth=_auth(config),
            headers=_headers(),
            params={"per_page": 100},
        )
        response.raise_for_status()

    rules: list[dict] = []
    for definition in response.json().get("event_definitions", []):
        rules.append(
            {
                "id": definition.get("id"),
                "name": definition.get("title"),
                "description": definition.get("description"),
                "priority": definition.get("priority"),
                "severity": definition.get("priority"),
                "alert": definition.get("alert", False),
                "is_active": True,
                "rule_type": "event_definition",
            }
        )
    return rules
