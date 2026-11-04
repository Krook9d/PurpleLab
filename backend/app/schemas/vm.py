from typing import Literal

from pydantic import BaseModel


class VmStateOut(BaseModel):
    name: str
    state: str
    snapshot: str | None
    ip: str | None


class MessageOut(BaseModel):
    message: str


class ExecutePayloadRequest(BaseModel):
    content: str


class ForensicRequest(BaseModel):
    acquisition_type: Literal["memory", "disk"]


class ForensicOut(BaseModel):
    message: str
    output_path: str | None = None
