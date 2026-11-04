from datetime import datetime

from pydantic import BaseModel


class PayloadCreate(BaseModel):
    name: str
    content: str


class PayloadOut(BaseModel):
    id: int
    name: str
    content: str
    author_id: int
    author_first_name: str
    author_last_name: str
    created_at: datetime | None


class ExecuteOut(BaseModel):
    status: str
    output: str | None = None
    error: str | None = None
