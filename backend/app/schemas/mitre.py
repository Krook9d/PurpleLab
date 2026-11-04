from datetime import datetime
from pydantic import BaseModel


class TechniqueSummary(BaseModel):
    id: str
    name: str | None
    tactics: str | None
    is_sub_technique: bool | None


class TechniqueDetail(BaseModel):
    id: str
    stix_id: str | None
    name: str | None
    description: str | None
    url: str | None
    created: str | None
    last_modified: str | None
    domain: str | None
    version: str | None
    tactics: str | None
    detection: str | None
    platforms: str | None
    data_sources: str | None
    is_sub_technique: bool | None
    sub_technique_of: str | None
    defenses_bypassed: str | None
    contributors: str | None
    permissions_required: str | None
    supports_remote: bool | None
    system_requirements: str | None
    impact_type: str | None
    effective_permissions: str | None
    relationship_citations: str | None


class AtomicTestOut(BaseModel):
    id: int
    tactic: str | None
    technique_id: str | None
    technique_name: str | None
    test: str | None
    test_name: str | None
    test_guid: str | None
    executor_name: str | None


class MitreStats(BaseModel):
    technique_count: int


class TacticCount(BaseModel):
    tactic: str
    count: int


class RefreshResult(BaseModel):
    created: int
    updated: int
    total: int


class MatrixTechnique(BaseModel):
    id: str
    name: str | None
    tactics: str | None
    is_sub_technique: bool | None
    atomic_tests: int
    sigma_rules: int
    executions: int
    last_execution_status: str | None
    last_executed_at: datetime | None


class ExecuteAtomicRequest(BaseModel):
    test_number: int | None = None


class ExecuteOut(BaseModel):
    status: str
    output: str | None = None
    error: str | None = None
