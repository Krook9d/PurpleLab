<?php
// read_csv.php

header('Content-Type: application/json');

$techniqueId = $_GET['techniqueId'];

$csvData = [];
$isHeader = true; 

if (($handle = fopen("/var/www/html/enterprise-attack/index.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
        if ($isHeader) {
            $isHeader = false; 
            continue;
        }
        if ($data[1] === $techniqueId) { 
            $csvData[] = $data;
        }
    }
    fclose($handle);
}

echo json_encode($csvData);
?>
