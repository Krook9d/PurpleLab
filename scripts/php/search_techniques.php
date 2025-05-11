<?php
// search_techniques.php

// Connexion à la base de données
$conn_string = "host=localhost dbname=purplelab user=toor password=root";
$conn = pg_connect($conn_string);

if (!$conn) {
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Erreur de connexion à la base de données']));
}

// Function to search techniques from database
function searchTechniques($searchTerm) {
    global $conn;
    
    $searchTerm = strtoupper($searchTerm);
    $searchPattern = "$searchTerm%";
    
    $query = "SELECT id, name 
              FROM mitre_techniques 
              WHERE UPPER(id) LIKE $1 
              ORDER BY id";
    
    $result = pg_query_params($conn, $query, [$searchPattern]);
    
    if (!$result) {
        die(json_encode(['error' => 'Erreur lors de la recherche']));
    }
    
    $techniques = [];
    while ($row = pg_fetch_assoc($result)) {
        $techniques[] = $row;
    }
    
    return $techniques;
}

// Function to get technique details from database
function getTechniqueDetails($id) {
    global $conn;
    
    $query = "SELECT * FROM mitre_techniques WHERE id = $1";
    $result = pg_query_params($conn, $query, [$id]);
    
    if (!$result) {
        die(json_encode(['error' => 'Erreur lors de la récupération des détails']));
    }
    
    $technique = pg_fetch_assoc($result);
    
    if ($technique) {
        // Map database columns to expected field names
        $techniqueDetails = [
            'ID' => $technique['id'],
            'Name' => $technique['name'],
            'description' => $technique['description'],
            'url' => $technique['url'],
            'created' => $technique['created'],
            'last_modified' => $technique['last_modified'],
            'tactics' => $technique['tactics'],
            'detection' => $technique['detection'],
            'platforms' => $technique['platforms'],
            'data_sources' => $technique['data_sources'],
            'defenses_bypassed' => $technique['defenses_bypassed'],
            'permissions_required' => $technique['permissions_required'],
            'system_requirements' => $technique['system_requirements'],
            'impact_type' => $technique['impact_type'],
            'effective_permissions' => $technique['effective_permissions']
        ];
        
        // Filter details to exclude certain columns
        $excludedColumns = ['STIX ID', 'domain', 'is sub-technique', 'contributors', 'supports remote', 'relationship citations'];
        foreach ($excludedColumns as $excludedColumn) {
            unset($techniqueDetails[$excludedColumn]);
        }
        
        return $techniqueDetails;
    }
    
    return [];
}

// Process requests
if (isset($_POST['searchTerm'])) {
    $searchTerm = $_POST['searchTerm'];
    $techniques = searchTechniques($searchTerm);
    header('Content-Type: application/json');
    echo json_encode($techniques);
} elseif (isset($_POST['id'])) {
    $id = $_POST['id'];
    $techniqueDetails = getTechniqueDetails($id);
    header('Content-Type: application/json');
    echo json_encode($techniqueDetails);
}

// Close connection
pg_close($conn);
?>
