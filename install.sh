#!/bin/bash

apt remove -y needrestart
# Installation du serveur Apache
apt-get update
apt-get install -y dialog
apt-get -y install megatools
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
phpenmod mysqli
apt-get -y install php-xml php-gd php-mbstring php-zip
systemctl restart apache2
apt install -y mysql-server
systemctl start mysql
systemctl enable mysql

# Configuration du pare-feu pour permettre les connexions HTTP et HTTPS
ufw allow in "Apache Full"

# Configuration d'Apache pour qu'il utilise l'adresse IP de la machine
IP=$(hostname -I | awk '{print $1}')
echo "ServerName $IP" >> /etc/apache2/apache2.conf


# Redémarrage du serveur Apache
systemctl restart apache2

# Installation de Java 11
sudo apt-get install -y openjdk-11-jdk

# Installation de curl
sudo apt-get install -y curl

# Ajout de la clé GPG Elasticsearch
wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo apt-key add -

# Ajout du repository Elasticsearch
sudo echo "deb https://artifacts.elastic.co/packages/8.x/apt stable main" | sudo tee /etc/apt/sources.list.d/elastic-8.x.list

# Mise à jour des paquets disponibles pour inclure le repository Elasticsearch
sudo apt-get update

# Installation de Elasticsearch, Kibana et Logstash
sudo apt-get install -y elasticsearch kibana logstash | tee temp.txt
grep 'The generated password for the elastic built-in' temp.txt >> admin.txt
rm temp.txt


sudo apt-get install -y filebeat
sudo systemctl enable filebeat

# Configuration de Kibana pour qu'il écoute sur l'adresse IP de la machine
sudo sed -i 's/#server.host: "localhost"/server.host: "0.0.0.0"/g' /etc/kibana/kibana.yml

# Configuration de Logstash pour qu'il écoute sur l'adresse IP de la machine
sudo sed -i 's/#http.host: "127.0.0.1"/http.host: "0.0.0.0"/g' /etc/logstash/logstash.yml

# ajout des ligne xpacksecurity dans le fichier elasticsearch.yml

sed -i '$a xpack.security.authc.api_key.enabled: true' /etc/elasticsearch/elasticsearch.yml


# Démarrage des services Elasticsearch, Kibana et Logstash
sudo systemctl enable elasticsearch.service
sudo systemctl enable kibana.service
sudo systemctl enable logstash.service
sudo systemctl start elasticsearch.service
sudo systemctl start kibana.service
sudo systemctl start logstash.service


# Exécution de la commande pour créer le jeton d'enrôlement pour Kibana
# et ajout de la sortie dans admin.txt
/usr/share/elasticsearch/bin/elasticsearch-create-enrollment-token -s kibana >> admin.txt


sleep 2


# Télécharger le fichier PurpleLab.tar


megadl 'https://mega.nz/file/Eucl3YqC#qtUba2LFIJpzSCLJYFXVoM86-ualrsCyvZOUpu0NuBo'
# Vérifier si le téléchargement a réussi

mv PurpleLab.tar /var/www/html/

# Extract the contents of PurpleLab.tar to /var/www/html
tar -xf /var/www/html/PurpleLab.tar -C /var/www/html

sleep 1
rm /var/www/html/PurpleLab.tar

echo "<VirtualHost *:80>

    DirectoryIndex index.php

</VirtualHost>" | sudo tee /etc/apache2/sites-available/000-default.conf

sudo systemctl restart apache2

# Move app.py from the extracted archive to the home directory of the active user
mv /var/www/html/app.py /home/$(logname)/app.py


sudo apt install -y virtualbox

# Importation de la VM
sudo VBoxManage import /var/www/html/sandbox.ova

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


# ---------- SQL PART ---------#

# Variables pour la connexion MySQL
MYSQL_USER="root" # 
DB_NAME="myDatabase"

# Commandes SQL pour créer la base de données et l'utilisateur
SQL_COMMANDS="
CREATE DATABASE IF NOT EXISTS $DB_NAME;
CREATE USER IF NOT EXISTS 'toor'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';
GRANT ALL ON $DB_NAME.* TO 'toor'@'localhost';
FLUSH PRIVILEGES;
"

# Commandes SQL pour créer la table users
SQL_CREATE_TABLE="
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
"

# Commandes SQL pour créer la table contents
SQL_CREATE_TABLE_CONTENTS="
USE $DB_NAME;
CREATE TABLE IF NOT EXISTS contents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL
);
"


# Exécution des commandes SQL pour la base de données et l'utilisateur
echo "$SQL_COMMANDS" | mysql 

# Exécution des commandes pour créer la table
echo "$SQL_CREATE_TABLE" | mysql 

echo "$SQL_CREATE_TABLE_CONTENTS" | mysql 


# Extraire le mot de passe et le stocker dans une variable
ELASTIC_PASSWORD=$(grep "The generated password for the elastic built-in superuser is :" admin.txt | sed 's/.*: \([^ ]*\).*/\1/')

# Supprimer les retours chariot Windows (CR) si présents
ELASTIC_PASSWORD=$(echo "$ELASTIC_PASSWORD" | tr -d '\r')

# Ajouter la variable d'environnement à envvars
echo "export ELASTIC_PASSWORD=\"$ELASTIC_PASSWORD\"" | sudo tee -a /etc/apache2/envvars

# Remplacez ces valeurs par les informations de votre base de données
DB_HOST="localhost"
DB_USER="toor"
DB_PASS="root"
DB_NAME="myDatabase"

# Échapper les caractères spéciaux dans le mot de passe
ESCAPED_DB_PASS=$(printf '%s\n' "$DB_PASS" | sed -e 's/[\/&]/\\&/g')

# Ajouter les variables d'environnement au fichier /etc/apache2/envvars
echo "export DB_HOST='$DB_HOST'" | sudo tee -a /etc/apache2/envvars
echo "export DB_USER='$DB_USER'" | sudo tee -a /etc/apache2/envvars
echo "export DB_PASS='$ESCAPED_DB_PASS'" | sudo tee -a /etc/apache2/envvars
echo "export DB_NAME='$DB_NAME'" | sudo tee -a /etc/apache2/envvars

# Redémarrer Apache pour que les changements prennent effet
sudo systemctl restart apache2


# Elasticsearch configuration file path
elasticsearch_config_path="/etc/elasticsearch/jvm.options.d/custom.options"

# Configuration content to be inserted into the Elasticsearch configuration file
elasticsearch_config_content="# ELK Stack JVM Heap Size - see /etc/elasticsearch/jvm.options
-Xms4g
-Xmx4g"

# Create the Elasticsearch configuration file and write the content
echo "$elasticsearch_config_content" | sudo tee "$elasticsearch_config_path" > /dev/null

# Restart the Elasticsearch service
sudo systemctl restart elasticsearch



# Emplacement du projet PHP
PROJECT_DIR="/var/www/html" # Mettez à jour avec le chemin de votre projet PHP

# Installer Composer s'il n'est pas déjà installé
if ! command -v composer &> /dev/null
then
    echo "Composer n'est pas installé. Installation en cours..."
    cd /tmp
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    echo "Composer a été installé."
else
    echo "Composer est déjà installé."
fi

# Se déplacer dans le répertoire du projet
cd "$PROJECT_DIR"

# Installer PhpSpreadsheet avec Composer
echo "Installation de PhpSpreadsheet en cours..."
composer require phpoffice/phpspreadsheet

echo "PhpSpreadsheet a été installé."

# Définir le chemin du répertoire source contenant les données à déplacer
source_directory_path="/home/purplelab/PurpleLab"

# Définir le chemin du répertoire de destination où les données doivent être déplacées
destination_directory_path="/var/www/html"

# Utiliser rsync pour déplacer les fichiers du répertoire source vers le répertoire de destination
# L'option '-a' est pour 'archive' et préserve les permissions et autres attributs de fichier
# L'option '-v' est pour 'verbose' et affiche les détails du processus de transfert
# L'option '--remove-source-files' indique à rsync de supprimer les fichiers source après leur copie
sudo rsync -av --remove-source-files "$source_directory_path/" "$destination_directory_path"

# Trouver et supprimer les répertoires vides restants dans le répertoire source
# '-type d' indique à find de chercher uniquement les répertoires
# '-empty' filtre pour ne trouver que les répertoires vides
# '-delete' supprime les répertoires trouvés par la commande find
find "$source_directory_path" -type d -empty -delete

# Afficher un message de confirmation une fois le processus terminé
echo "Le contenu a été déplacé de $source_directory_path vers $destination_directory_path."

# Liste des fichiers à supprimer
files_to_delete=(
    "/var/www/html/index.html"
    "/var/www/html/dropzone.png"
    "/var/www/html/database.sql"
)

# Parcourir la liste des fichiers et les supprimer
for file in "${files_to_delete[@]}"; do
    if [[ -f "$file" ]]; then
        sudo rm "$file"
        echo "Supprimé: $file"
    else
        echo "Le fichier n'existe pas et ne peut pas être supprimé: $file"
    fi
done

sudo chmod -R 775 /var/www/html/


# Chemin vers le fichier filebeat.yml
FILEBEAT_CONFIG="/etc/filebeat/filebeat.yml"

# Chemin vers le fichier admin.txt (dans le même répertoire que le script)
ADMIN_FILE="./admin.txt"

# Extraire le mot de passe du fichier admin.txt
# Extraire le mot de passe du fichier admin.txt
PASSWORD=$(grep "The generated password for the elastic built-in superuser is :" $ADMIN_FILE | awk -F': ' '{print $2}' | tr -d '\r')

# Vérifier si le mot de passe a été trouvé
if [ -z "$PASSWORD" ]; then
    echo "Le mot de passe n'a pas pu être trouvé dans $ADMIN_FILE."
    exit 1
fi

# Partie 1: Modification de la section 'Elasticsearch Output'
# Supprimer les commentaires des lignes spécifiées
sed -i '/#output.logstash:/ s/#//' $FILEBEAT_CONFIG
sed -i '/#hosts: \["localhost:9200"\]/ s/#//' $FILEBEAT_CONFIG
sed -i '/#protocol: "https"/ s/#//' $FILEBEAT_CONFIG
sed -i '/#username:/ s/#//' $FILEBEAT_CONFIG

# Remplacer la ligne du mot de passe avec le mot de passe extrait
sed -i "s/#password: .*/password: \"$PASSWORD\"/" $FILEBEAT_CONFIG

# Ajouter 'ssl.verification_mode: "none"' en dessous de 'hosts: ["localhost:9200"]'
sed -i '/hosts: \["localhost:9200"\]/a \ \ ssl.verification_mode: "none"' $FILEBEAT_CONFIG

# Commenter la configuration de Logstash
sed -i '/output.logstash:/,/^[^#]/ s/^/#/' $FILEBEAT_CONFIG

# Construction de la configuration à ajouter pour le type 'log'
LOG_CONFIG="- type: log\n  enabled: true\n  paths:\n    - /var/www/html/Downloaded/Log_simulation/*.json\n  json.keys_under_root: true\n  json.add_error_key: true\n  json.message_key: log\n  fields_under_root: true\n  fields:\n    timestamp: date\n  date_formats:\n    - 'MMM dd HH:mm:ss'"

# Ajouter la configuration après la ligne spécifiée
awk -v log_config="$LOG_CONFIG" '/#- c:\\programdata\\elasticsearch\\logs\\*/{print; print log_config; next}1' $FILEBEAT_CONFIG > temp.yml && mv temp.yml $FILEBEAT_CONFIG

# Redémarrer le service Filebeat pour appliquer les changements
systemctl restart filebeat

# Afficher un message indiquant la fin du script et le redémarrage de Filebeat
echo "Filebeat a été redémarré pour appliquer les changements de configuration."
