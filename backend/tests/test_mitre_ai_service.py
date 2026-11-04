import asyncio
from unittest.mock import AsyncMock, MagicMock, patch

from app.db.models.mitre_technique import MitreTechnique
from app.services import mitre_ai_service


def test_extract_keywords_drops_stopwords_and_short_words():
    keywords = mitre_ai_service._extract_keywords("How can I detect and test phishing attacks with macros?")
    assert "phishing" in keywords
    assert "macros" in keywords
    assert "detect" not in keywords  # stopword
    assert "how" not in keywords  # trop court / stopword


def test_search_relevant_techniques_finds_matches(db_session):
    db_session.add_all(
        [
            MitreTechnique(id="T1566", name="Phishing"),
            MitreTechnique(id="T1059", name="Command and Scripting Interpreter"),
        ]
    )
    db_session.commit()

    results = mitre_ai_service.search_relevant_techniques(db_session, "How do I simulate a phishing campaign?")
    assert [t.id for t in results] == ["T1566"]


def test_build_system_prompt_includes_found_techniques():
    technique = MitreTechnique(id="T1566", name="Phishing", description="Adversaries send phishing messages.")
    prompt = mitre_ai_service.build_system_prompt([technique])
    assert "T1566" in prompt
    assert "Phishing" in prompt


def test_build_system_prompt_falls_back_to_base_when_no_matches():
    prompt = mitre_ai_service.build_system_prompt([])
    assert prompt == mitre_ai_service.SYSTEM_PROMPT_BASE


def test_call_openai_parses_response():
    response = MagicMock(status_code=200)
    response.json.return_value = {"choices": [{"message": {"content": "hello"}}]}
    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client = AsyncMock()
        mock_client.__aenter__.return_value = mock_client
        mock_client.post = AsyncMock(return_value=response)
        mock_client_cls.return_value = mock_client
        result = asyncio.run(
            mitre_ai_service.call_openai([{"role": "user", "content": "hi"}], {"model": "gpt-x", "api_key": "k"})
        )
    assert result == "hello"


def test_call_gemini_parses_response():
    response = MagicMock(status_code=200)
    response.json.return_value = {"candidates": [{"content": {"parts": [{"text": "hello from gemini"}]}}]}
    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client = AsyncMock()
        mock_client.__aenter__.return_value = mock_client
        mock_client.post = AsyncMock(return_value=response)
        mock_client_cls.return_value = mock_client
        result = asyncio.run(
            mitre_ai_service.call_gemini([{"role": "user", "content": "hi"}], {"model": "gemini-x", "api_key": "k"})
        )
    assert result == "hello from gemini"


def test_chat_raises_when_not_configured(db_session):
    import pytest

    with pytest.raises(mitre_ai_service.MitreAiError):
        asyncio.run(mitre_ai_service.chat(db_session, [{"role": "user", "content": "hi"}]))


def test_chat_uses_rag_and_dispatches_to_provider(db_session):
    from app.services import integration_configs

    db_session.add(MitreTechnique(id="T1566", name="Phishing", description="desc"))
    integration_configs.save_config(
        db_session, "mitre_ai", {"provider": "openai", "model": "gpt-x", "api_key": "k", "api_base": None}
    )
    db_session.commit()

    with patch("app.services.mitre_ai_service.call_provider", new=AsyncMock(return_value="reply text")) as mock_call:
        reply, related = asyncio.run(
            mitre_ai_service.chat(db_session, [{"role": "user", "content": "tell me about phishing"}])
        )

    assert reply == "reply text"
    assert related == ["T1566"]
    sent_messages = mock_call.call_args[0][0]
    assert sent_messages[0]["role"] == "system"
    assert "T1566" in sent_messages[0]["content"]
