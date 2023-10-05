#!/bin/bash


# Installation du serveur Apache
apt-get update
apt-get install -y apache2

# Configuration du pare-feu pour permettre les connexions HTTP et HTTPS
ufw allow in "Apache Full"

# Configuration d'Apache pour qu'il utilise l'adresse IP de la machine
IP=$(hostname -I | awk '{print $1}')
echo "ServerName $IP" >> /etc/apache2/apache2.conf

# Création de 5 pages web dans /var/www/html/
for i in {1..5}
do
    echo "<html><head><title>Page $i</title></head><body><h1>Page $i</h1></body></html>" > /var/www/html/page$i.html
done

# Redémarrage du serveur Apache
systemctl restart apache2


# Mise à jour des paquets disponibles
sudo apt-get update

# Installation de Java 11
sudo apt-get install -y openjdk-11-jdk

# Installation de curl
sudo apt-get install -y curl

# Ajout de la clé GPG Elasticsearch
wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo apt-key add -

# Ajout du repository Elasticsearch
sudo echo "deb https://artifacts.elastic.co/packages/7.x/apt stable main" | sudo tee /etc/apt/sources.list.d/elastic-7.x.list

# Mise à jour des paquets disponibles pour inclure le repository Elasticsearch
sudo apt-get update

# Installation de Elasticsearch, Kibana et Logstash
sudo apt-get install -y elasticsearch kibana logstash

# Configuration de Kibana pour qu'il écoute sur l'adresse IP de la machine
sudo sed -i 's/#server.host: "localhost"/server.host: "0.0.0.0"/g' /etc/kibana/kibana.yml

# Configuration de Logstash pour qu'il écoute sur l'adresse IP de la machine
sudo sed -i 's/#http.host: "127.0.0.1"/http.host: "0.0.0.0"/g' /etc/logstash/logstash.yml

# ajout des ligne xpacksecurity dans le fichier elasticsearch.yml
sed -i '$a xpack.security.enabled: true' /etc/elasticsearch/elasticsearch.yml
sed -i '$a xpack.security.authc.api_key.enabled: true' /etc/elasticsearch/elasticsearch.yml
sed -i '$a discovery.type: single-node' /etc/elasticsearch/elasticsearch.yml


# Démarrage des services Elasticsearch, Kibana et Logstash
sudo systemctl enable elasticsearch.service
sudo systemctl enable kibana.service
sudo systemctl enable logstash.service
sudo systemctl start elasticsearch.service
sudo systemctl start kibana.service
sudo systemctl start logstash.service


# Ajout du lien vers Kibana dans le code Apache
sudo sed -i "s|<a href=\"\" class=\"nav-item nav-link\"><i class=\"fa fa-chart-bar me-2\"></i>Hunting</a>|<a href=\"http://$(hostname -I | cut -d' ' -f1):5601\" class=\"nav-item nav-link\"><i class=\"fa fa-chart-bar me-2\"></i>Hunting</a>|g" /var/www/html/index.html


sleep 3

echo "Lancement de la génération des mots de passe ELK..."

# Exécute la commande pour générer les mots de passe et stocke le résultat dans une variable
result=$(echo -ne 'y\n' | /usr/share/elasticsearch/bin/elasticsearch-setup-passwords auto)

# Enregistre le résultat dans un fichier texte
echo "$result" > elk-passwords.txt

echo "Les mots de passe ont été enregistrés dans le fichier ~/Documents/elk-passwords.txt."



# Extraction du mot de passe kibana_system du fichier elk-passwords.txt
kibana_system_password=$(grep -oP '(?<=PASSWORD kibana_system = ).*' elk-passwords.txt)

# Modification du fichier /etc/kibana/kibana.yml
sed -i "s/#elasticsearch.username: \"kibana_system\"/elasticsearch.username: \"kibana_system\"/" /etc/kibana/kibana.yml
sed -i "s/#elasticsearch.password: \"pass\"/elasticsearch.password: \"$kibana_system_password\"/" /etc/kibana/kibana.yml

service kibana restart



# Affichage de l'adresse IP de la machine
echo "Connectez-vous à Kibana sur http://$(hostname -I | cut -d' ' -f1):5601"