#!/bin/bash

# Function to check internet connectivity
check_internet() {
    echo -e "\nChecking Internet connection..."
    if ! ping -c 1 google.com &> /dev/null; then
        echo -e "\e[31m✖ No Internet connection. Please ensure you have an active Internet connection.\e[0m"
        exit 1
    else
        echo -e "\e[32m✔ Internet connection: OK\e[0m"
    fi
}

# Function to check minimum required RAM
check_ram() {
    echo -e "\nChecking RAM size..."
    total_ram=$(awk '/MemTotal/ {print $2}' /proc/meminfo)
    # Convert from kB to GB
    total_ram_gb=$(echo "$total_ram/1024/1024" | bc)
    if (( $(echo "$total_ram_gb < 7.9" | bc -l) )); then
        echo -e "\e[31m✖ Insufficient RAM. A minimum of 7.9 GB RAM is required.\e[0m"
        exit 1
    else
        echo -e "\e[32m✔ RAM size: OK\e[0m"
    fi
}

# Function to check if hardware virtualization is enabled
check_virtualization() {
    echo -e "\nChecking Hardware Virtualization..."
    if ! grep -E --color 'vmx|svm' /proc/cpuinfo &> /dev/null; then
        echo -e "\e[31m✖ Hardware Virtualization is disabled. Please enable it in your BIOS settings.\e[0m"
        exit 1
    else
        echo -e "\e[32m✔ Hardware Virtualization: OK\e[0m"
    fi
}

# Call the checking functions
check_internet
check_ram
check_virtualization

# If all checks pass, continue with the installation...
echo -e "\n\e[32mAll checks passed. Continuing with the installation...\e[0m\n"

    apt remove -y needrestart
    apt-get update
    apt-get install -y dialog
    
    # Ask the user if they want to install the preconfigured Elasticsearch SIEM
    dialog --title "Elasticsearch SIEM Installation" --yesno "Do you want to automatically install the preconfigured Elasticsearch SIEM? If not, you can manually install your own SIEM later." 10 60
    response=$?
    
    apt-get install -y apache2
    apt install -y php libapache2-mod-php
    apt-get install -y php-curl
    apt-get install -y php-mysqli
    apt install -y python3-pip
    apt-get -y install p7zip-full
    apt-get -y install apt-transport-https
    pip install pandas
    pip install flask
    pip install flask-cors
    pip install loguru
    pip install stix2
    pip install mitreattack-python
    pip install Flask-JWT-Extended
    apt-get install -y libapache2-mod-ldap-userdir
    a2enmod ldap
    a2enmod authnz_ldap
    
    phpenmod mysqli
    apt-get -y install php-xml php-gd php-mbstring php-zip
    systemctl restart apache2
    apt install -y mysql-server
    systemctl start mysql
    systemctl enable mysql

    # Configure firewall to allow HTTP and HTTPS connections
    ufw allow in "Apache Full"

    # Configure Apache to use the machine's IP address
    IP=$(hostname -I | awk '{print $1}')
    echo "ServerName $IP" >> /etc/apache2/apache2.conf


    # Restart Apache server
    systemctl restart apache2

    # Installing Java 11
    sudo apt-get install -y openjdk-11-jdk

    # Installing curl
    sudo apt-get install -y curl

    # Add Elasticsearch GPG key
    wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo apt-key add -

    # Add Elasticsearch repository
    sudo echo "deb https://artifacts.elastic.co/packages/8.x/apt stable main" | sudo tee /etc/apt/sources.list.d/elastic-8.x.list

    # Update available packages to include the Elasticsearch repository
    sudo apt-get update

    if [ $response -eq 0 ]; then
    ############ELK PART###############


    # Installing Elasticsearch, Kibana and Logstash
    sudo apt-get install -y elasticsearch kibana logstash | tee temp.txt
    grep 'The generated password for the elastic built-in' temp.txt >> admin.txt
    rm temp.txt


    sudo apt-get install -y filebeat
    sudo systemctl enable filebeat

    # Configure Kibana to listen on the machine's IP address
    sudo sed -i 's/#server.host: "localhost"/server.host: "0.0.0.0"/g' /etc/kibana/kibana.yml

    # Configure Logstash to listen on the machine's IP address
    sudo sed -i 's/#http.host: "127.0.0.1"/http.host: "0.0.0.0"/g' /etc/logstash/logstash.yml

    # add xpacksecurity lines to the elasticsearch.yml file

    sed -i '$a xpack.security.authc.api_key.enabled: true' /etc/elasticsearch/elasticsearch.yml


    # Start Elasticsearch, Kibana and Logstash services
    sudo systemctl enable elasticsearch.service
    sudo systemctl enable kibana.service
    sudo systemctl enable logstash.service
    sudo systemctl start elasticsearch.service
    sudo systemctl start kibana.service
    sudo systemctl start logstash.service


    # Execute command to create enrolment token for Kibana and add output to admin.txt
    /usr/share/elasticsearch/bin/elasticsearch-create-enrollment-token -s kibana >> admin.txt

    sleep 2

    # Extract the password and store it in a variable
    ELASTIC_PASSWORD=$(grep "The generated password for the elastic built-in superuser is :" admin.txt | sed 's/.*: \([^ ]*\).*/\1/')

    # Delete Windows carriage returns (CR) if present
    ELASTIC_PASSWORD=$(echo "$ELASTIC_PASSWORD" | tr -d '\r')

    # Add environment variable to envvars
    echo "export ELASTIC_PASSWORD=\"$ELASTIC_PASSWORD\"" | sudo tee -a /etc/apache2/envvars

    sleep 1
    # Elasticsearch configuration file path
    elasticsearch_config_path="/etc/elasticsearch/jvm.options.d/custom.options"

    # Configuration content to be inserted into the Elasticsearch configuration file
    elasticsearch_config_content="# ELK Stack JVM Heap Size - see /etc/elasticsearch/jvm.options\n-Xms4g\n-Xmx4g"

    # Create the Elasticsearch configuration file and write the content
    printf "%b" "$elasticsearch_config_content" | sudo tee "$elasticsearch_config_path" > /dev/null

    # Restart the Elasticsearch service
    sudo systemctl restart elasticsearch

    # Path to filebeat.yml file
    FILEBEAT_CONFIG="/etc/filebeat/filebeat.yml"

    # Path to admin.txt file (in the same directory as the script)
    ADMIN_FILE="/home/$(logname)/admin.txt"

    # Extract password from admin.txt file

    PASSWORD=$(grep "The generated password for the elastic built-in superuser is :" $ADMIN_FILE | awk -F': ' '{print $2}' | tr -d '\r')

    # Check if the password has been found
    if [ -z "$PASSWORD" ]; then
        echo "The password could not be found in $ADMIN_FILE."
        exit 1
    fi

    # Part 1: Modifying the 'Elasticsearch Output' section
    # Remove comments from specified lines
    sed -i '/#output.logstash:/ s/#//' $FILEBEAT_CONFIG
    sed -i '/#hosts: \["localhost:9200"\]/ s/#//' $FILEBEAT_CONFIG
    sed -i '/#protocol: "https"/ s/#//' $FILEBEAT_CONFIG
    sed -i '/#username:/ s/#//' $FILEBEAT_CONFIG

    # Replace the password line with the extracted password
    sed -i "s/#password: .*/password: \"$PASSWORD\"/" $FILEBEAT_CONFIG

    # Add 'ssl.verification_mode: "none"' below 'hosts: ["localhost:9200"]'.
    sed -i '/hosts: \["localhost:9200"\]/a \ \ ssl.verification_mode: "none"' $FILEBEAT_CONFIG

    # Comment on Logstash configuration
    sed -i '/output.logstash:/,/^[^#]/ s/^/#/' $FILEBEAT_CONFIG

    # Build the configuration to be added for the 'log' type
    LOG_CONFIG="- type: log\n  enabled: true\n  paths:\n    - /var/www/html/Downloaded/Log_simulation/*.json\n  json.keys_under_root: true\n  json.add_error_key: true\n  json.message_key: log\n  fields_under_root: true\n  fields:\n    timestamp: date\n  date_formats:\n    - 'MMM dd HH:mm:ss'"

    # Add configuration after specified line
    awk -v log_config="$LOG_CONFIG" '/#- c:\\programdata\\elasticsearch\\logs\\*/{print; print log_config; next}1' $FILEBEAT_CONFIG > temp.yml && mv temp.yml $FILEBEAT_CONFIG

    # Restart the Filebeat service to apply the changes
    systemctl restart filebeat

    # Display a message indicating the end of the script and the restart of Filebeat
    echo "Filebeat has been restarted to apply the configuration changes."
    fi
    ############ELK PART END###############


    # Download the sandbox.ova file
    
    curl -L -o sandbox.ova -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3" "https://dl-15ur2kes.swisstransfer.com/api/download/88c4eede-91db-4016-b8a6-57f7282dbab7/c367102d-17c1-4e5c-8161-3233f98c83b0"
    #curl -L -o sandbox.ova -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3" "https://dl-0tnr1rp1.swisstransfer.com/api/download/aeb212c2-e1cb-4660-b863-c81e9e39449a/b70b626f-854f-4836-875d-d787db43cc54"
    
    mv sandbox.ova /var/www/html/

    mv PurpleLab/* /var/www/html/
    mv /var/www/html/Web/* /var/www/html/
    rm -r /var/www/html/Web
    
    rm -R PurpleLab

    sleep 1

    echo "<VirtualHost *:80>

        DirectoryIndex index.php

    </VirtualHost>" | sudo tee /etc/apache2/sites-available/000-default.conf

    sudo systemctl restart apache2

    # Move app.py from the extracted archive to the home directory of the active user
    mv /var/www/html/app.py /home/$(logname)/app.py

    echo 'W10 "sandbox" VM credentials: user = oem password = oem' >> admin.txt

    sudo apt install -y virtualbox

    # VM import
    sudo VBoxManage import /var/www/html/sandbox.ova

    # VM configuration
    VM_NAME="Virtual10"
    VM_MEMORY=4096
    VM_CPUS=2
    VM_PASSWORD="oem"


    # Changing the number of processors and RAM
    sudo VBoxManage modifyvm "sandbox" --memory "$VM_MEMORY" --cpus "$VM_CPUS"

# Collect and display available network interfaces with their IP addresses
echo -e "Available network interfaces and their IP addresses:"

# Get a list of interfaces with their IPv4 addresses
interfaces=$(ip -o -4 addr show | awk '{print $2, $4}')

# Check if interfaces were found
if [ -z "$interfaces" ]; then
    echo "No network interfaces found. Exiting."
    exit 1
fi

# Display interfaces in light cyan without coloring the IP addresses
echo "$interfaces" | awk '{print "\033[1;96m" $1 "\033[0m " $2}'

# Ask for user input with the ability to retry if the input is invalid
while true; do
    echo "Please type the name of the network interface you want to use for the VM (as listed above):"
    read INTERFACE_CHOSEN

    # Check if the chosen interface is valid
    if echo "$interfaces" | grep -q "^$INTERFACE_CHOSEN "; then
        break # Valid interface selected
    else
        echo -e "\033[1;31mInvalid interface selected. Please try again.\033[0m"
    fi
done

# Configure the VM's network interface
sudo VBoxManage modifyvm sandbox --nic1 bridged --bridgeadapter1 "$INTERFACE_CHOSEN"

    # VM startup
    sudo VBoxManage startvm "sandbox" --type headless

    # Wait for VM to be started
    echo "Waiting for VM to start..."
    while ! VBoxManage showvminfo "sandbox" | grep -q "State.*running"; do
    sleep 1s
    done


    sleep 15s

    # Check if the sandbox machine is running
    if VBoxManage showvminfo sandbox | grep -q "running (since"; then
        # Recover the IP address of the sandbox machine
        ip=$(VBoxManage guestproperty get sandbox "/VirtualBox/GuestInfo/Net/0/V4/IP" | awk '{print $2}')
        echo "The sandbox machine is successfully booted and its IP address is accessible via RDP at : $ip"
    else
        echo "Sandbox machine not running"
    fi

# Variables for MySQL connection
DB_USER="toor"
DB_PASS="root"
DB_NAME="myDatabase"
CSV_FILE="/var/www/html/enterprise-attack/index.csv"
MYSQL_CNF="/etc/mysql/mysql.conf.d/mysql.cnf"

# Add local_infile=1 to [mysql] sections if not already present
sudo grep -qxF 'local_infile=1' $MYSQL_CNF || echo 'local_infile=1' | sudo tee -a $MYSQL_CNF

# Restart MySQL service to apply the changes
sudo service mysql restart

# Enable local infile globally
mysql -u root -p$DB_PASS -e "SET GLOBAL local_infile=1;"

# Create database and user
mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED WITH mysql_native_password BY '$DB_PASS';"
mysql -e "GRANT ALL ON $DB_NAME.* TO '$DB_USER'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Create tables and load data from CSV
mysql --local-infile=1 -u $DB_USER -p$DB_PASS -e "
USE $DB_NAME;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    analyst_level VARCHAR(50) NOT NULL,
    avatar VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS contents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    author_id INT NOT NULL,
    FOREIGN KEY (author_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS atomic_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tactic VARCHAR(255),
    technique_id VARCHAR(255),
    technique_name VARCHAR(255),
    test VARCHAR(255),
    test_name VARCHAR(255),
    Test_GUID VARCHAR(255),
    Executor_Name VARCHAR(255)
);

LOAD DATA LOCAL INFILE '$CSV_FILE'
INTO TABLE atomic_tests
FIELDS TERMINATED BY ',' 
ENCLOSED BY '\"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(tactic, technique_id, technique_name, test, test_name, Test_GUID, Executor_Name)
SET
    technique_id = NULLIF(technique_id, ''),
    technique_name = NULLIF(technique_name, ''),
    test = NULLIF(test, ''),
    test_name = NULLIF(test_name, ''),
    Test_GUID = NULLIF(Test_GUID, ''),
    Executor_Name = NULLIF(Executor_Name, '');
"

# Randomly generate a secure password
ADMIN_PASSWORD=$(< /dev/urandom tr -dc 'A-Za-z0-9!@#$%^&*()' | head -c 12)

# Create a temporary PHP script for hashing the password
TEMP_PHP_SCRIPT=$(mktemp)
echo "<?php
echo password_hash('$ADMIN_PASSWORD', PASSWORD_DEFAULT);
?>" > "$TEMP_PHP_SCRIPT"

# Hash the password using PHP
HASHED_PASSWORD=$(php "$TEMP_PHP_SCRIPT")

# Delete the temporary PHP script
rm "$TEMP_PHP_SCRIPT"

# Add admin user to the users table with the hashed password
mysql -e "USE $DB_NAME; INSERT INTO users (first_name, last_name, email, analyst_level, avatar, password) VALUES ('Admin', 'Admin', 'admin@local.com', 'n3', '/MD_image/admin.png', '$HASHED_PASSWORD');"

# Add admin credentials to admin.txt file
echo "admin@local.com:$ADMIN_PASSWORD" >> /home/$(logname)/admin.txt

    # Replace these values with information from your database
    DB_HOST="localhost"
    DB_USER="toor"
    DB_PASS="root"
    DB_NAME="myDatabase"

    # Escape special characters in password
    ESCAPED_DB_PASS=$(printf '%s\n' "$DB_PASS" | sed -e 's/[\/&]/\\&/g')

    # Add environment variables to /etc/apache2/envvars
    echo "export DB_HOST='$DB_HOST'" | sudo tee -a /etc/apache2/envvars
    echo "export DB_USER='$DB_USER'" | sudo tee -a /etc/apache2/envvars
    echo "export DB_PASS='$ESCAPED_DB_PASS'" | sudo tee -a /etc/apache2/envvars
    echo "export DB_NAME='$DB_NAME'" | sudo tee -a /etc/apache2/envvars

    # Restart Apache for changes to take effect
    sudo systemctl restart apache2


    # PHP project location
    PROJECT_DIR="/var/www/html" 

    # Install Composer if not already installed
    if ! command -v composer &> /dev/null
    then
        echo "Composer is not installed. Installation in progress..."
        cd /tmp
        curl -sS https://getcomposer.org/installer | php
        sudo mv composer.phar /usr/local/bin/composer
        echo "Composer has been installed."
    else
        echo "Composer is already installed."
    fi

    # Move to project directory
    cd "$PROJECT_DIR"

    # Installing PhpSpreadsheet with Composer
    echo "PhpSpreadsheet installation in progress..."
    yes | composer require phpoffice/phpspreadsheet

    echo "PhpSpreadsheet has been installed."

    find "$source_directory_path" -type d -empty -delete

    echo "The content has been moved from $source_directory_path to $destination_directory_path."

    # List of files to delete
    files_to_delete=(
        "/var/www/html/index.html"
    )

    # Browse the list of files and delete them
    for file in "${files_to_delete[@]}"; do
        if [[ -f "$file" ]]; then
            sudo rm "$file"
            echo "Deleted: $file"
        else
            echo "File does not exist and cannot be deleted: $file"
        fi
    done

    # Generate a secure encryption key
    ENCRYPTION_KEY=$(openssl rand -base64 32)

    # Add the encryption key to /etc/apache2/envvars
    echo "export ENCRYPTION_KEY=\"$ENCRYPTION_KEY\"" | sudo tee -a /etc/apache2/envvars


    # Set temporary path for cloning
TEMP_CLONE_DIR="/tmp/sigma_clone"
# Set destination path
DEST_PATH="/var/www/html/Downloaded/Sigma"

# Clone the Sigma repository in the temporary directory
git clone https://github.com/SigmaHQ/sigma.git "$TEMP_CLONE_DIR"

# Create the destination path if necessary
mkdir -p "$DEST_PATH"

# Copy rules folder to destination path
cp -r "$TEMP_CLONE_DIR/rules" "$DEST_PATH"

# Clean up temporary directory
rm -rf "$TEMP_CLONE_DIR"

echo "The 'rules' folder has been successfully copied to $DEST_PATH"

pip install sigma-cli
sigma plugin install elasticsearch
sigma plugin install splunk
#sigma plugin install qradar -f

    sudo chmod -R 775 /var/www/html/
    sudo chmod -R 77 /var/www/html/uploads/
    sudo chmod -R 777 -R /var/www/html/Downloaded/malware_upload/
    sudo chmod -R 755 /var/www/html/admin.php
    sudo mkdir /var/www/html/Downloaded/upload/
    sudo mkdir /var/www/html/config/
    sudo chmod 750 /var/www/html/config/

SERVER_IP=$(hostname -I | awk '{print $1}')

GREEN='\033[0;32m'
NC='\033[0m' 

MESSAGE="
*********************************************
*                                           *
*        PURPLELAB by Krook9d               *
*                                           *
*********************************************

Connect to: ${GREEN}http://${SERVER_IP}${NC}

The necessary passwords for the application are in admin.txt.
For better security, change them and harden the server.
"

echo -e "$MESSAGE"

