import re

import httpx
from sqlalchemy.orm import Session

from app.db.models.mitre_technique import MitreTechnique
from app.services import integration_configs, mitre_service

DEFAULT_GEMINI_API_BASE = "https://generativelanguage.googleapis.com/v1beta"

SYSTEM_PROMPT_BASE = (
    "You are a cybersecurity assistant for PurpleLab, specialized in MITRE ATT&CK-based "
    "testing. For any attack scenario described by the user, suggest relevant MITRE ATT&CK "
    "technique IDs (format T#### or T####.###) and briefly explain why. Prefer techniques "
    "that correspond to executable Atomic Red Team tests when possible."
)

_STOPWORDS = {
    "this", "that", "with", "from", "have", "what", "how", "can", "are", "use", "using",
    "attack", "technique", "about", "want", "need", "like", "would", "could", "should",
    "detect", "detection", "test", "tests", "simulate", "simulation",
}


class MitreAiError(Exception):
    pass


def _extract_keywords(message: str, limit: int = 8) -> list[str]:
    words = re.findall(r"[a-zA-Z]{4,}", message.lower())
    keywords: list[str] = []
    for word in words:
        if word in _STOPWORDS or word in keywords:
            continue
        keywords.append(word)
        if len(keywords) >= limit:
            break
    return keywords


def search_relevant_techniques(db: Session, user_message: str, limit: int = 5) -> list[MitreTechnique]:
    # Real RAG (unlike the legacy version which let the model guess IDs
    # from memory, without ever querying the database): keywords from the
    # user message searched in mitre_techniques, results injected into
    # the system prompt by build_system_prompt().
    found: dict[str, MitreTechnique] = {}
    for keyword in _extract_keywords(user_message):
        for technique in mitre_service.search_techniques(db, keyword, limit=limit):
            found.setdefault(technique.id, technique)
        if len(found) >= limit:
            break
    return list(found.values())[:limit]


def build_system_prompt(techniques: list[MitreTechnique]) -> str:
    if not techniques:
        return SYSTEM_PROMPT_BASE
    lines = "\n".join(
        f"- {t.id} ({t.name}): {(t.description or '')[:200]}" for t in techniques
    )
    return (
        f"{SYSTEM_PROMPT_BASE}\n\n"
        "Relevant techniques found in the knowledge base for this message "
        f"(use these IDs preferentially if applicable):\n{lines}"
    )


def _truncate_history(messages: list[dict], max_messages: int = 24) -> list[dict]:
    return messages[-max_messages:]


async def call_openai(messages: list[dict], config: dict) -> str:
    api_base = config.get("api_base") or "https://api.openai.com/v1"
    async with httpx.AsyncClient(timeout=120.0) as client:
        response = await client.post(
            f"{api_base.rstrip('/')}/chat/completions",
            headers={"Authorization": f"Bearer {config['api_key']}"},
            json={"model": config["model"], "messages": messages, "temperature": 0.35},
        )
    if response.status_code != 200:
        raise MitreAiError(f"OpenAI-compatible API error: {response.status_code} {response.text[:200]}")
    data = response.json()
    return data["choices"][0]["message"]["content"]


def _to_gemini_contents(messages: list[dict]) -> list[dict]:
    contents = []
    for message in messages:
        role = "model" if message["role"] in ("assistant", "system") else "user"
        contents.append({"role": role, "parts": [{"text": message["content"]}]})
    return contents


async def call_gemini(messages: list[dict], config: dict) -> str:
    api_base = config.get("api_base") or DEFAULT_GEMINI_API_BASE
    model = config["model"]
    async with httpx.AsyncClient(timeout=120.0) as client:
        response = await client.post(
            f"{api_base.rstrip('/')}/models/{model}:generateContent",
            params={"key": config["api_key"]},
            json={
                "contents": _to_gemini_contents(messages),
                "generationConfig": {"temperature": 0.35, "maxOutputTokens": 1200},
            },
        )
    if response.status_code != 200:
        raise MitreAiError(f"Gemini API error: {response.status_code} {response.text[:200]}")
    data = response.json()
    try:
        return data["candidates"][0]["content"]["parts"][0]["text"]
    except (KeyError, IndexError) as exc:
        raise MitreAiError(f"unexpected Gemini response shape: {data}") from exc


async def call_provider(messages: list[dict], config: dict) -> str:
    if config["provider"] == "gemini":
        return await call_gemini(messages, config)
    return await call_openai(messages, config)


async def test_connection(config: dict) -> bool:
    probe = [{"role": "user", "content": "ping"}]
    try:
        await call_provider(probe, config)
        return True
    except (MitreAiError, httpx.HTTPError):
        return False


async def chat(db: Session, messages: list[dict]) -> tuple[str, list[str]]:
    config = integration_configs.get_config(db, "mitre_ai")
    if config is None:
        raise MitreAiError("MITRE AI assistant not configured")

    last_user_message = next((m["content"] for m in reversed(messages) if m["role"] == "user"), "")
    relevant = search_relevant_techniques(db, last_user_message)
    system_prompt = build_system_prompt(relevant)

    truncated = _truncate_history(messages)
    provider_messages = [{"role": "system", "content": system_prompt}, *truncated]
    reply = await call_provider(provider_messages, config)
    return reply, [t.id for t in relevant]
