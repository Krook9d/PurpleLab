terraform {
  required_version = ">= 0.12"
  required_providers {
    null = {
      source  = "hashicorp/null"
      version = "~> 3.0"
    }
  }
}

variable "vm_name" {
  type        = string
  description = "Virtual machine name"
  default     = "sandbox"
}

variable "vm_cpus" {
  type        = number
  description = "Number of CPUs for the VM"
  default     = 2
}

variable "vm_memory" {
  type        = string
  description = "Amount of RAM for the VM"
  default     = "4096"
}

variable "vm_disk_size" {
  type        = number
  description = "Disk size in MB"
  default     = 40960
}

variable "vm_network_interface" {
  type        = string
  description = "Network interface to use"
}

variable "user" {
  type        = string
  description = "System user"
  default     = "user"
}

resource "null_resource" "windows_vm" {
  # A "local-exec" provisioner only runs when the resource is created,
  # never on a later apply without a change in triggers — with only
  # vm_name/user (which never change between runs), this script therefore
  # ran only once over the whole lifetime of terraform.tfstate. As a
  # result, the "VM already exists" check of the script below was in fact
  # never re-evaluated after the very first apply — regardless of whether
  # it handles that case well or not.
  # `always_run` forces this script to run on every apply; the script
  # itself (not Terraform) remains responsible for doing nothing when the
  # VM is already running.
  triggers = {
    vm_name    = var.vm_name
    user       = var.user
    always_run = timestamp()
  }

  provisioner "local-exec" {
    command = <<-EOT
      # Check whether the VM already exists. Checking that it exists is
      # not enough: an interrupted previous run (e.g. the Ansible task
      # "Get the Windows VM IP" giving up after 300s) leaves the VM
      # registered but possibly powered off/crashed — without this check,
      # every later run is a silent no-op and Ansible waits for an IP from
      # a VM that never boots.
      if VBoxManage list vms | grep -q "\"${var.vm_name}\""; then
        if VBoxManage list runningvms | grep -q "\"${var.vm_name}\""; then
          echo "VM ${var.vm_name} already exists and is running. Nothing to do."
          exit 0
        fi
        echo "VM ${var.vm_name} exists but is stopped — restarting..." >> terraform.log
        VBoxManage startvm "${var.vm_name}" --type headless || exit 1
        echo "Waiting for the VM to be ready..." >> terraform.log
        sleep 60
        exit 0
      fi

      echo "Creating VM ${var.vm_name}..." >> terraform.log
      VBoxManage import "/home/${var.user}/.vagrant.d/boxes/StefanScherer-VAGRANTSLASH-windows_2019/2021.05.15/virtualbox/box.ovf" \
        --vsys 0 \
        --vmname "${var.vm_name}" \
        --basefolder "/home/${var.user}/VirtualBox VMs" || exit 1

      echo "Configuring the VM resources..." >> terraform.log
      VBoxManage modifyvm "${var.vm_name}" --cpus ${var.vm_cpus} --memory ${var.vm_memory} --acpi on --boot1 disk || exit 1

      echo "Configuring the network..." >> terraform.log
      VBoxManage modifyvm "${var.vm_name}" --nic1 bridged --bridgeadapter1 "${var.vm_network_interface}" || exit 1

      echo "Starting the VM..." >> terraform.log
      VBoxManage startvm "${var.vm_name}" --type headless || exit 1

      echo "Waiting for the VM to be ready..." >> terraform.log
      sleep 60

      echo "Checking the VM state..." >> terraform.log
      VBoxManage showvminfo "${var.vm_name}" | grep -q "running" || exit 1

      echo "VM ${var.vm_name} created and started successfully" >> terraform.log
    EOT
  }
}
