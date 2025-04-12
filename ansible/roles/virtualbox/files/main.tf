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
  description = "Nom de la machine virtuelle"
  default     = "sandbox"
}

variable "vm_cpus" {
  type        = number
  description = "Nombre de CPU pour la VM"
  default     = 2
}

variable "vm_memory" {
  type        = string
  description = "Quantité de mémoire RAM pour la VM"
  default     = "4096"
}

variable "vm_network_interface" {
  type        = string
  description = "Interface réseau à utiliser"
}

resource "null_resource" "windows_vm" {
  triggers = {
    vm_name = var.vm_name
  }

  provisioner "local-exec" {
    command = <<-EOT
      LOGFILE="/home/purplelab/terraform.log"
      echo "=== Création VM '${var.vm_name}' ===" >> $LOGFILE

      # Vérifie si la VM est déjà enregistrée
      if VBoxManage list vms | grep -q "\"${var.vm_name}\""; then
        echo "⚠️ VM ${var.vm_name} déjà présente. Aucune action." >> $LOGFILE
        exit 0
      fi

      echo "[+] Import de l'OVF..." >> $LOGFILE
      VBoxManage import "/home/purplelab/.vagrant.d/boxes/StefanScherer-VAGRANTSLASH-windows_2019/2021.05.15/virtualbox/box.ovf" \
        --vsys 0 \
        --vmname "${var.vm_name}" \
        --basefolder "/home/purplelab/VirtualBox VMs" || exit 1

      echo "[+] Fix des permissions..." >> $LOGFILE
      sudo chown -R purplelab:purplelab "/home/purplelab/VirtualBox VMs/${var.vm_name}"

      echo "[+] Enregistrement de la VM dans VirtualBox..." >> $LOGFILE

      UUID_FILE="/home/purplelab/VirtualBox VMs/${var.vm_name}/${var.vm_name}.vbox"
      EXISTING_UUID=$(grep "<Machine" "$UUID_FILE" | sed -n 's/.*uuid="\([^"]*\)".*/\1/p')

      # Si une VM avec le même UUID existe, on la vire
      if VBoxManage list vms | grep -q "$EXISTING_UUID"; then
        VBoxManage unregistervm "$EXISTING_UUID" --delete || true
      fi

      VBoxManage registervm "$UUID_FILE" || exit 1

      echo "[+] Configuration CPU et mémoire..." >> $LOGFILE
      VBoxManage modifyvm "${var.vm_name}" --cpus ${var.vm_cpus} --memory ${var.vm_memory} --acpi on --boot1 disk || exit 1

      echo "[+] Configuration réseau..." >> $LOGFILE
      VBoxManage modifyvm "${var.vm_name}" --nic1 bridged --bridgeadapter1 "${var.vm_network_interface}" || exit 1

      echo "[+] Démarrage de la VM..." >> $LOGFILE
      VBoxManage startvm "${var.vm_name}" --type headless || exit 1

      echo "[+] Attente du boot..." >> $LOGFILE
      sleep 60

      echo "[+] Vérification du statut 'running'..." >> $LOGFILE
      VBoxManage showvminfo "${var.vm_name}" | grep -q "running" || exit 1

      echo "✅ VM ${var.vm_name} prête et persistante" >> $LOGFILE
    EOT
  }
}
