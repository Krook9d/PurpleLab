# Configuration initiale de Windows Server 2019
$ErrorActionPreference = "Stop"

# Activer le bureau à distance
Set-ItemProperty -Path "HKLM:\System\CurrentControlSet\Control\Terminal Server" -Name "fDenyTSConnections" -Value 0
Enable-NetFirewallRule -DisplayGroup "Remote Desktop"

# Configurer le réseau
$adapter = Get-NetAdapter | Where-Object {$_.Name -like "*Ethernet*"}
if ($adapter) {
    Set-NetIPInterface -InterfaceIndex $adapter.ifIndex -Dhcp Enabled
}

# Créer un utilisateur administrateur
$password = ConvertTo-SecureString "PurpleLab123!" -AsPlainText -Force
New-LocalUser -Name "purplelab" -Password $password -FullName "PurpleLab Admin" -Description "PurpleLab Administrator"
Add-LocalGroupMember -Group "Administrators" -Member "purplelab"

# Installer Chocolatey
Set-ExecutionPolicy Bypass -Scope Process -Force
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072
Invoke-Expression ((New-Object System.Net.WebClient).DownloadString('https://chocolatey.org/install.ps1'))

# Installer les outils nécessaires

choco install -y python


# Configurer le pare-feu Windows
New-NetFirewallRule -DisplayName "Allow PurpleLab Ports" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 80,443,3306,8080

# Redémarrer la machine
Restart-Computer -Force 