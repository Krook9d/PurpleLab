from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    vm_name: str = "sandbox"
    # host.docker.internal (extra_hosts: host-gateway) resolves to the
    # Docker bridge's IP by default (typically 172.17.0.1), NOT
    # 127.0.0.1 — validated empirically at Milestone 0. The agent must
    # therefore listen on 0.0.0.0 to be reachable from the backend
    # container, with access restriction then handled by UFW (allow only
    # the Docker bridge subnet on this port) rather than by the loopback
    # bind alone.
    listen_host: str = "0.0.0.0"
    listen_port: int = 8088
    vm_agent_token: str = ""
    vm_agent_token_file: str | None = None
    vboxmanage_bin: str = "VBoxManage"

    # Windows guest account credentials (used by guestcontrol run for
    # toggling Defender / restarting winlogbeat). An artifact of the
    # existing Vagrant/Ansible provisioning (the "oem" account, out of
    # scope for this migration plan) — but cleanly externalized into
    # config rather than hardcoded, unlike the old manageVM.py.
    vm_guest_username: str = ""
    vm_guest_password: str = ""

    # Shared folders (identical bind-mounts on both the backend container
    # side and the host side) where the backend drops
    # files to be copied into the VM. The agent runs natively on the
    # host, so these paths are the host's, not the ones seen from inside
    # the container. Two distinct folders on the backend side (downloaded
    # samples vs manually uploaded files) — the agent checks both.
    samples_dir: str = "/opt/purplelab/data/samples"
    malware_upload_dir: str = "/opt/purplelab/data/malware_upload"
    # Output folder for forensic acquisitions (memory dump, disk clone) —
    # not a shared bind-mount with the backend like the two above, these
    # files stay on the host (potentially large). Under
    # /opt/purplelab/vmbridge-agent (not /opt/purplelab/data like
    # samples_dir/malware_upload_dir): this folder already belongs to
    # purplelab-vm (created by the agent's installation), whereas
    # /opt/purplelab itself is root:root 0755 — os.makedirs() would fail
    # there with a PermissionError if targeting /opt/purplelab/data
    # directly (validated empirically).
    forensic_dir: str = "/opt/purplelab/vmbridge-agent/forensic"

    @property
    def resolved_token(self) -> str:
        if self.vm_agent_token_file:
            return open(self.vm_agent_token_file).read().strip()
        return self.vm_agent_token


@lru_cache
def get_settings() -> Settings:
    return Settings()
