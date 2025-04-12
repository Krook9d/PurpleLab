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
  default     = "sandbox"
}

variable "vm_cpus" {
  type        = number
  default     = 2
}

variable "vm_memory" {
  type        = string
  default     = "4096"
}

variable "vm_network_interface" {
  type        = string
  default     = "ens33"
}

resource "null_resource" "windows_vm" {
  triggers = {
    vm_name = var.vm_name
  }

  provisioner "local-exec" {
    command = <<-EOT
      LOGFILE="/home/purplelab/terraform.log"
      echo "=== Création VM '${var.vm_name}' ===" >> $LOGFILE

      if VBoxManage list vms | grep -q "\"${var.vm_name}\""; then
        echo "⚠️ VM ${var.vm_name} déjà présente. Aucune action." >> $LOGFILE
        exit 0
      fi

      echo "[+] Import OVF..." >> $LOGFILE
      VBoxManage import "/home/purplelab/.vagrant.d/boxes/StefanScherer-VAGRANTSLASH-windows_2019/2021.05.15/virtualbox/box.ovf" \
        --vsys 0 \
        --vmname "${var.vm_name}" \
        --basefolder "/home/purplelab/VirtualBox VMs" || exit 1

      echo "[+] Recherche du .vbox généré..." >> $LOGFILE
      GENERATED_VBOX=$(find "/home/purplelab/VirtualBox VMs" -name "*.vbox" | grep -i "${var.vm_name}" | head -n1)

      if [ ! -f "$GENERATED_VBOX" ]; then
        echo "❌ Fichier .vbox introuvable !" >> $LOGFILE
        exit 1
      fi

      echo "[+] Changement des permissions..." >> $LOGFILE
      sudo chown -R purplelab:purplelab "$(dirname "$GENERATED_VBOX")"

      echo "[+] Enregistrement explicite de la VM..." >> $LOGFILE
      VBoxManage registervm "$GENERATED_VBOX" || exit 1

      echo "[+] Configuration CPU/RAM..." >> $LOGFILE
      VBoxManage modifyvm "${var.vm_name}" --cpus ${var.vm_cpus} --memory ${var.vm_memory} --acpi on --boot1 disk || exit 1

      echo "[+] Configuration réseau..." >> $LOGFILE
      VBoxManage modifyvm "${var.vm_name}" --nic1 bridged --bridgeadapter1 "${var.vm_network_interface}" || exit 1

      echo "[+] Démarrage de la VM..." >> $LOGFILE
      VBoxManage startvm "${var.vm_name}" --type headless || exit 1

      echo "[+] Pause boot..." >> $LOGFILE
      sleep 60

      echo "[+] Vérification VM running..." >> $LOGFILE
      VBoxManage showvminfo "${var.vm_name}" | grep -q "running" || exit 1

      echo "✅ VM ${var.vm_name} prête et enregistrée" >> $LOGFILE
    EOT
  }
}
