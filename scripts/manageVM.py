import subprocess
import sys
import time

def poweroff_vm():
    # Command to shut down the virtual machine
    command = 'VBoxManage controlvm "sandbox" poweroff'
    subprocess.run(command, shell=True)

def restore_snapshot():
    # Command to restore Snapshot1
    command = 'VBoxManage snapshot "sandbox" restore "Snapshot1"'
    subprocess.run(command, shell=True)

def start_vm_headless():
    # Command to start the virtual machine
    command = 'VBoxManage startvm "sandbox" --type headless'
    subprocess.run(command, shell=True)

def show_vm_info():
    # Command to display virtual machine information
    command = 'VBoxManage showvminfo "sandbox" | grep -E "Snapshots:|Name:|State:"'
    subprocess.run(command, shell=True)

def get_vm_ip():
    # Command to get the IP address of the virtual machine
    command = 'echo "IP = $(VBoxManage guestproperty enumerate "sandbox" | grep -oP \'/VirtualBox/GuestInfo/Net/0/V4/IP, value: \K[^,]+\')"'
    subprocess.run(command, shell=True)

def upload_to_vm():
    # Command to copy files to the virtual machine
    command = (
        'sudo VBoxManage guestcontrol "sandbox" copyto '
        '--username oem --password oem --target-directory '
        '"C:\\Users\\oem\\Documents\\malware_upload" --recursive '
        '"/var/www/html/Downloaded/malware_upload"'
    )
    subprocess.run(command, shell=True)

def api_upload_to_vm():
    # Command to copy files to the virtual machine for API uploads
    command = (
        'sudo VBoxManage guestcontrol "sandbox" copyto '
        '--username oem --password oem --target-directory '
        '"C:\\Users\\oem\\Documents\\upload" --recursive '
        '"/var/www/html/Downloaded/upload/"'  
    )
    subprocess.run(command, shell=True)


def disable_antivirus():
# Command to disable Windows Defender real-time monitoring
    powershell_command = (
    'VBoxManage guestcontrol "sandbox" run --exe '
    '"C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe" --username oem --password oem '
    '-- -Command "& {Start-Process powershell -ArgumentList \'Set-MpPreference -DisableRealtimeMonitoring \$true\' -Verb RunAs}"'
)

    # Run the command to disable Windows Defender real-time monitoring
    subprocess.run(powershell_command, shell=True, check=True)
    print("PowerShell command successfully executed on the VM.")

def enable_antivirus():
# Command to disable Windows Defender real-time monitoring
    powershell_command = (
    'VBoxManage guestcontrol "sandbox" run --exe '
    '"C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe" --username oem --password oem '
    '-- -Command "& {Start-Process powershell -ArgumentList \'Set-MpPreference -DisableRealtimeMonitoring \$false\' -Verb RunAs}"'
)

    # Run the command to disable Windows Defender real-time monitoring
    subprocess.run(powershell_command, shell=True, check=True)
    print("PowerShell command successfully executed on the VM.")

def restart_winlogbeat():
    # Command to restart the winlogbeat service
    command = (
        'VBoxManage guestcontrol "sandbox" run --exe '
        '"C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe" --username oem --password oem '
        '-- -Command "& {Start-Process powershell -ArgumentList \'Restart-Service winlogbeat\' -Verb RunAs}"'
    )
    subprocess.run(command, shell=True)

if len(sys.argv) != 2:
    print("Utilisation: python manageVM.py <commande>")
    sys.exit(1)

command_name = sys.argv[1]

if command_name == "restore":
    poweroff_vm()
    restore_snapshot()
    time.sleep(1)
    start_vm_headless()
elif command_name == "state":
    show_vm_info()
elif command_name == "upload":
    upload_to_vm()
elif command_name == "apiupload":  
    api_upload_to_vm()
elif command_name == "ip":
    get_vm_ip()
elif command_name == "poweroff":
    poweroff_vm()
elif command_name == "startheadless":
    start_vm_headless()
elif command_name == "disableav":
    disable_antivirus()
elif command_name == "enableav":
    enable_antivirus()
elif command_name == "restartwinlogbeat":
    restart_winlogbeat()
else:
    print("Command not recognized. Utilisation: python manageVM.py <commande>")
