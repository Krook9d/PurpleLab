# vmbridge-agent

Small FastAPI service that drives VirtualBox (`VBoxManage`) on behalf of the
Dockerized backend. It runs **natively on the host**, not in a container:
nested virtualization (Docker → VirtualBox → Windows VM) isn't reliably
possible, so VirtualBox stays on the host and this service bridges HTTP
between the `backend` container and it (`host.docker.internal:8088`).

## Installation

**Automated** — `install.sh` runs steps 1-7 below (idempotent, safe to
re-run), deriving `SAMPLES_DIR`/`MALWARE_UPLOAD_DIR` from this checkout's
own location instead of requiring manual edits:
```bash
sudo ./vmbridge-agent/install.sh
```
Requires `secrets/vm_agent_token` to already exist at the repo root (see
the main README's Installation section). `VM_GUEST_USERNAME`/
`VM_GUEST_PASSWORD` default to `oem`/`oem` (the Ansible-provisioned
sandbox's account) — override via env vars if the guest was set up
differently: `VM_GUEST_USERNAME=... VM_GUEST_PASSWORD=... sudo -E ./vmbridge-agent/install.sh`.

The manual steps below are what the script does — useful for
understanding a failure or doing one step by hand.

**1. Dedicated system account** (never root):
```bash
sudo useradd --system --no-create-home --groups vboxusers purplelab-vm
```

**2. udev rule** — without it, `/dev/vboxdrv` stays locked to
`root:root 0600` by the `virtualbox-dkms` package (Ubuntu), and the agent
can neither start nor stop the VM (only pure read operations work):
```bash
sudo cp udev/70-vboxdrv-vboxusers.rules /etc/udev/rules.d/
sudo udevadm control --reload-rules
sudo chgrp vboxusers /dev/vboxdrv && sudo chmod 0660 /dev/vboxdrv
```

**3. Scoped sudoers** — only needed for forensic disk acquisition
(`clonemedium disk`, which accesses the VM's disk file directly).
Everything else (poweroff/restore/start/memory dump...) works without sudo
thanks to the udev rule above:
```bash
sudo cp sudoers.d/purplelab-vm.example /etc/sudoers.d/purplelab-vm
sudo chmod 0440 /etc/sudoers.d/purplelab-vm
sudo visudo -cf /etc/sudoers.d/purplelab-vm   # validates syntax
```

**4. Python environment**:
```bash
sudo mkdir -p /opt/purplelab/vmbridge-agent
sudo cp -r app requirements.txt /opt/purplelab/vmbridge-agent/
sudo python3 -m venv /opt/purplelab/vmbridge-agent/.venv
sudo /opt/purplelab/vmbridge-agent/.venv/bin/pip install -r /opt/purplelab/vmbridge-agent/requirements.txt
sudo chown -R purplelab-vm:vboxusers /opt/purplelab/vmbridge-agent
```

**5. Shared token** — same value as `secrets/vm_agent_token` at the repo
root (this is what the backend sends to authenticate with the agent):
```bash
sudo mkdir -p /opt/purplelab/vmbridge-agent/secrets
sudo cp <repo>/secrets/vm_agent_token /opt/purplelab/vmbridge-agent/secrets/vm_agent_token
sudo chown -R purplelab-vm:vboxusers /opt/purplelab/vmbridge-agent/secrets
```

**6. systemd service** — copy the template, then edit the `CHANGE_ME`
values and the `SAMPLES_DIR`/`MALWARE_UPLOAD_DIR` paths so they point to
the same folders as the `backend` service's bind-mounts in
`docker-compose.yml` (at the repo root, not under `/opt`):
```bash
sudo cp systemd/vmbridge-agent.service /etc/systemd/system/
sudo nano /etc/systemd/system/vmbridge-agent.service   # VM_GUEST_USERNAME/PASSWORD, SAMPLES_DIR, MALWARE_UPLOAD_DIR
sudo systemctl daemon-reload
sudo systemctl enable --now vmbridge-agent
sudo systemctl status vmbridge-agent
```

**7. Firewall** — the backend reaches the agent via `host.docker.internal`,
which resolves to the Docker bridge's IP (not `127.0.0.1`), so the agent
listens on `0.0.0.0:8088`. Restrict access to the Docker bridge subnet
rather than opening the port widely:
```bash
sudo ufw allow from 172.16.0.0/12 to any port 8088 proto tcp
```

## Verification

```bash
curl -s http://127.0.0.1:8088/vm/state -H "Authorization: Bearer $(cat /opt/purplelab/vmbridge-agent/secrets/vm_agent_token)"
```

Should return the VM's state (`{"name":"sandbox","state":"poweroff",...}`
or similar). From the backend container, `/health` should show
`"vm_agent": "ok"`.

## Redeploying after a code change

The agent isn't containerized: a change in `app/` must be copied manually
and the service restarted:
```bash
sudo cp app/*.py /opt/purplelab/vmbridge-agent/app/
sudo chown purplelab-vm:vboxusers /opt/purplelab/vmbridge-agent/app/*.py
sudo systemctl restart vmbridge-agent
```

## After a host reboot

- `docker compose up -d` at the repo root brings the containers back if
  they didn't restart on their own (they carry `restart: unless-stopped`,
  but only from the compose version that added it).
- The sandbox VM does **not** come back on its own — VirtualBox doesn't
  remember to power a VM back on after the host reboots. Once the agent
  answers (see Verification above), restore/start it from the app's
  Health page, or directly: `POST /vm/restore` (poweroff-if-needed +
  restore last snapshot + headless start — the safer choice after an
  unclean shutdown, since the VM was hard-killed along with the host, not
  gracefully powered off) or `POST /vm/start` if you just want it powered
  on as-is.

## Troubleshooting: "Failed to create the VirtualBox object! ... Access denied"

Symptom: `/vm/state` (and everything else) returns 502, and the agent's
own logs show `VBoxManage: error: ... opening '/var/lib/virtualbox/VirtualBox.xml'
for reading: -38 (Access denied.)`.

Cause: `VBoxManage` was run as a **different** user than `purplelab-vm` —
directly as root via `sudo VBoxManage ...`, or interactively as whichever
user has a VirtualBox GUI/session on this machine. VirtualBox rewrites its
profile (`VirtualBox.xml`, and the `VBoxSVC.log`/lock files next to it)
with that user's ownership every time, locking `purplelab-vm` back out
until it's handed back:
```bash
sudo chown purplelab-vm:vboxusers /var/lib/virtualbox/VirtualBox.xml
sudo systemctl restart vmbridge-agent   # only if it's still failing after the chown
```
This is a recurring operational hazard on this design (a single shared
VirtualBox profile, multiple users touching it), not a one-time install
step — it can resurface any time something other than the agent itself
runs `VBoxManage` on this host.
