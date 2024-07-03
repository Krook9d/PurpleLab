<?php
// search_techniques.php
require '/var/www/html/vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

// Function to read the Excel file and return filtered techniques
function searchTechniques($searchTerm) {
    $reader = new Xlsx();
    $spreadsheet = $reader->load('/var/www/html/enterprise-attack/enterprise-attack-techniques.xlsx');
    $worksheet = $spreadsheet->getActiveSheet();
    $techniques = [];

    $searchTerm = strtoupper($searchTerm);

    foreach ($worksheet->getRowIterator() as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(FALSE); 

        $cells = [];
        foreach ($cellIterator as $cell) {
            $cells[] = $cell->getValue();
        }

        $cellValue = strtoupper($cells[0]);

        if (strncmp($cellValue, $searchTerm, strlen($searchTerm)) === 0) {
            $techniques[] = [
                'id' => $cells[0], // ID of the technique
                'name' => $cells[2] // Name of the technique
            ];
        }
    }

    return $techniques;
}


// Function to obtain details of a specific technique by its ID
function getTechniqueDetails($id) {
    $reader = new Xlsx();
    $spreadsheet = $reader->load('/var/www/html/enterprise-attack/enterprise-attack-techniques.xlsx');
    $worksheet = $spreadsheet->getActiveSheet();
    
    $highestColumn = $worksheet->getHighestColumn();
    $headers = $worksheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE)[0];

    $techniqueDetails = [];

    foreach ($worksheet->getRowIterator(2) as $row) {

        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(FALSE);

        $cells = [];
        foreach ($cellIterator as $cell) {
            $cells[] = $cell->getValue();
        }

        if (strtoupper($cells[0]) === strtoupper($id)) {
            foreach ($cells as $index => $value) {
               
                $techniqueDetails[$headers[$index]] = $value;
            }
            break; 
        }
    }

    // Filter details to exclude certain columns
    $excludedColumns = ['STIX ID', 'domain', 'is sub-technique', 'contributors', 'supports remote', 'relationship citations'];
    foreach ($excludedColumns as $excludedColumn) {
        unset($techniqueDetails[$excludedColumn]);
    }

    return $techniqueDetails;
}


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
?>
