#!/bin/bash

# Colors for messages
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
NC='\033[0m' # No color

echo -e "${GREEN}*********************************************${NC}"
echo -e "${GREEN}*                                           *${NC}"
echo -e "${GREEN}*        PURPLELAB by Krook9d               *${NC}"
echo -e "${GREEN}*    Automatic installation with Ansible    *${NC}"
echo -e "${GREEN}*                                           *${NC}"
echo -e "${GREEN}*********************************************${NC}"

# Check for root privileges
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}This script must be run as root or with sudo.${NC}"
  exit 1
fi

# Preliminary checks
# Check Internet connection
echo -e "${YELLOW}Checking Internet connection...${NC}"
if ! ping -c 1 google.com &> /dev/null; then
    echo -e "${RED}✖ No Internet connection. Please ensure you have an active Internet connection.${NC}"
    exit 1
else
    echo -e "${GREEN}✔ Internet connection: OK${NC}"
fi

# Check RAM
echo -e "${YELLOW}Checking RAM...${NC}"
total_ram=$(awk '/MemTotal/ {print $2}' /proc/meminfo)
total_ram_gb=$(echo "$total_ram/1024/1024" | bc)
if (( $(echo "$total_ram_gb < 7.9" | bc -l) )); then
    echo -e "${RED}✖ Insufficient RAM. A minimum of 7.9 GB of RAM is required.${NC}"
    exit 1
else
    echo -e "${GREEN}✔ RAM: OK${NC}"
fi

# Check virtualization
echo -e "${YELLOW}Checking hardware virtualization...${NC}"
if ! grep -E --color 'vmx|svm' /proc/cpuinfo &> /dev/null; then
    echo -e "${RED}✖ Hardware virtualization is disabled. Please enable it in the BIOS settings.${NC}"
    exit 1
else
    echo -e "${GREEN}✔ Hardware virtualization: OK${NC}"
fi

# Disable needrestart prompts
echo -e "${YELLOW}Disabling needrestart prompts...${NC}"
if [ -f /etc/needrestart/needrestart.conf ]; then
    sed -i "s/#\$nrconf{restart} = 'i';/\$nrconf{restart} = 'a';/" /etc/needrestart/needrestart.conf
fi
apt-get remove -y needrestart

# Install prerequisites
echo -e "${YELLOW}Updating packages...${NC}"
apt-get update

echo -e "${YELLOW}Installing necessary dependencies...${NC}"
apt-get install -y software-properties-common git python3 python3-pip curl unzip

echo -e "${YELLOW}Installing Ansible...${NC}"
apt-add-repository --yes --update ppa:ansible/ansible
apt-get install -y ansible

# Install PyMySQL for the MySQL module
echo -e "${YELLOW}Installing PyMySQL...${NC}"
pip install PyMySQL

# Prepare the web directory
echo -e "${YELLOW}Preparing the web directory...${NC}"
mkdir -p /var/www/html
if [ -d "PurpleLab/Web" ]; then
    cp -r PurpleLab/Web/* /var/www/html/
fi

# Execute the playbook
echo -e "${YELLOW}Executing the playbook...${NC}"
cd PurpleLab/ansible
ansible-galaxy collection install -r requirements.yml

# Exécuter la partie principale du playbook (jusqu'à opensearch)
echo -e "${YELLOW}Installing main components...${NC}"
ansible-playbook -i inventory/local/hosts playbook.yml --tags "common,webserver,database"

# Exécuter le playbook OpenSearch séparément
echo -e "${YELLOW}Installing OpenSearch...${NC}"
ansible-playbook -i inventory/opensearch/hosts/hosts inventory/opensearch/opensearch_only.yml 

# Exécuter la partie VirtualBox
echo -e "${YELLOW}Installing VirtualBox and configuring VMs...${NC}"
ansible-playbook -i inventory/local/hosts playbook.yml --tags "virtualbox" 

# Set permissions
echo -e "${YELLOW}Setting permissions...${NC}"
chown -R www-data:www-data /var/www/html
chmod -R 775 /var/www/html

# Save login credentials to admin.txt only if it doesn't exist or doesn't have password
if [ ! -f "/home/$SUDO_USER/admin.txt" ] || grep -q "^Password: $" "/home/$SUDO_USER/admin.txt"; then
  echo -e "${YELLOW}Creating admin.txt file...${NC}"
  # Générer un mot de passe pour l'administrateur
  ADMIN_PASSWORD=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 12)
  
  cat > /home/$SUDO_USER/admin.txt << EOL
PurpleLab Admin Credentials
==========================

Web Application:
Username: admin@local.com
Password: ${ADMIN_PASSWORD}

OpenSearch & Logstash:
Username: admin
Password: admin123

Sandbox VM:
Username: oem
Password: oem
RDP Access: Use the IP address shown below
EOL
else
  echo -e "${YELLOW}Admin.txt file already exists with credentials.${NC}"
fi

# Get Sandbox VM IP
SANDBOX_IP=$(VBoxManage guestproperty get sandbox "/VirtualBox/GuestInfo/Net/0/V4/IP" 2>/dev/null | awk '{print $2}')


# Display final message
echo -e "${GREEN}*********************************************${NC}"
echo -e "${GREEN}*                                           *${NC}"
echo -e "${GREEN}*    PurpleLab installation completed       *${NC}"
echo -e "${GREEN}*                                           *${NC}"
echo -e "${GREEN}*********************************************${NC}"
echo -e "\nCheck the file /home/$SUDO_USER/admin.txt for all credentials."
echo -e "Access PurpleLab via: http://$(hostname -I | awk '{print $1}')"

if [ -n "$SANDBOX_IP" ]; then
    echo -e "Access Sandbox VM via RDP: $SANDBOX_IP"
    echo -e "Sandbox VM IP: $SANDBOX_IP" >> /home/$SUDO_USER/admin.txt
else
    echo -e "Sandbox VM IP not yet available. To get the IP, run:"
    echo -e "  sudo VBoxManage guestproperty get sandbox \"/VirtualBox/GuestInfo/Net/0/V4/IP\""
    echo -e "Sandbox VM access instructions saved to /home/$SUDO_USER/admin.txt"
fi 
