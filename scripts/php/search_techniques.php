<?php
// search_techniques.php
require '/var/www/html/vendor/autoload.php'; // Assurez-vous de mettre à jour le chemin

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

// Fonction pour lire le fichier Excel et retourner les techniques filtrées
function searchTechniques($searchTerm) {
    $reader = new Xlsx();
    $spreadsheet = $reader->load('/var/www/html/enterprise-attack/enterprise-attack-techniques.xlsx');
    $worksheet = $spreadsheet->getActiveSheet();
    $techniques = [];

    // Convertir le terme de recherche en majuscules pour une recherche insensible à la casse
    $searchTerm = strtoupper($searchTerm);

    foreach ($worksheet->getRowIterator() as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(FALSE); // Cela inclut les cellules vides

        $cells = [];
        foreach ($cellIterator as $cell) {
            $cells[] = $cell->getValue();
        }

        // Convertir la valeur de la cellule en majuscules pour la comparaison
        $cellValue = strtoupper($cells[0]);

        // Vérifier si la valeur de la cellule commence par le terme de recherche
        if (strncmp($cellValue, $searchTerm, strlen($searchTerm)) === 0) {
            $techniques[] = [
                'id' => $cells[0], // ID de la technique
                'name' => $cells[2] // Nom de la technique
                // Ajoutez d'autres détails que vous souhaitez renvoyer
            ];
        }
    }

    return $techniques;
}


// Fonction pour obtenir les détails d'une technique spécifique par son ID
function getTechniqueDetails($id) {
    $reader = new Xlsx();
    $spreadsheet = $reader->load('/var/www/html/enterprise-attack/enterprise-attack-techniques.xlsx');
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Lire les en-têtes de colonnes de la première ligne
    $highestColumn = $worksheet->getHighestColumn();
    $headers = $worksheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE)[0];

    $techniqueDetails = [];

    foreach ($worksheet->getRowIterator(2) as $row) {
        // Puisque les en-têtes de colonnes sont dans la première ligne, on commence à la deuxième ligne
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(FALSE);

        $cells = [];
        foreach ($cellIterator as $cell) {
            $cells[] = $cell->getValue();
        }

        if (strtoupper($cells[0]) === strtoupper($id)) {
            foreach ($cells as $index => $value) {
                // Utilisez les en-têtes de colonnes comme clés dans le tableau associatif
                $techniqueDetails[$headers[$index]] = $value;
            }
            break; // Quittez la boucle dès que vous avez trouvé la technique correspondante
        }
    }

    // Filtrer les détails pour exclure certaines colonnes
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
