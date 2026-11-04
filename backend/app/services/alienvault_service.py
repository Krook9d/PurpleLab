import asyncio
from collections import Counter
from datetime import datetime, timezone

import httpx

BASE_URL = "https://otx.alienvault.com/api/v1"

# Subset of keywords for the "most targeted industries"/malware ranking
# (reproduces the spirit of alienvault.py's local heuristics, with no
# claim of exhaustiveness — it was already a heuristic on the legacy
# side, not a real classification).
INDUSTRY_KEYWORDS = {
    "Finance": ["bank", "financial", "fintech", "payment"],
    "Healthcare": ["health", "hospital", "medical", "pharma"],
    "Government": ["government", "gov.", "military", "defense"],
    "Technology": ["tech", "software", "cloud", "saas"],
    "Energy": ["energy", "utility", "oil", "gas", "power grid"],
    "Retail": ["retail", "e-commerce", "shopping"],
    "Education": ["education", "university", "school"],
}
MALWARE_KEYWORDS = ["malware", "ransomware", "trojan", "backdoor", "rootkit", "worm", "spyware"]


class AlienvaultError(Exception):
    pass


def _headers(api_key: str) -> dict:
    return {"X-OTX-API-KEY": api_key}


async def test_api_key(api_key: str) -> bool:
    async with httpx.AsyncClient(timeout=10.0) as client:
        try:
            response = await client.get(f"{BASE_URL}/user/me", headers=_headers(api_key))
            return response.status_code == 200
        except httpx.HTTPError:
            return False


async def _get_recent_pulses(client: httpx.AsyncClient, api_key: str, limit: int = 20) -> list[dict]:
    response = await client.get(
        f"{BASE_URL}/pulses/subscribed", headers=_headers(api_key), params={"limit": limit}
    )
    response.raise_for_status()
    return response.json().get("results", [])


async def _get_pulse_indicators(client: httpx.AsyncClient, api_key: str, pulse_id: str) -> list[dict]:
    response = await client.get(f"{BASE_URL}/pulses/{pulse_id}/indicators", headers=_headers(api_key))
    response.raise_for_status()
    return response.json().get("results", [])


async def _get_ip_geo(client: httpx.AsyncClient, api_key: str, ip: str) -> dict | None:
    # One retry on a 429: a burst of per-IP geo calls (no batch endpoint on
    # the OTX side) occasionally trips rate limiting, which surfaced live as
    # a dashboard with a real feed but a silently empty threat map — the
    # non-200 branch below swallowed it with no distinction from "no data".
    for attempt in range(2):
        response = await client.get(f"{BASE_URL}/indicators/IPv4/{ip}/geo", headers=_headers(api_key))
        if response.status_code == 429 and attempt == 0:
            await asyncio.sleep(1)
            continue
        if response.status_code != 200:
            return None
        return response.json()
    return None


async def _get_pulse_enrichment(client: httpx.AsyncClient, api_key: str, pulses: list[dict]) -> tuple[dict, list[dict]]:
    """Geo map + top-CVEs, derived together from one indicators fetch per
    pulse. Previously the CVEs list came from an unrelated global OTX search
    (`?q=CVE` across the whole platform) with no connection to the user's own
    subscribed feed — confusing ("why does this CVE list have nothing to do
    with my pulses?") and it regex-scanned free text for a `CVE-####-#####`
    shape. OTX indicators already carry a structured `type: "CVE"` on the
    same per-pulse endpoint already being called here for IPv4 geolocation,
    so both are read off the one fetch, straight from the same 20 pulses
    shown everywhere else on this dashboard.
    """
    geo_counts: Counter[str] = Counter()
    cve_counts: Counter[str] = Counter()
    seen_ips: set[str] = set()
    for pulse in pulses:
        try:
            indicators = await _get_pulse_indicators(client, api_key, pulse["id"])
        except httpx.HTTPError:
            # OTX's public indicators endpoint is occasionally flaky per-pulse
            # (observed live: a transient 502 on one pulse) — without this,
            # one bad pulse used to abort enrichment for every other pulse
            # too, and (before the try/except in generate_dashboard_data
            # below) the whole dashboard refresh.
            continue
        cves_in_pulse: set[str] = set()
        for indicator in indicators:
            indicator_type = indicator.get("type")
            if indicator_type == "CVE":
                value = indicator.get("indicator")
                if value:
                    cves_in_pulse.add(value.upper())
            elif indicator_type == "IPv4":
                # Capped at 20 unique resolved IPs to keep latency
                # reasonable — CVE scanning above is cheap and keeps going
                # past the cap, only the geo lookups stop.
                ip = indicator.get("indicator")
                if not ip or ip in seen_ips or len(seen_ips) >= 20:
                    continue
                seen_ips.add(ip)
                geo = await _get_ip_geo(client, api_key, ip)
                if geo and geo.get("country_name"):
                    geo_counts[geo["country_name"]] += 1
        cve_counts.update(cves_in_pulse)
    top_cves = [{"cve": cve, "count": count} for cve, count in cve_counts.most_common(10)]
    return dict(geo_counts), top_cves


def _get_targeted_industries(pulses: list[dict], limit: int = 5) -> list[dict]:
    counts: Counter[str] = Counter()
    for pulse in pulses:
        text = f"{pulse.get('name', '')} {pulse.get('description', '')}".lower()
        for industry, keywords in INDUSTRY_KEYWORDS.items():
            if any(keyword in text for keyword in keywords):
                counts[industry] += 1
    return [{"name": name, "count": count} for name, count in counts.most_common(limit)]


def _filter_malware_pulses(pulses: list[dict]) -> list[dict]:
    return [
        pulse
        for pulse in pulses
        if any(keyword in f"{pulse.get('name', '')} {pulse.get('description', '')}".lower() for keyword in MALWARE_KEYWORDS)
    ]


def _threat_type_counts(pulses: list[dict], limit: int = 8) -> list[dict]:
    tag_counts: Counter[str] = Counter()
    for pulse in pulses:
        for tag in pulse.get("tags", []):
            tag_counts[tag] += 1
    return [{"name": name, "count": count} for name, count in tag_counts.most_common(limit)]


async def generate_dashboard_data(api_key: str) -> dict:
    """Reproduces alienvault.py generate_dashboard_data(), WITHOUT the
    fallback to fabricated demo data: if the key is invalid or the OTX
    API is unavailable, raises AlienvaultError instead of returning JSON
    indistinguishable from real data (legacy bug)."""
    async with httpx.AsyncClient(timeout=20.0) as client:
        try:
            pulses = await _get_recent_pulses(client, api_key, limit=20)
        except httpx.HTTPStatusError as exc:
            raise AlienvaultError(f"OTX API error: {exc.response.status_code}") from exc
        except httpx.HTTPError as exc:
            raise AlienvaultError(f"OTX API unreachable: {exc}") from exc

        malware_pulses = _filter_malware_pulses(pulses)
        # geo_data/top_cves enrichment hits OTX endpoints known to be
        # occasionally flaky (transient 502s observed live) — a failure here
        # must not blank out the rest of the dashboard (threat types,
        # targeted industries, recent pulses all only need `pulses`, already
        # fetched successfully above). Per-pulse errors are already caught
        # inside _get_pulse_enrichment; this is the outer safety net.
        try:
            geo_data, top_cves = await _get_pulse_enrichment(client, api_key, pulses)
        except httpx.HTTPError:
            geo_data, top_cves = {}, []

    recent_pulses = [
        {
            "id": pulse.get("id"),
            "name": pulse.get("name"),
            "created": pulse.get("created"),
            "description": pulse.get("description", ""),
            "tags": pulse.get("tags", [])[:5],
            "author_name": pulse.get("author_name"),
            "references": pulse.get("references", [])[:3],
        }
        for pulse in pulses[:10]
    ]
    created_dates = [p.get("created") for p in pulses if p.get("created")]

    return {
        "summary": {
            "intrusion_sets": sum(1 for p in pulses if "APT" in " ".join(p.get("tags", []))),
            "malware": len(malware_pulses),
            "reports": len(pulses),
            "indicators": sum(p.get("indicator_count", 0) for p in pulses),
        },
        "top_threats": _threat_type_counts(pulses),
        "most_targeted": _get_targeted_industries(pulses),
        "top_cves": top_cves,
        "geo_data": geo_data,
        "recent_pulses": recent_pulses,
        "time_metadata": {
            "oldest_pulse": min(created_dates) if created_dates else None,
            "newest_pulse": max(created_dates) if created_dates else None,
            "pulse_count": len(pulses),
            "generated_at": datetime.now(timezone.utc).isoformat(),
        },
    }
