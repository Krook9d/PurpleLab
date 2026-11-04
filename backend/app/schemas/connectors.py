from datetime import datetime

from pydantic import BaseModel


class ConnectorConfig(BaseModel):
    host: str
    port: int | None = None
    username: str
    password: str
    ssl_enabled: bool = True


class ConnectorStatus(BaseModel):
    type: str
    configured: bool
    config: dict | None = None  # never a plaintext secret (see manager.mask_config)


class ConnectorTestResult(BaseModel):
    ok: bool


class RuleOut(BaseModel):
    # Deliberately free-form (dict passthrough): fields vary between
    # Splunk/OpenSearch connectors, like on the legacy side.
    model_config = {"extra": "allow"}


class SyncedRuleOut(BaseModel):
    id: int
    connector: str
    rule_id: str
    rule_data: dict
    synced_at: datetime | None


class RulesSyncResult(BaseModel):
    synced_count: int


class RulePayloadMapOut(BaseModel):
    rule_id: str
    payload_id: int | None


class RulePayloadMapUpdate(BaseModel):
    payload_id: int | None


class RuleLifecyclePayloadCreate(BaseModel):
    name: str
    description: str | None = None
    code: str


class RuleLifecyclePayloadUpdate(BaseModel):
    name: str
    description: str | None = None
    code: str


class RuleLifecyclePayloadOut(BaseModel):
    id: int
    name: str
    description: str | None
    code: str
    created_at: datetime | None
    updated_at: datetime | None


class ExecuteContext(BaseModel):
    # Optional context passed by the frontend when execution is
    # triggered from a specific rule (Rules & Payloads / Execution &
    # Results tabs) — persisted in execution_history if provided.
    rule_id: str | None = None
    connector: str | None = None


class ExecuteOut(BaseModel):
    status: str
    output: str | None = None
    error: str | None = None


class ExecutionHistoryOut(BaseModel):
    id: int
    source: str
    payload_id: int | None
    rule_id: str | None
    connector: str | None
    status: str
    output: str | None
    error: str | None
    executed_by: int | None
    executed_at: datetime
