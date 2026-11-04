from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    db_host: str = "postgres"
    db_port: int = 5432
    db_name: str = "purplelab"
    db_user: str = "purplelab"
    db_password: str = ""
    db_password_file: str | None = None

    vm_agent_url: str = "http://host.docker.internal:8088"
    vm_agent_token: str = ""
    vm_agent_token_file: str | None = None
    vm_agent_timeout_seconds: float = 3.0

    jwt_secret: str = ""
    jwt_secret_file: str | None = None
    master_encryption_key: str = ""
    master_encryption_key_file: str | None = None

    # Shared folders (bind-mounts identical on the backend container side
    # and the host side for vmbridge-agent). Paths as
    # seen from THIS container.
    malware_upload_dir: str = "/data/malware_upload"
    samples_dir: str = "/data/samples"
    csv_dir: str = "/data/csv"
    json_dir: str = "/data/json"
    sigma_rules_dir: str = "/data/sigma_rules"

    # The MalwareBazaar API now requires a key (Auth-Key header) —
    # verified empirically at Milestone 3, the old scripts/malwareretrieval.py
    # did not send one and is broken against the current API. Free
    # personal key at https://bazaar.abuse.ch/account/api/.
    malwarebazaar_api_key: str = ""
    malwarebazaar_api_key_file: str | None = None

    @property
    def resolved_malwarebazaar_api_key(self) -> str:
        return self._read_secret(self.malwarebazaar_api_key, self.malwarebazaar_api_key_file)

    # Explicit CORS (end of the current Flask "*" wildcard) — comma-separated
    # list of origins. Empty by default: nobody calls the API from a browser
    # yet (the legacy PHP calls server-to-server, not from browser JS), to
    # be populated in phase 2 (React) with the actual frontend origin.
    cors_origins: str = ""

    # Internal docker-compose OpenSearch instance (Winlogbeat ingestion
    # from the sandbox VM), distinct from the user-configurable external
    # SIEM connectors (app/services/connectors/).
    opensearch_url: str = "http://opensearch:9200"

    @property
    def cors_origins_list(self) -> list[str]:
        return [origin.strip() for origin in self.cors_origins.split(",") if origin.strip()]

    def _read_secret(self, value: str, file_path: str | None) -> str:
        if file_path:
            return open(file_path).read().strip()
        return value

    @property
    def resolved_db_password(self) -> str:
        return self._read_secret(self.db_password, self.db_password_file)

    @property
    def resolved_vm_agent_token(self) -> str:
        return self._read_secret(self.vm_agent_token, self.vm_agent_token_file)

    @property
    def resolved_jwt_secret(self) -> str:
        return self._read_secret(self.jwt_secret, self.jwt_secret_file)

    @property
    def resolved_master_encryption_key(self) -> str:
        return self._read_secret(self.master_encryption_key, self.master_encryption_key_file)

    @property
    def database_url(self) -> str:
        return (
            f"postgresql+psycopg://{self.db_user}:{self.resolved_db_password}"
            f"@{self.db_host}:{self.db_port}/{self.db_name}"
        )


@lru_cache
def get_settings() -> Settings:
    return Settings()
