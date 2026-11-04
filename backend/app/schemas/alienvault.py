from datetime import datetime

from pydantic import BaseModel


class AlienvaultConfigStatus(BaseModel):
    configured: bool
    key_preview: str | None = None
    updated_at: datetime | None = None


class AlienvaultConfigSave(BaseModel):
    api_key: str


class DashboardOut(BaseModel):
    data: dict
    generated_at: datetime


class RefreshStarted(BaseModel):
    message: str
