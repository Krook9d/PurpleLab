from datetime import datetime
from typing import Literal

from pydantic import BaseModel


class MitreAiConfigStatus(BaseModel):
    configured: bool
    provider: str | None = None
    model: str | None = None
    api_base: str | None = None
    key_preview: str | None = None
    updated_at: datetime | None = None


class MitreAiConfigSave(BaseModel):
    provider: Literal["openai", "gemini"]
    model: str
    api_base: str | None = None
    api_key: str


class ChatMessage(BaseModel):
    role: Literal["user", "assistant"]
    content: str


class ChatRequest(BaseModel):
    messages: list[ChatMessage]


class ChatResponse(BaseModel):
    message: ChatMessage
    related_techniques: list[str] = []
