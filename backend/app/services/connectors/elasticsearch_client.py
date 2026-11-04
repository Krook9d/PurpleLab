import httpx

# Kibana's port, not Elasticsearch's 9200: Elastic Security's detection
# rules are a Kibana-side concept (the Detection Engine API under
# /api/detection_engine), not something the Elasticsearch cluster itself
# exposes. host/port here must point at Kibana.
DEFAULT_PORT = 5601


def _base_url(config: dict) -> str:
    scheme = "https" if config.get("ssl_enabled", True) else "http"
    port = config.get("port") or DEFAULT_PORT
    return f"{scheme}://{config['host']}:{port}"


def _auth(config: dict) -> tuple[str, str]:
    return (config.get("username", ""), config.get("password", ""))


def _headers() -> dict:
    # Kibana's CSRF protection rejects any API request without this
    # header, GET included, on the detection engine routes.
    return {"kbn-xsrf": "true"}


async def test_connection(config: dict) -> bool:
    async with httpx.AsyncClient(verify=False, timeout=5.0) as client:
        try:
            response = await client.get(
                f"{_base_url(config)}/api/status", auth=_auth(config), headers=_headers()
            )
            return response.status_code == 200
        except httpx.HTTPError:
            return False


async def list_rules(config: dict) -> list[dict]:
    """Elastic Security's Detection Engine API (Kibana, not the raw
    Elasticsearch cluster — see DEFAULT_PORT). Unlike Splunk/OpenSearch, a
    rule's enabled state is a direct field on the rule itself, so there's
    no separate "recent alerts" feed to cross-reference to know if it's
    active."""
    async with httpx.AsyncClient(verify=False, timeout=15.0) as client:
        response = await client.get(
            f"{_base_url(config)}/api/detection_engine/rules/_find",
            auth=_auth(config),
            headers=_headers(),
            params={"per_page": 100},
        )
        response.raise_for_status()

    rules: list[dict] = []
    for rule in response.json().get("data", []):
        rules.append(
            {
                "id": rule.get("rule_id") or rule.get("id"),
                "name": rule.get("name"),
                "description": rule.get("description"),
                "severity": rule.get("severity"),
                "risk_score": rule.get("risk_score"),
                "tags": rule.get("tags", []),
                "is_active": rule.get("enabled", False),
                "rule_type": rule.get("type"),
            }
        )
    return rules
