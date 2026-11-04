from datetime import datetime

from pydantic import BaseModel


class PostCreate(BaseModel):
    content: str
    title: str | None = None
    description: str | None = None
    language: str | None = None


class PostOut(BaseModel):
    id: int
    content: str
    title: str | None
    description: str | None
    language: str | None
    author_id: int
    author_first_name: str
    author_last_name: str
    created_at: datetime
