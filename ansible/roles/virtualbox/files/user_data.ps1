# Configuration initiale de Windows Server 2019
$ErrorActionPreference = "Stop"

# Installer Chocolatey
Set-ExecutionPolicy Bypass -Scope Process -Force
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072
Invoke-Expression ((New-Object System.Net.WebClient).DownloadString('https://chocolatey.org/install.ps1'))

# Installer Python
choco install -y python

# Créer un utilisateur administrateur
$password = ConvertTo-SecureString "oem" -AsPlainText -Force
New-LocalUser -Name "oem" -Password $password -FullName "OEM Admin" -Description "OEM Administrator"
Add-LocalGroupMember -Group "Administrators" -Member "oem"


