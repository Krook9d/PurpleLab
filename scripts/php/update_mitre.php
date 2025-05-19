<?php
// update_mitre.php
header('Content-Type: application/json');

// Récupérer les variables d'environnement nécessaires pour la connexion à la base de données
$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');

// Vérifier que les variables sont disponibles
if (!$db_host || !$db_name || !$db_user || !$db_pass) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Variables d\'environnement pour la base de données manquantes'
    ]);
    exit;
}

// Configurer la commande avec les variables d'environnement
$command = "python3 /var/www/html/scripts/attackToExcel.py " .
           "-db-host '$db_host' " .
           "-db-name '$db_name' " .
           "-db-user '$db_user' " .
           "-db-pass '$db_pass'";

// Exécuter le script Python
$output = [];
$return_code = 0;
exec($command . " 2>&1", $output, $return_code);

// Vérifier si l'exécution a réussi
if ($return_code === 0) {
    echo json_encode([
        'status' => 'success',
        'message' => 'La base de données MITRE ATT&CK a été mise à jour avec succès',
        'output' => implode("\n", $output)
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors de la mise à jour de la base de données MITRE ATT&CK',
        'output' => implode("\n", $output)
    ]);
}
?> 
