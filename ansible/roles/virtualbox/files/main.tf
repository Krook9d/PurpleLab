terraform {
  required_version = ">= 0.12"
  required_providers {
    null = {
      source = "hashicorp/null"
      version = "~> 3.0"
    }
  }
}

variable "vm_name" {
  type        = string
  description = "Nom de la machine virtuelle"
}

variable "vm_cpus" {
  type        = number
  description = "Nombre de CPU pour la VM"
}

variable "vm_memory" {
  type        = string
  description = "Quantité de mémoire RAM pour la VM"
}

variable "vm_disk_size" {
  type        = number
  description = "Taille du disque en MB"
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
      # Supprimer la VM si elle existe déjà
      VBoxManage list vms | grep -q "${var.vm_name}" && VBoxManage unregistervm "${var.vm_name}" --delete || true
      
      # Créer la VM
      echo "Création de la VM ${var.vm_name}..." >> terraform.log
      VBoxManage import "/home/purplelab/.vagrant.d/boxes/StefanScherer-VAGRANTSLASH-windows_2019/2021.05.15/virtualbox/box.ovf" --vsys 0 --vmname "${var.vm_name}" || exit 1

      # Configurer la VM
      echo "Configuration des ressources de la VM..." >> terraform.log
      VBoxManage modifyvm "${var.vm_name}" --cpus ${var.vm_cpus} --memory ${var.vm_memory} --acpi on --boot1 disk || exit 1
      
      # Configurer le réseau
      echo "Configuration du réseau..." >> terraform.log
      VBoxManage modifyvm "${var.vm_name}" --nic1 bridged --bridgeadapter1 "${var.vm_network_interface}" || exit 1
      
      # Démarrer la VM
      echo "Démarrage de la VM..." >> terraform.log
      VBoxManage startvm "${var.vm_name}" --type headless || exit 1
      
      # Attendre que la VM soit prête
      echo "Attente que la VM soit prête..." >> terraform.log
      sleep 120
      
      # Vérifier que la VM est en cours d'exécution
      echo "Vérification de l'état de la VM..." >> terraform.log
      VBoxManage showvminfo "${var.vm_name}" | grep -q "running" || exit 1
      
      # Attendre que les Guest Additions soient prêts
      echo "Attente des Guest Additions..." >> terraform.log
      for i in {1..30}; do
        if VBoxManage guestproperty get "${var.vm_name}" "/VirtualBox/GuestAdd/Version" 2>/dev/null | grep -q "Value:"; then
          break
        fi
        sleep 10
        if [ $i -eq 30 ]; then
          echo "Les Guest Additions ne sont pas prêts après 5 minutes" >> terraform.log
          exit 1
        fi
      done
      
      # Créer le dossier temporaire
      echo "Création du dossier temporaire..." >> terraform.log
      VBoxManage guestcontrol "${var.vm_name}" execute --username vagrant --password vagrant --image "C:\\Windows\\System32\\cmd.exe" --arguments "/c mkdir C:\\Windows\\Temp" || exit 1
      
      # Copier et exécuter le script de configuration
      echo "Application de la configuration..." >> terraform.log
      VBoxManage guestcontrol "${var.vm_name}" copyto --username vagrant --password vagrant "C:\\Windows\\Temp\\user_data.ps1" "/home/purplelab/PurpleLab/ansible/roles/virtualbox/files/user_data.ps1" || exit 1
      VBoxManage guestcontrol "${var.vm_name}" execute --username vagrant --password vagrant --image "C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe" --arguments "-ExecutionPolicy Bypass -File C:\\Windows\\Temp\\user_data.ps1" || exit 1
      
      echo "VM ${var.vm_name} créée et configurée avec succès" >> terraform.log
    EOT
  }

  provisioner "local-exec" {
    when = destroy
    command = "VBoxManage list vms | grep -o '\"[^\"]*\"' | tr -d '\"' | while read vm; do VBoxManage controlvm \"$vm\" poweroff || true; sleep 5; VBoxManage unregistervm \"$vm\" --delete || true; done"
  }
} 
