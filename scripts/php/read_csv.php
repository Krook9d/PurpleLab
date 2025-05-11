<?php
// read_csv.php

header('Content-Type: application/json');

if (!isset($_GET['techniqueId'])) {
    die(json_encode(['error' => 'Technique ID manquant']));
}

$techniqueId = $_GET['techniqueId'];

// Connexion à la base de données
$conn_string = "host=localhost dbname=purplelab user=toor password=root";
$conn = pg_connect($conn_string);

if (!$conn) {
    die(json_encode(['error' => 'Erreur de connexion à la base de données']));
}

// Récupération des tests atomiques pour cette technique
$query = "SELECT tactic, technique_id, technique_name, test, test_name, test_guid, executor_name 
          FROM atomic_tests 
          WHERE technique_id = $1 
          ORDER BY test";

$result = pg_query_params($conn, $query, [$techniqueId]);

if (!$result) {
    die(json_encode(['error' => 'Erreur lors de la récupération des données: ' . pg_last_error($conn)]));
}

$data = [];
while ($row = pg_fetch_assoc($result)) {
    $data[] = [
        $row['tactic'],
        $row['technique_id'],
        $row['technique_name'],
        $row['test'],
        $row['test_name'],
        $row['test_guid'],
        $row['executor_name']
    ];
}

echo json_encode($data);
pg_close($conn);
?>
