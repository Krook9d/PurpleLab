<?php

require '/var/www/html/vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;


function countExcelRows($filePath) {
    $reader = new Xlsx();
    try {
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        // Read only the first column of the first sheet
        $columnValues = $worksheet->rangeToArray('A1:A' . $worksheet->getHighestRow(), null, true, true, false);

        // Count non-empty lines
        $rowCount = 0;
        foreach ($columnValues as $cell) {
            if (!empty($cell[0])) {
                $rowCount++;
            }
        }

        return $rowCount - 1; 
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        die('Error loading file: ' . $e->getMessage());
    }
}

function getCachedCount($cacheFile, $excelFile, $cacheLifetime = 86400) {
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheLifetime) {
        
        return file_get_contents($cacheFile);
    } else {
        
        $count = countExcelRows($excelFile);
        file_put_contents($cacheFile, $count);
        return $count;
    }
}

// File paths
$filePath = '/var/www/html/enterprise-attack/enterprise-attack-techniques.xlsx';
$cacheFile = '/var/www/html/cache/technique_count.cache';

$numberOfTechniques = getCachedCount($cacheFile, $filePath);

echo $numberOfTechniques;

?>
