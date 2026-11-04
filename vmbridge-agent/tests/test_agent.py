from unittest.mock import patch

import pytest
from fastapi.testclient import TestClient

from app.config import get_settings
from app.main import app
from app.vbox import VBoxCommandError


@pytest.fixture(autouse=True)
def _configure_token(monkeypatch):
    monkeypatch.setenv("VM_AGENT_TOKEN", "test-token")
    get_settings.cache_clear()
    yield
    get_settings.cache_clear()


@pytest.fixture
def client():
    return TestClient(app)


AUTH = {"Authorization": "Bearer test-token"}


def test_vm_state_rejects_missing_token(client):
    response = client.get("/vm/state")
    assert response.status_code == 401


def test_vm_state_rejects_wrong_token(client):
    response = client.get("/vm/state", headers={"Authorization": "Bearer wrong"})
    assert response.status_code == 401


def test_vm_state_ok(client):
    with patch("app.main.get_vm_info", return_value={"name": "sandbox", "state": "poweroff", "snapshot": None}):
        response = client.get("/vm/state", headers=AUTH)
    assert response.status_code == 200
    assert response.json() == {"name": "sandbox", "state": "poweroff", "snapshot": None}


def test_vm_ip_ok(client):
    with patch("app.main.get_vm_ip", return_value="192.168.56.10"):
        response = client.get("/vm/ip", headers=AUTH)
    assert response.status_code == 200
    assert response.json() == {"ip": "192.168.56.10"}


def test_vm_ip_none_when_no_value_set(client):
    with patch("app.main.get_vm_ip", return_value=None):
        response = client.get("/vm/ip", headers=AUTH)
    assert response.status_code == 200
    assert response.json() == {"ip": None}


def test_vm_state_returns_502_on_vbox_error(client):
    with patch("app.main.get_vm_info", side_effect=VBoxCommandError("VBoxManage not found")):
        response = client.get("/vm/state", headers=AUTH)
    assert response.status_code == 502


@pytest.mark.parametrize(
    "path,patched_fn",
    [
        ("/vm/poweroff", "poweroff_vm"),
        ("/vm/restore", "restore_vm"),
        ("/vm/start-headless", "start_vm_headless"),
        ("/vm/disable-av", "set_defender_realtime_monitoring"),
        ("/vm/enable-av", "set_defender_realtime_monitoring"),
        ("/vm/restart-winlogbeat", "restart_winlogbeat_service"),
    ],
)
def test_vm_action_requires_token(client, path, patched_fn):
    response = client.post(path)
    assert response.status_code == 401


@pytest.mark.parametrize(
    "path,patched_fn",
    [
        ("/vm/poweroff", "poweroff_vm"),
        ("/vm/restore", "restore_vm"),
        ("/vm/start-headless", "start_vm_headless"),
        ("/vm/disable-av", "set_defender_realtime_monitoring"),
        ("/vm/enable-av", "set_defender_realtime_monitoring"),
        ("/vm/restart-winlogbeat", "restart_winlogbeat_service"),
    ],
)
def test_vm_action_ok(client, path, patched_fn):
    with patch(f"app.main.{patched_fn}") as mock_fn:
        response = client.post(path, headers=AUTH)
    assert response.status_code == 200
    assert response.json() == {"message": "ok"}
    mock_fn.assert_called_once()


def test_vm_poweroff_returns_502_on_vbox_error(client):
    with patch("app.main.poweroff_vm", side_effect=VBoxCommandError("boom")):
        response = client.post("/vm/poweroff", headers=AUTH)
    assert response.status_code == 502


def test_vm_copy_file_requires_token(client):
    response = client.post("/vm/copy-file", json={"filename": "sample.exe"})
    assert response.status_code == 401


def test_vm_copy_file_ok_from_samples_dir(client, tmp_path):
    samples_dir = tmp_path / "samples"
    samples_dir.mkdir()
    (samples_dir / "sample.exe").write_bytes(b"MZ...")
    with patch("app.main.get_settings") as mock_settings, patch("app.main.copy_file_to_vm") as mock_fn:
        mock_settings.return_value.samples_dir = str(samples_dir)
        mock_settings.return_value.malware_upload_dir = str(tmp_path / "malware_upload")
        mock_settings.return_value.resolved_token = "test-token"
        response = client.post("/vm/copy-file", json={"filename": "sample.exe"}, headers=AUTH)
    assert response.status_code == 200
    assert response.json() == {"message": "ok"}
    mock_fn.assert_called_once()
    local_path_arg = mock_fn.call_args[0][0]
    assert local_path_arg.endswith("sample.exe")


def test_vm_copy_file_ok_from_malware_upload_dir(client, tmp_path):
    # two possible shared folders on the backend side (downloaded samples
    # vs manual uploads) — the agent must find the file in the second one
    # even if it's absent from the first.
    upload_dir = tmp_path / "malware_upload"
    upload_dir.mkdir()
    (upload_dir / "sample.exe").write_bytes(b"MZ...")
    with patch("app.main.get_settings") as mock_settings, patch("app.main.copy_file_to_vm") as mock_fn:
        mock_settings.return_value.samples_dir = str(tmp_path / "samples")
        mock_settings.return_value.malware_upload_dir = str(upload_dir)
        mock_settings.return_value.resolved_token = "test-token"
        response = client.post("/vm/copy-file", json={"filename": "sample.exe"}, headers=AUTH)
    assert response.status_code == 200
    mock_fn.assert_called_once()


def test_vm_copy_file_404_when_not_found_anywhere(client, tmp_path):
    with patch("app.main.get_settings") as mock_settings:
        mock_settings.return_value.samples_dir = str(tmp_path / "samples")
        mock_settings.return_value.malware_upload_dir = str(tmp_path / "malware_upload")
        mock_settings.return_value.resolved_token = "test-token"
        response = client.post("/vm/copy-file", json={"filename": "ghost.exe"}, headers=AUTH)
    assert response.status_code == 404


def test_vm_copy_file_rejects_path_traversal(client):
    response = client.post("/vm/copy-file", json={"filename": "../../etc/passwd"}, headers=AUTH)
    assert response.status_code == 400


def test_vm_execute_file_ok(client):
    with patch("app.main.execute_file_in_vm") as mock_fn:
        response = client.post("/vm/execute-file", json={"filename": "sample.exe"}, headers=AUTH)
    assert response.status_code == 200
    mock_fn.assert_called_once_with("sample.exe")


def test_vm_execute_file_rejects_path_traversal(client):
    response = client.post("/vm/execute-file", json={"filename": "../../etc/passwd"}, headers=AUTH)
    assert response.status_code == 400


def test_vm_execute_file_returns_502_on_unsupported_extension(client):
    with patch("app.main.execute_file_in_vm", side_effect=VBoxCommandError("Unsupported extension: .zip")):
        response = client.post("/vm/execute-file", json={"filename": "archive.zip"}, headers=AUTH)
    assert response.status_code == 502


def test_vm_execute_payload_requires_token(client):
    response = client.post("/vm/execute-payload", json={"encoded_command": "d2hvYW1p"})
    assert response.status_code == 401


def test_vm_execute_payload_ok(client):
    with patch("app.main.execute_powershell_payload") as mock_fn:
        response = client.post("/vm/execute-payload", json={"encoded_command": "d2hvYW1p"}, headers=AUTH)
    assert response.status_code == 200
    mock_fn.assert_called_once_with("d2hvYW1p")


def test_vm_execute_payload_returns_502_on_vbox_error(client):
    with patch("app.main.execute_powershell_payload", side_effect=VBoxCommandError("boom")):
        response = client.post("/vm/execute-payload", json={"encoded_command": "d2hvYW1p"}, headers=AUTH)
    assert response.status_code == 502


def test_vm_execute_atomic_test_requires_token(client):
    response = client.post("/vm/execute-atomic-test", json={"technique_id": "T1059"})
    assert response.status_code == 401


def test_vm_execute_atomic_test_ok(client):
    with patch("app.main.execute_atomic_test") as mock_fn:
        response = client.post(
            "/vm/execute-atomic-test", json={"technique_id": "T1059", "test_number": 2}, headers=AUTH
        )
    assert response.status_code == 200
    mock_fn.assert_called_once_with("T1059", 2)


def test_vm_execute_atomic_test_surfaces_stdout(client):
    with patch("app.main.execute_atomic_test", return_value="PathToAtomicsFolder ...\nDone.\n"):
        response = client.post("/vm/execute-atomic-test", json={"technique_id": "T1059"}, headers=AUTH)
    assert response.status_code == 200
    assert response.json() == {"message": "ok", "output": "PathToAtomicsFolder ...\nDone.\n"}


def test_vm_execute_payload_surfaces_stdout(client):
    with patch("app.main.execute_powershell_payload", return_value="purplelab\\ada\n"):
        response = client.post("/vm/execute-payload", json={"encoded_command": "d2hvYW1p"}, headers=AUTH)
    assert response.status_code == 200
    assert response.json() == {"message": "ok", "output": "purplelab\\ada\n"}


def test_vm_poweroff_has_no_output_key(client):
    with patch("app.main.poweroff_vm", return_value=None):
        response = client.post("/vm/poweroff", headers=AUTH)
    assert response.status_code == 200
    assert response.json() == {"message": "ok"}


def test_vm_execute_atomic_test_returns_502_on_vbox_error(client):
    with patch("app.main.execute_atomic_test", side_effect=VBoxCommandError("boom")):
        response = client.post("/vm/execute-atomic-test", json={"technique_id": "T1059"}, headers=AUTH)
    assert response.status_code == 502


def test_vm_forensic_requires_token(client):
    response = client.post("/vm/forensic", json={"acquisition_type": "memory"})
    assert response.status_code == 401


def test_vm_forensic_memory_calls_dump_memory(client):
    with patch("app.main.dump_memory", return_value="/opt/purplelab/data/forensic/sandbox.dmp") as mock_fn:
        response = client.post("/vm/forensic", json={"acquisition_type": "memory"}, headers=AUTH)
    assert response.status_code == 200
    assert response.json() == {"message": "ok", "output": "/opt/purplelab/data/forensic/sandbox.dmp"}
    mock_fn.assert_called_once()


def test_vm_forensic_disk_calls_clone_disk(client):
    with patch("app.main.clone_disk", return_value="/opt/purplelab/data/forensic/sandbox.vdi") as mock_fn:
        response = client.post("/vm/forensic", json={"acquisition_type": "disk"}, headers=AUTH)
    assert response.status_code == 200
    assert response.json() == {"message": "ok", "output": "/opt/purplelab/data/forensic/sandbox.vdi"}
    mock_fn.assert_called_once()


def test_vm_forensic_rejects_unknown_acquisition_type(client):
    response = client.post("/vm/forensic", json={"acquisition_type": "bogus"}, headers=AUTH)
    assert response.status_code == 400


def test_vm_forensic_returns_502_on_vbox_error(client):
    with patch("app.main.dump_memory", side_effect=VBoxCommandError("boom")):
        response = client.post("/vm/forensic", json={"acquisition_type": "memory"}, headers=AUTH)
    assert response.status_code == 502


def test_host_system_requires_token(client):
    response = client.get("/host/system")
    assert response.status_code == 401


def test_host_system_ok(client):
    fake_metrics = {
        "memory": {"percent": 42.0, "total_gb": 16.0, "used_gb": 6.7},
        "disk": {"percent": 61.0, "total_gb": 100.0, "used_gb": 61.0},
        "cpu": {"percent": 13.0},
    }
    with patch("app.main.get_system_metrics", return_value=fake_metrics):
        response = client.get("/host/system", headers=AUTH)
    assert response.status_code == 200
    assert response.json() == fake_metrics
