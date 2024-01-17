import subprocess
import sys
import time

def poweroff_vm():
    # Commande pour éteindre la machine virtuelle
    command = 'VBoxManage controlvm "sandbox" poweroff'
    subprocess.run(command, shell=True)

def restore_snapshot():
    # Commande pour restaurer le Snapshot1
    command = 'VBoxManage snapshot "sandbox" restore "Snapshot1"'
    subprocess.run(command, shell=True)

def start_vm_headless():
    # Commande pour démarrer la machine virtuelle
    command = 'VBoxManage startvm "sandbox" --type headless'
    subprocess.run(command, shell=True)

def show_vm_info():
    # Commande pour afficher les informations de la machine virtuelle
    command = 'VBoxManage showvminfo "sandbox" | grep -E "Snapshots:|Name:|State:"'
    subprocess.run(command, shell=True)

def get_vm_ip():
    # Command to get the IP address of the virtual machine
    command = 'echo "IP = $(VBoxManage guestproperty enumerate "sandbox" | grep -oP \'/VirtualBox/GuestInfo/Net/0/V4/IP, value: \K[^,]+\')"'
    subprocess.run(command, shell=True)


def upload_to_vm():
    # Commande pour copier des fichiers vers la machine virtuelle
    command = (
        'sudo VBoxManage guestcontrol "sandbox" copyto '
        '--username oem --password oem --target-directory '
        '"C:\\Users\\oem\\Documents" --recursive '
        '"/var/www/html/Downloaded/malware_upload"'
    )
    subprocess.run(command, shell=True)

# Vérifier le nombre d'arguments
if len(sys.argv) != 2:
    print("Utilisation: python manageVM.py <commande>")
    sys.exit(1)

# Récupérer l'argument de la ligne de commande
command_name = sys.argv[1]

if command_name == "restore":
    # Éteindre la machine virtuelle
    poweroff_vm()
    # Restaurer le snapshot après l'avoir éteinte
    restore_snapshot()
    # Attendre 1 seconde
    time.sleep(1)
    # Démarrer la machine virtuelle en mode headless
    start_vm_headless()
elif command_name == "state":
    # Afficher les informations de la machine virtuelle
    show_vm_info()
elif command_name == "upload":
    # Copier des fichiers vers la machine virtuelle
    upload_to_vm()
elif command_name == "ip":
    get_vm_ip()
else:
    print("Commande non reconnue. Utilisation: python manageVM.py <commande>")
