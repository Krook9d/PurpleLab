import httpx

DEFAULT_PORT = 55000


def _base_url(config: dict) -> str:
    scheme = "https" if config.get("ssl_enabled", True) else "http"
    port = config.get("port") or DEFAULT_PORT
    return f"{scheme}://{config['host']}:{port}"


async def _authenticate(client: httpx.AsyncClient, config: dict) -> str:
    # Wazuh's REST API is JWT-based: Basic auth only buys a short-lived
    # token (POST /security/user/authenticate), every other endpoint then
    # takes it as a Bearer header. No token caching here — each test/sync
    # call re-authenticates, the same one-shot-per-call simplicity as the
    # other connectors (splunk_client reconnects on every call too).
    response = await client.post(
        "/security/user/authenticate",
        auth=(config.get("username", ""), config.get("password", "")),
    )
    response.raise_for_status()
    return response.json()["data"]["token"]


async def test_connection(config: dict) -> bool:
    # verify=False deliberately kept, like the other self-hosted
    # connectors: lab Wazuh managers commonly run on self-signed certs.
    async with httpx.AsyncClient(base_url=_base_url(config), verify=False, timeout=5.0) as client:
        try:
            await _authenticate(client, config)
            return True
        except httpx.HTTPError:
            return False


async def list_rules(config: dict) -> list[dict]:
    """Wazuh's own ruleset (GET /rules) — the manager's built-in/custom
    detection rules. Unlike the Elastic-flavored connectors there's no
    separate "recently fired alerts" feed to cross-reference: a Wazuh rule
    carries its enabled/disabled status directly.
    limit=500 is the Wazuh API's own per-request maximum; a stock install
    ships several thousand default rules, so — like the size caps already
    used by the OpenSearch/Splunk connectors — this surfaces the first
    page rather than exhaustively paginating the entire built-in ruleset.
    """
    async with httpx.AsyncClient(base_url=_base_url(config), verify=False, timeout=15.0) as client:
        token = await _authenticate(client, config)
        response = await client.get(
            "/rules", headers={"Authorization": f"Bearer {token}"}, params={"limit": 500}
        )
        response.raise_for_status()

    rules: list[dict] = []
    for item in response.json().get("data", {}).get("affected_items", []):
        rules.append(
            {
                "id": item.get("id"),
                "name": item.get("description"),
                "level": item.get("level"),
                "severity": item.get("level"),
                "groups": item.get("groups", []),
                "filename": item.get("filename"),
                "is_active": item.get("status") == "enabled",
                "rule_type": "wazuh_rule",
            }
        )
    return rules
