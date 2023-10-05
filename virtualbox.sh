#!/bin/bash

# Installation de VirtualBox
sudo apt update
sudo apt install virtualbox

# Importation de la VM
sudo VBoxManage import Virtual10.ova

# Configuration de la VM
VM_NAME="Virtual10"
VM_MEMORY=4096
VM_CPUS=2
VM_PASSWORD="oem"


# Modification du nombre de processeurs et de la mémoire vive
sudo VBoxManage modifyvm "sandbox" --memory "$VM_MEMORY" --cpus "$VM_CPUS"

VBoxManage modifyvm sandbox --nic1 bridged --bridgeadapter1 ens33

# Démarrage de la VM
sudo VBoxManage startvm "sandbox" --type headless

# Attente que la VM soit démarrée
echo "Waiting for VM to start..."
while ! VBoxManage showvminfo "sandbox" | grep -q "State.*running"; do
  sleep 1s
done


sleep 15s


# Vérifier si la machine sandbox est en marche
if VBoxManage showvminfo sandbox | grep -q "running (since"; then
    # Récupérer l'adresse IP de la machine sandbox
    ip=$(VBoxManage guestproperty get sandbox "/VirtualBox/GuestInfo/Net/0/V4/IP" | awk '{print $2}')
    echo "La machine sandbox est bien démarrée et son adresse IP est accessible via RDP à : $ip"
else
    echo "La machine sandbox n'est pas en marche"
fi
