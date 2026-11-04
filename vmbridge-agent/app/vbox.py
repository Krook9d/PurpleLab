import os
import subprocess
import threading
import time
from contextlib import nullcontext

from app.config import get_settings

POWERSHELL_EXE = "C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe"


class VBoxCommandError(Exception):
    pass


# FastAPI runs sync endpoints in a thread pool, so the Health page's
# /vm/state polling (showvminfo) can overlap a startvm/poweroff/restore.
# VirtualBox doesn't tolerate that on the same machine: startvm then fails
# with "already locked by a session (or being locked or unlocked)" and
# showvminfo with "The object is not ready" (validated empirically). These
# subcommands are therefore serialized. guestcontrol, debugvm, etc. are
# left out on purpose: they only run against an already running VM (no
# conflict observed) and can last minutes, which would block the polling.
_MACHINE_STATE_SUBCOMMANDS = {"showvminfo", "startvm", "controlvm", "snapshot"}
_machine_state_lock = threading.Lock()


def run_vboxmanage(args: list[str], timeout: int = 10, privileged: bool = False) -> str:
    """Runs VBoxManage with a list of arguments (never shell=True) and
    returns stdout. Injectable function via unittest.mock.patch for the
    tests, since VBoxManage isn't installed on every machine (dev/CI)
    running this agent.

    privileged=True prefixes the command with sudo, scoped via
    /etc/sudoers.d/purplelab-vm (see sudoers.d/purplelab-vm.example).
    Not used for VM control (poweroff/startvm/snapshot) since
    Milestone 2: Ubuntu's virtualbox-dkms package forces /dev/vboxdrv to
    root:root 0600 by default (60-virtualbox-dkms.rules rule), but
    alternating root/group access on the same .vbox file makes its
    permissions "flap" (VirtualBox rewrites this file with the identity
    of the current process on every state change) — breaking
    unprivileged reads right afterward. Fixed by restoring the standard
    VirtualBox model via a local udev rule (see
    /etc/udev/rules.d/70-vboxdrv-vboxusers.rules on the target machine,
    outside the repo) giving vboxusers direct access to the device,
    without sudo. This parameter remains available for future operations
    that would genuinely need root (e.g. dumpvmcore, clonemedium —
    Milestone 3+)."""
    settings = get_settings()
    command = [settings.vboxmanage_bin, *args]
    if privileged:
        # sudo resets the environment by default (env_reset): without
        # --preserve-env, VBoxManage launched as root doesn't see
        # VBOX_USER_HOME (set for the purplelab-vm user by the systemd
        # service) and falls back to root's default VirtualBox profile,
        # where the "sandbox" VM isn't registered — resulting in a
        # misleading "Machine 'sandbox' is not currently running" even
        # though the VM is genuinely running (validated empirically). The
        # scoped sudoers entry must carry the SETENV: tag for
        # --preserve-env to be honored (see sudoers.d/purplelab-vm.example).
        command = ["sudo", "-n", "--preserve-env=VBOX_USER_HOME", *command]
    lock = _machine_state_lock if args and args[0] in _MACHINE_STATE_SUBCOMMANDS else nullcontext()
    try:
        with lock:
            result = subprocess.run(command, capture_output=True, text=True, timeout=timeout, check=True)
    except subprocess.CalledProcessError as exc:
        raise VBoxCommandError(f"{exc}: {exc.stderr.strip()}") from exc
    except (subprocess.TimeoutExpired, FileNotFoundError) as exc:
        raise VBoxCommandError(str(exc)) from exc
    return result.stdout


def _parse_machinereadable(output: str) -> dict[str, str]:
    parsed: dict[str, str] = {}
    for line in output.splitlines():
        if "=" not in line:
            continue
        key, _, value = line.partition("=")
        parsed[key] = value.strip('"')
    return parsed


def get_vm_info() -> dict:
    settings = get_settings()
    output = run_vboxmanage(["showvminfo", settings.vm_name, "--machinereadable"])
    fields = _parse_machinereadable(output)
    if "VMState" not in fields:
        raise VBoxCommandError("VMState not found in showvminfo output")
    return {
        "name": fields.get("name", settings.vm_name),
        "state": fields["VMState"],
        "snapshot": fields.get("CurrentSnapshotName"),
    }


def get_vm_ip() -> str | None:
    settings = get_settings()
    output = run_vboxmanage(
        ["guestproperty", "get", settings.vm_name, "/VirtualBox/GuestInfo/Net/0/V4/IP"]
    )
    output = output.strip()
    if output == "No value set!":
        return None
    # output format: "Value: 192.168.56.10"
    if output.lower().startswith("value:"):
        return output.split(":", 1)[1].strip()
    return output


def poweroff_vm() -> None:
    settings = get_settings()
    run_vboxmanage(["controlvm", settings.vm_name, "poweroff"])


def start_vm_headless() -> None:
    settings = get_settings()
    run_vboxmanage(["startvm", settings.vm_name, "--type", "headless"], timeout=30)


def restore_snapshot(snapshot_name: str = "Snapshot1") -> None:
    settings = get_settings()
    run_vboxmanage(["snapshot", settings.vm_name, "restore", snapshot_name], timeout=30)


def restore_vm() -> None:
    """Reproduces the behavior of the old manageVM.py `restore` command:
    poweroff, then restore snapshot, then headless restart. The old
    script ignored the poweroff return code (no check=True) to stay
    idempotent when the VM is already off — we reproduce that explicitly
    here rather than letting check=True fail the whole restore for that
    one reason."""
    try:
        poweroff_vm()
    except VBoxCommandError:
        pass
    restore_snapshot()
    time.sleep(1)
    start_vm_headless()


def _run_guest_powershell(command: str, timeout: int = 30) -> str:
    settings = get_settings()
    if not settings.vm_guest_username or not settings.vm_guest_password:
        raise VBoxCommandError("VM_GUEST_USERNAME/VM_GUEST_PASSWORD not configured")
    return run_vboxmanage(
        [
            "guestcontrol", settings.vm_name, "run",
            "--exe", POWERSHELL_EXE,
            "--username", settings.vm_guest_username,
            "--password", settings.vm_guest_password,
            "--",
            "-Command", command,
        ],
        timeout=timeout,
    )


def set_defender_realtime_monitoring(enabled: bool) -> None:
    flag = "$false" if enabled else "$true"
    _run_guest_powershell(
        f"& {{Start-Process powershell -ArgumentList 'Set-MpPreference -DisableRealtimeMonitoring {flag}' -Verb RunAs}}"
    )


def restart_winlogbeat_service() -> None:
    _run_guest_powershell(
        "& {Start-Process powershell -ArgumentList 'Restart-Service winlogbeat' -Verb RunAs}"
    )


# The Windows "oem" profile is an account created during the VM's existing
# Vagrant/Ansible provisioning (out of scope for this plan) — its path is
# fixed regardless of the configurable auth credentials
# (VM_GUEST_USERNAME/PASSWORD), hence the hardcode here.
GUEST_SAMPLES_DIR = "C:\\Users\\oem\\Documents\\samples"


def copy_file_to_vm(local_path: str, filename: str) -> None:
    """Reproduces the manageVM.py upload behavior: copies a file from the
    host's shared folder (identical bind-mount on both sides) to the VM's samples folder, via guestcontrol copyto."""
    settings = get_settings()
    if not settings.vm_guest_username or not settings.vm_guest_password:
        raise VBoxCommandError("VM_GUEST_USERNAME/VM_GUEST_PASSWORD not configured")
    dest = f"{GUEST_SAMPLES_DIR}\\{filename}"
    run_vboxmanage(
        [
            "guestcontrol", settings.vm_name, "copyto",
            "--username", settings.vm_guest_username,
            "--password", settings.vm_guest_password,
            local_path, dest,
        ],
        timeout=60,
    )


def _run_guest_exe(exe_path: str, args: list[str]) -> str:
    settings = get_settings()
    if not settings.vm_guest_username or not settings.vm_guest_password:
        raise VBoxCommandError("VM_GUEST_USERNAME/VM_GUEST_PASSWORD not configured")
    return run_vboxmanage(
        [
            "guestcontrol", settings.vm_name, "run",
            "--exe", exe_path,
            "--username", settings.vm_guest_username,
            "--password", settings.vm_guest_password,
            "--",
            *args,
        ],
        timeout=30,
    )


def execute_file_in_vm(filename: str) -> None:
    """Reproduces the extension-based dispatch table of the old
    malware_executable.py (VBoxManage called with a list of arguments,
    never shell=True)."""
    file_path = f"{GUEST_SAMPLES_DIR}\\{filename}"
    extension = filename.rsplit(".", 1)[-1].lower() if "." in filename else ""

    if extension in {"exe", "dll", "bin"}:
        _run_guest_exe(file_path, [])
    elif extension == "py":
        _run_guest_exe("C:\\Python39\\python.exe", [file_path])
    elif extension == "ps1":
        _run_guest_exe(
            POWERSHELL_EXE,
            [
                "-Command",
                f"& {{Start-Process powershell -ArgumentList '-ExecutionPolicy Bypass -File {file_path}' -Verb RunAs}}",
            ],
        )
    elif extension in {"docx", "xlsx", "ods"}:
        _run_guest_exe(
            "C:\\Program Files\\LibreOffice\\program\\soffice.exe",
            ["--writer", "--calc", "--impress", "--draw", file_path],
        )
    elif extension == "pdf":
        _run_guest_exe("C:\\Program Files\\Adobe\\Acrobat DC\\Acrobat\\Acrobat.exe", [file_path])
    else:
        raise VBoxCommandError(f"Unsupported extension: .{extension}")


ATOMIC_RED_TEAM_MODULE = "C:\\AtomicRedTeam\\invoke-atomicredteam\\Invoke-AtomicRedTeam.psd1"


def execute_atomic_test(technique_id: str, test_number: int | None = None) -> str:
    """Reproduces the Atomic Red Team execution from the old Web/app.py
    (Import-Module Invoke-AtomicRedTeam then Invoke-AtomicTest <id>),
    which was wired up with no authentication at all on the legacy side
    (Flask route /mitre_attack_execution) — reimplemented here via
    guestcontrol with a list of arguments, behind require_token like the
    rest of the agent. More generous timeout than the other guest
    commands (90s): Invoke-AtomicTest may need to install prerequisites
    before running the test itself."""
    test_arg = technique_id if test_number is None else f"{technique_id}-{test_number}"
    command = f"Import-Module '{ATOMIC_RED_TEAM_MODULE}' -Force; Invoke-AtomicTest {test_arg}"
    return _run_guest_powershell(command, timeout=90)


def execute_powershell_payload(encoded_command: str) -> str:
    """Runs arbitrary PowerShell in the VM via -EncodedCommand (the
    backend encodes the content as base64 UTF-16LE): a core feature of
    the lab (one-click payload execution for any authenticated analyst),
    reimplemented here via guestcontrol run with a list of
    arguments rather than the old Web/app.py, which ran it with no
    authentication whatsoever."""
    return _run_guest_exe(POWERSHELL_EXE, ["-EncodedCommand", encoded_command])


def _find_disk_medium_path() -> str:
    """Resolves the VM's disk path dynamically via showvminfo rather than
    the hardcoded path from the old forensic_acquisition.py
    (/root/VirtualBox VMs/sandbox/..., which assumes a VM registered
    under root — doesn't match this setup where the VM runs under
    purplelab-vm/vboxusers). Looks for the first storage attachment value
    that looks like a disk file, regardless of the controller name
    (SATA/IDE/SCSI)."""
    settings = get_settings()
    output = run_vboxmanage(["showvminfo", settings.vm_name, "--machinereadable"])
    fields = _parse_machinereadable(output)
    for value in fields.values():
        if value.lower().endswith((".vdi", ".vmdk", ".vhd")):
            return value
    raise VBoxCommandError("no disk medium found in showvminfo output")


def dump_memory() -> str:
    """Raw memory dump of the VM (VBoxManage debugvm dumpvmcore) — NOT
    privileged: the old forensic_acquisition.py itself didn't use sudo
    for this command (only for clonemedium disk), and it's the same
    class of operation as poweroff/restore/startvm (VBoxManage →
    VBoxSVC → /dev/vboxdrv), which already work without sudo since the
    Milestone 2 udev rule. Going through root here actually broke access: a
    separate root process doesn't share purplelab-vm's "live" VBoxSVC
    session even with an identical VBOX_USER_HOME (validated empirically
    at Milestone 6 — root sees the VM as registered but never as
    "running")."""
    settings = get_settings()
    os.makedirs(settings.forensic_dir, exist_ok=True)
    output_file = os.path.join(settings.forensic_dir, f"{settings.vm_name}.dmp")
    if os.path.exists(output_file):
        os.remove(output_file)
    run_vboxmanage(
        ["debugvm", settings.vm_name, "dumpvmcore", f"--filename={output_file}"],
        timeout=300,
    )
    return output_file


def clone_disk() -> str:
    """Clones the VM's disk (VBoxManage clonemedium disk). Not privileged:
    the disk image is group-readable/writable by vboxusers (0660,
    confirmed on this host), same as everything else purplelab-vm already
    does without sudo since the Milestone 2 udev fix — sudo was assumed
    necessary here (mirroring the legacy script) but never actually
    re-verified against the real file permissions, the same mistake
    originally made for dumpvmcore (see Milestone 6 notes). Kept non-root:
    every earlier use of privileged=True for this operation left
    VirtualBox.xml owned root:root, locking purplelab-vm out of every
    other VBoxManage call (poweroff/restore/start/dumpvmcore) until it was
    manually chowned back — a real recurring hazard, not a one-off."""
    settings = get_settings()
    os.makedirs(settings.forensic_dir, exist_ok=True)
    source = _find_disk_medium_path()
    output_file = os.path.join(settings.forensic_dir, f"{settings.vm_name}.vdi")
    if os.path.exists(output_file):
        os.remove(output_file)
    run_vboxmanage(
        ["clonemedium", "disk", source, output_file, "--format", "VDI", "--variant", "Standard"],
        timeout=300,
    )
    return output_file
