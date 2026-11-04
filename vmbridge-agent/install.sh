#!/usr/bin/env bash
# Automates the 7 manual steps from vmbridge-agent/README.md#installation.
# Safe to re-run (every step is idempotent) — e.g. after `git pull`ing an
# app/ change, though `Redeploying after a code change` in the README is
# the lighter-weight path for that specific case.
#
# Usage: sudo ./vmbridge-agent/install.sh
# Optional env vars (defaults match the Ansible-provisioned sandbox VM):
#   VM_GUEST_USERNAME=oem VM_GUEST_PASSWORD=oem sudo -E ./vmbridge-agent/install.sh

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Must run as root (sudo ./vmbridge-agent/install.sh)." >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
INSTALL_DIR="/opt/purplelab/vmbridge-agent"
VM_GUEST_USERNAME="${VM_GUEST_USERNAME:-oem}"
VM_GUEST_PASSWORD="${VM_GUEST_PASSWORD:-oem}"

echo "==> 1/7: dedicated system account (purplelab-vm)"
# vboxusers is normally created by the virtualbox package (installed by the
# Ansible playbook in the next README step). Create it here too so this
# script works regardless of which of the two steps runs first.
if ! getent group vboxusers &>/dev/null; then
  groupadd --system vboxusers
fi
if ! id purplelab-vm &>/dev/null; then
  useradd --system --no-create-home --groups vboxusers purplelab-vm
else
  usermod --append --groups vboxusers purplelab-vm
fi

echo "==> 2/7: udev rule for /dev/vboxdrv"
cp "$SCRIPT_DIR/udev/70-vboxdrv-vboxusers.rules" /etc/udev/rules.d/
udevadm control --reload-rules
if [ -e /dev/vboxdrv ]; then
  chgrp vboxusers /dev/vboxdrv
  chmod 0660 /dev/vboxdrv
else
  echo "    /dev/vboxdrv not present yet — install/load VirtualBox first, the rule will apply on next load."
fi

echo "==> 3/7: scoped sudoers (clonemedium disk, forensic disk acquisition only)"
cp "$SCRIPT_DIR/sudoers.d/purplelab-vm.example" /etc/sudoers.d/purplelab-vm
chmod 0440 /etc/sudoers.d/purplelab-vm
visudo -cf /etc/sudoers.d/purplelab-vm

echo "==> 4/7: Python environment"
mkdir -p "$INSTALL_DIR"
cp -r "$SCRIPT_DIR/app" "$SCRIPT_DIR/requirements.txt" "$INSTALL_DIR/"
if [ ! -d "$INSTALL_DIR/.venv" ]; then
  python3 -m venv "$INSTALL_DIR/.venv"
fi
"$INSTALL_DIR/.venv/bin/pip" install --quiet --upgrade pip
"$INSTALL_DIR/.venv/bin/pip" install --quiet -r "$INSTALL_DIR/requirements.txt"
chown -R purplelab-vm:vboxusers "$INSTALL_DIR"

echo "==> 5/7: shared token"
if [ ! -f "$REPO_ROOT/secrets/vm_agent_token" ]; then
  echo "    $REPO_ROOT/secrets/vm_agent_token not found." >&2
  echo "    Run 'for f in secrets/*.example; do cp \"\$f\" \"\${f%.example}\"; done' at the repo root first, then re-run this script." >&2
  exit 1
fi
mkdir -p "$INSTALL_DIR/secrets"
cp "$REPO_ROOT/secrets/vm_agent_token" "$INSTALL_DIR/secrets/vm_agent_token"
chown -R purplelab-vm:vboxusers "$INSTALL_DIR/secrets"

echo "==> 6/7: systemd service"
# SAMPLES_DIR/MALWARE_UPLOAD_DIR derived from this checkout's own location
# (same bind-mounts as docker-compose.yml's backend service, see
# ../docker-compose.yml) — no manual path editing needed, unlike the
# template's own CHANGE_ME placeholders.
sed \
  -e "s#Environment=VM_GUEST_USERNAME=CHANGE_ME#Environment=VM_GUEST_USERNAME=$VM_GUEST_USERNAME#" \
  -e "s#Environment=VM_GUEST_PASSWORD=CHANGE_ME#Environment=VM_GUEST_PASSWORD=$VM_GUEST_PASSWORD#" \
  -e "s#Environment=SAMPLES_DIR=.*#Environment=SAMPLES_DIR=$REPO_ROOT/Downloaded/samples#" \
  -e "s#Environment=MALWARE_UPLOAD_DIR=.*#Environment=MALWARE_UPLOAD_DIR=$REPO_ROOT/Downloaded/malware_upload#" \
  "$SCRIPT_DIR/systemd/vmbridge-agent.service" > /etc/systemd/system/vmbridge-agent.service
systemctl daemon-reload
systemctl enable --now vmbridge-agent
sleep 1
systemctl --no-pager status vmbridge-agent || true

echo "==> 7/7: firewall (best effort — restrict the agent's port to the Docker bridge subnet)"
if command -v ufw &>/dev/null; then
  ufw allow from 172.16.0.0/12 to any port 8088 proto tcp || true
else
  echo "    ufw not installed, skipping — restrict access to :8088 by whatever firewall this host uses."
fi

echo
echo "Done."
echo "  Note: VirtualBox isn't installed yet at this point in the README (step 4 does"
echo "  that) — running the curl check below right now will fail with a VBoxManage"
echo "  'No such file or directory' error. That's expected; re-run it after step 4:"
echo "  curl -s http://127.0.0.1:8088/vm/state -H \"Authorization: Bearer \$(cat $INSTALL_DIR/secrets/vm_agent_token)\""
