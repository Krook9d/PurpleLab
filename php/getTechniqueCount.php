<?php

require '/var/www/html/vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;


function countExcelRows($filePath) {
    $reader = new Xlsx();
    try {
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        // Lire uniquement la première colonne de la première feuille
        $columnValues = $worksheet->rangeToArray('A1:A' . $worksheet->getHighestRow(), null, true, true, false);

        // Compter les lignes non vides
        $rowCount = 0;
        foreach ($columnValues as $cell) {
            if (!empty($cell[0])) {
                $rowCount++;
            }
        }

        return $rowCount - 1; // Retirez 1 pour l'en-tête
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        die('Error loading file: ' . $e->getMessage());
    }
}

function getCachedCount($cacheFile, $excelFile, $cacheLifetime = 86400) {
    // Vérifier si le fichier de cache existe et est assez récent
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheLifetime) {
        // Lire la valeur depuis le cache
        return file_get_contents($cacheFile);
    } else {
        // Lire le fichier Excel et mettre à jour le cache
        $count = countExcelRows($excelFile);
        file_put_contents($cacheFile, $count);
        return $count;
    }
}

// Chemins de fichiers
$filePath = '/var/www/html/enterprise-attack/enterprise-attack-techniques.xlsx';
$cacheFile = '/var/www/html/cache/technique_count.cache';

// Obtenir le nombre de techniques, avec cache
$numberOfTechniques = getCachedCount($cacheFile, $filePath);

// Renvoyer le nombre de techniques au client
echo $numberOfTechniques;

?>