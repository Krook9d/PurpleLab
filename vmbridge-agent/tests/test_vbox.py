from unittest.mock import call, patch

import pytest

from app.vbox import (
    ATOMIC_RED_TEAM_MODULE,
    POWERSHELL_EXE,
    VBoxCommandError,
    clone_disk,
    copy_file_to_vm,
    dump_memory,
    execute_atomic_test,
    execute_file_in_vm,
    execute_powershell_payload,
    get_vm_info,
    get_vm_ip,
    poweroff_vm,
    restart_winlogbeat_service,
    restore_vm,
    run_vboxmanage,
    set_defender_realtime_monitoring,
    start_vm_headless,
)


def test_run_vboxmanage_raises_on_missing_binary():
    with patch("app.vbox.get_settings") as mock_settings:
        mock_settings.return_value.vboxmanage_bin = "/nonexistent/VBoxManage"
        with pytest.raises(VBoxCommandError):
            run_vboxmanage(["--version"])


def test_get_vm_info_parses_machinereadable_output():
    fake_output = (
        'name="sandbox"\nVMState="poweroff"\nVMStateChangeTime="..."\n'
        'CurrentSnapshotName="Snapshot1"\n'
    )
    with patch("app.vbox.run_vboxmanage", return_value=fake_output):
        assert get_vm_info() == {"name": "sandbox", "state": "poweroff", "snapshot": "Snapshot1"}


def test_get_vm_info_snapshot_none_when_absent():
    fake_output = 'name="sandbox"\nVMState="running"\n'
    with patch("app.vbox.run_vboxmanage", return_value=fake_output):
        assert get_vm_info() == {"name": "sandbox", "state": "running", "snapshot": None}


def test_get_vm_info_raises_if_state_missing():
    with patch("app.vbox.run_vboxmanage", return_value="name=\"sandbox\"\n"):
        with pytest.raises(VBoxCommandError):
            get_vm_info()


def test_get_vm_ip_parses_value():
    with patch("app.vbox.run_vboxmanage", return_value="Value: 192.168.56.10\n"):
        assert get_vm_ip() == "192.168.56.10"


def test_get_vm_ip_returns_none_when_not_set():
    with patch("app.vbox.run_vboxmanage", return_value="No value set!\n"):
        assert get_vm_ip() is None


def test_run_vboxmanage_never_uses_shell_true():
    with patch("app.vbox.subprocess.run") as mock_run:
        mock_run.return_value.stdout = "ok"
        run_vboxmanage(["showvminfo", "sandbox"])
        _, kwargs = mock_run.call_args
        assert kwargs.get("shell") is not True
        args_passed = mock_run.call_args[0][0]
        assert isinstance(args_passed, list)


def test_poweroff_vm_calls_controlvm():
    with patch("app.vbox.run_vboxmanage") as mock_run:
        poweroff_vm()
        mock_run.assert_called_once_with(["controlvm", "sandbox", "poweroff"])


def test_start_vm_headless_calls_startvm():
    with patch("app.vbox.run_vboxmanage") as mock_run:
        start_vm_headless()
        mock_run.assert_called_once_with(["startvm", "sandbox", "--type", "headless"], timeout=30)


def test_restore_vm_sequence_matches_legacy_manageVM():
    # reproduces exactly the order of the old manageVM.py "restore":
    # poweroff -> restore snapshot -> sleep -> start headless
    with patch("app.vbox.run_vboxmanage") as mock_run, patch("app.vbox.time.sleep") as mock_sleep:
        restore_vm()
        assert mock_run.call_args_list == [
            call(["controlvm", "sandbox", "poweroff"]),
            call(["snapshot", "sandbox", "restore", "Snapshot1"], timeout=30),
            call(["startvm", "sandbox", "--type", "headless"], timeout=30),
        ]
        mock_sleep.assert_called_once_with(1)


def test_restore_vm_tolerates_vm_already_powered_off():
    # reproduces the idempotence of the old manageVM.py: poweroff fails
    # if the VM is already off, but the restore must proceed anyway.
    with patch("app.vbox.run_vboxmanage") as mock_run, patch("app.vbox.time.sleep"):
        mock_run.side_effect = [
            VBoxCommandError("Machine 'sandbox' is not currently running"),
            "",
            "",
        ]
        restore_vm()  # must not raise
        assert mock_run.call_count == 3


def test_run_vboxmanage_privileged_prefixes_sudo():
    with patch("app.vbox.subprocess.run") as mock_run:
        mock_run.return_value.stdout = "ok"
        run_vboxmanage(["controlvm", "sandbox", "poweroff"], privileged=True)
        args_passed = mock_run.call_args[0][0]
        assert args_passed[:4] == ["sudo", "-n", "--preserve-env=VBOX_USER_HOME", "VBoxManage"]


def test_run_vboxmanage_not_privileged_by_default():
    with patch("app.vbox.subprocess.run") as mock_run:
        mock_run.return_value.stdout = "ok"
        run_vboxmanage(["showvminfo", "sandbox"])
        args_passed = mock_run.call_args[0][0]
        assert args_passed[0] == "VBoxManage"


def test_set_defender_realtime_monitoring_requires_guest_creds():
    with patch("app.vbox.get_settings") as mock_settings:
        mock_settings.return_value.vm_guest_username = ""
        mock_settings.return_value.vm_guest_password = ""
        with pytest.raises(VBoxCommandError):
            set_defender_realtime_monitoring(enabled=True)


def test_set_defender_realtime_monitoring_builds_expected_command():
    with patch("app.vbox.get_settings") as mock_settings, patch("app.vbox.run_vboxmanage") as mock_run:
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.vm_guest_username = "oem"
        mock_settings.return_value.vm_guest_password = "oem"
        set_defender_realtime_monitoring(enabled=False)
        args = mock_run.call_args[0][0]
        assert args[0:3] == ["guestcontrol", "sandbox", "run"]
        assert "--username" in args and "oem" in args
        assert "DisableRealtimeMonitoring $true" in args[-1]


def test_restart_winlogbeat_service_builds_expected_command():
    with patch("app.vbox.get_settings") as mock_settings, patch("app.vbox.run_vboxmanage") as mock_run:
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.vm_guest_username = "oem"
        mock_settings.return_value.vm_guest_password = "oem"
        restart_winlogbeat_service()
        args = mock_run.call_args[0][0]
        assert "Restart-Service winlogbeat" in args[-1]


def test_copy_file_to_vm_requires_guest_creds():
    with patch("app.vbox.get_settings") as mock_settings:
        mock_settings.return_value.vm_guest_username = ""
        mock_settings.return_value.vm_guest_password = ""
        with pytest.raises(VBoxCommandError):
            copy_file_to_vm("/tmp/evil.exe", "evil.exe")


def test_copy_file_to_vm_builds_expected_command():
    with patch("app.vbox.get_settings") as mock_settings, patch("app.vbox.run_vboxmanage") as mock_run:
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.vm_guest_username = "oem"
        mock_settings.return_value.vm_guest_password = "oem"
        copy_file_to_vm("/opt/purplelab/data/malware_upload/sample.exe", "sample.exe")
        args, kwargs = mock_run.call_args
        assert args[0][:3] == ["guestcontrol", "sandbox", "copyto"]
        assert args[0][-2:] == [
            "/opt/purplelab/data/malware_upload/sample.exe",
            "C:\\Users\\oem\\Documents\\samples\\sample.exe",
        ]
        assert kwargs.get("timeout") == 60


@pytest.mark.parametrize(
    "filename,expected_exe_fragment",
    [
        ("sample.exe", "C:\\Users\\oem\\Documents\\samples\\sample.exe"),
        ("sample.dll", "C:\\Users\\oem\\Documents\\samples\\sample.dll"),
        ("sample.bin", "C:\\Users\\oem\\Documents\\samples\\sample.bin"),
    ],
)
def test_execute_file_in_vm_direct_exe(filename, expected_exe_fragment):
    with patch("app.vbox.get_settings") as mock_settings, patch("app.vbox.run_vboxmanage") as mock_run:
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.vm_guest_username = "oem"
        mock_settings.return_value.vm_guest_password = "oem"
        execute_file_in_vm(filename)
        args = mock_run.call_args[0][0]
        assert args[:3] == ["guestcontrol", "sandbox", "run"]
        assert expected_exe_fragment in args


def test_execute_file_in_vm_python_script():
    with patch("app.vbox.get_settings") as mock_settings, patch("app.vbox.run_vboxmanage") as mock_run:
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.vm_guest_username = "oem"
        mock_settings.return_value.vm_guest_password = "oem"
        execute_file_in_vm("script.py")
        args = mock_run.call_args[0][0]
        assert "C:\\Python39\\python.exe" in args
        assert "C:\\Users\\oem\\Documents\\samples\\script.py" in args


def test_execute_file_in_vm_powershell_script():
    with patch("app.vbox.get_settings") as mock_settings, patch("app.vbox.run_vboxmanage") as mock_run:
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.vm_guest_username = "oem"
        mock_settings.return_value.vm_guest_password = "oem"
        execute_file_in_vm("script.ps1")
        args = mock_run.call_args[0][0]
        assert POWERSHELL_EXE in args
        assert "-ExecutionPolicy Bypass -File C:\\Users\\oem\\Documents\\samples\\script.ps1" in args[-1]


@pytest.mark.parametrize("filename", ["doc.docx", "sheet.xlsx", "doc.ods"])
def test_execute_file_in_vm_office_documents(filename):
    with patch("app.vbox.get_settings") as mock_settings, patch("app.vbox.run_vboxmanage") as mock_run:
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.vm_guest_username = "oem"
        mock_settings.return_value.vm_guest_password = "oem"
        execute_file_in_vm(filename)
        args = mock_run.call_args[0][0]
        assert "C:\\Program Files\\LibreOffice\\program\\soffice.exe" in args
        assert f"C:\\Users\\oem\\Documents\\samples\\{filename}" in args


def test_execute_file_in_vm_pdf():
    with patch("app.vbox.get_settings") as mock_settings, patch("app.vbox.run_vboxmanage") as mock_run:
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.vm_guest_username = "oem"
        mock_settings.return_value.vm_guest_password = "oem"
        execute_file_in_vm("report.pdf")
        args = mock_run.call_args[0][0]
        assert "C:\\Program Files\\Adobe\\Acrobat DC\\Acrobat\\Acrobat.exe" in args


def test_execute_file_in_vm_unsupported_extension_raises():
    with patch("app.vbox.get_settings") as mock_settings:
        mock_settings.return_value.vm_guest_username = "oem"
        mock_settings.return_value.vm_guest_password = "oem"
        with pytest.raises(VBoxCommandError):
            execute_file_in_vm("archive.tar.gz")


def test_execute_powershell_payload_builds_expected_command():
    with patch("app.vbox.get_settings") as mock_settings, patch("app.vbox.run_vboxmanage") as mock_run:
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.vm_guest_username = "oem"
        mock_settings.return_value.vm_guest_password = "oem"
        mock_run.return_value = "purplelab\\ada\n"
        result = execute_powershell_payload("d2hvYW1p")
        args = mock_run.call_args[0][0]
        assert args[:3] == ["guestcontrol", "sandbox", "run"]
        assert POWERSHELL_EXE in args
        assert args[-2:] == ["-EncodedCommand", "d2hvYW1p"]
        assert result == "purplelab\\ada\n"


def test_execute_powershell_payload_requires_guest_creds():
    with patch("app.vbox.get_settings") as mock_settings:
        mock_settings.return_value.vm_guest_username = ""
        mock_settings.return_value.vm_guest_password = ""
        with pytest.raises(VBoxCommandError):
            execute_powershell_payload("d2hvYW1p")


def test_execute_atomic_test_builds_expected_command_without_test_number():
    with patch("app.vbox.get_settings") as mock_settings, patch("app.vbox.run_vboxmanage") as mock_run:
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.vm_guest_username = "oem"
        mock_settings.return_value.vm_guest_password = "oem"
        mock_run.return_value = "PathToAtomicsFolder ...\nDone.\n"
        result = execute_atomic_test("T1059")
        args = mock_run.call_args[0][0]
        assert args[:3] == ["guestcontrol", "sandbox", "run"]
        command = args[-1]
        assert ATOMIC_RED_TEAM_MODULE in command
        assert command.endswith("Invoke-AtomicTest T1059")
        # Generous timeout (90s): Invoke-AtomicTest may need to install
        # prerequisites before running the test itself.
        assert mock_run.call_args.kwargs["timeout"] == 90
        assert result == "PathToAtomicsFolder ...\nDone.\n"


def test_execute_atomic_test_builds_expected_command_with_test_number():
    with patch("app.vbox.get_settings") as mock_settings, patch("app.vbox.run_vboxmanage") as mock_run:
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.vm_guest_username = "oem"
        mock_settings.return_value.vm_guest_password = "oem"
        execute_atomic_test("T1059", test_number=2)
        command = mock_run.call_args[0][0][-1]
        assert command.endswith("Invoke-AtomicTest T1059-2")


def test_execute_atomic_test_requires_guest_creds():
    with patch("app.vbox.get_settings") as mock_settings:
        mock_settings.return_value.vm_guest_username = ""
        mock_settings.return_value.vm_guest_password = ""
        with pytest.raises(VBoxCommandError):
            execute_atomic_test("T1059")


def test_dump_memory_builds_expected_command_not_privileged(tmp_path):
    # No sudo: same class of operation as poweroff/restore, which already
    # work without privilege since the Milestone 2 udev rule — and root
    # doesn't share purplelab-vm's "live" VBoxSVC session anyway
    # (validated empirically at Milestone 6).
    with patch("app.vbox.get_settings") as mock_settings, patch("app.vbox.run_vboxmanage") as mock_run:
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.forensic_dir = str(tmp_path)
        result = dump_memory()
        args, kwargs = mock_run.call_args
        assert args[0][:3] == ["debugvm", "sandbox", "dumpvmcore"]
        assert args[0][3] == f"--filename={tmp_path}/sandbox.dmp"
        assert kwargs.get("privileged", False) is False
        assert result == f"{tmp_path}/sandbox.dmp"


def test_dump_memory_removes_previous_dump_first(tmp_path):
    existing = tmp_path / "sandbox.dmp"
    existing.write_text("stale")
    with patch("app.vbox.get_settings") as mock_settings, patch("app.vbox.run_vboxmanage"):
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.forensic_dir = str(tmp_path)
        dump_memory()
    assert not existing.exists()


def test_clone_disk_resolves_source_path_dynamically(tmp_path):
    machinereadable_output = (
        'name="sandbox"\n'
        'VMState="poweroff"\n'
        '"SATA-0-0"="/home/purplelab-vm/VirtualBox VMs/sandbox/sandbox.vdi"\n'
    )
    with patch("app.vbox.get_settings") as mock_settings, patch(
        "app.vbox.run_vboxmanage", return_value=machinereadable_output
    ) as mock_run:
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.forensic_dir = str(tmp_path)
        result = clone_disk()
        clone_call_args = mock_run.call_args_list[-1]
        args, kwargs = clone_call_args
        assert args[0][:3] == ["clonemedium", "disk", "/home/purplelab-vm/VirtualBox VMs/sandbox/sandbox.vdi"]
        assert kwargs["privileged"] is True
        assert result == f"{tmp_path}/sandbox.vdi"


def test_clone_disk_raises_when_no_disk_medium_found(tmp_path):
    with patch("app.vbox.get_settings") as mock_settings, patch(
        "app.vbox.run_vboxmanage", return_value='name="sandbox"\nVMState="poweroff"\n'
    ):
        mock_settings.return_value.vm_name = "sandbox"
        mock_settings.return_value.forensic_dir = str(tmp_path)
        with pytest.raises(VBoxCommandError):
            clone_disk()


def test_run_vboxmanage_serializes_machine_state_commands():
    # Reproduces the Health page race: showvminfo polling overlapping a
    # startvm must never reach VBoxManage concurrently.
    import threading
    import time

    active = 0
    max_active = 0
    counter_lock = threading.Lock()

    def fake_run(*args, **kwargs):
        nonlocal active, max_active
        with counter_lock:
            active += 1
            max_active = max(max_active, active)
        time.sleep(0.05)
        with counter_lock:
            active -= 1
        return type("Result", (), {"stdout": ""})()

    with patch("app.vbox.subprocess.run", side_effect=fake_run):
        threads = [
            threading.Thread(target=run_vboxmanage, args=(args,))
            for args in (["showvminfo", "sandbox"], ["startvm", "sandbox"], ["showvminfo", "sandbox"])
        ]
        for thread in threads:
            thread.start()
        for thread in threads:
            thread.join()

    assert max_active == 1


def test_run_vboxmanage_does_not_serialize_guestcontrol():
    from app import vbox

    def fake_run(*args, **kwargs):
        return type("Result", (), {"stdout": ""})()

    with patch("app.vbox.subprocess.run", side_effect=fake_run):
        with vbox._machine_state_lock:
            # Would deadlock (and hang the test) if guestcontrol took the lock.
            run_vboxmanage(["guestcontrol", "sandbox", "run"])
