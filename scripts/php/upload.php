<?php
$target_dir = "/var/www/html/Downloaded/malware_upload/";
$target_file = $target_dir . basename($_FILES["file"]["name"]);

// Vérifier si le fichier existe déjà
if (file_exists($target_file)) {
    echo "Désolé, le fichier existe déjà.";
    exit;
}

// Vérifier la taille du fichier
if ($_FILES["file"]["size"] > 15097152) { // 2MB
    echo "Désolé, votre fichier est trop volumineux.";
    exit;
}

// Autoriser certains formats de fichier
$extension = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
if(!in_array($extension, ['exe', 'dll', 'bin', 'py', 'ps1'])) {
    echo "Désolé, seuls les fichiers EXE, DLL, BIN, PY, PS1 sont autorisés.";
    exit;
}

// Essayer de télécharger le fichier
if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
    echo "Le fichier ". htmlspecialchars(basename($_FILES["file"]["name"])) . " a été téléchargé.";

    // Initialiser une session cURL
    $curl = curl_init();

    // Configurer les options de cURL pour la requête POST
    curl_setopt($curl, CURLOPT_URL, "http://127.0.0.1:5000/upload_to_vm");
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, [
        'file' => new CURLFile($target_file, $_FILES["file"]["type"], $_FILES["file"]["name"])
    ]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

// Exécuter la requête cURL et récupérer la réponse
$response = curl_exec($curl);

// Vérifier s'il y a eu des erreurs
if (curl_errno($curl)) {
    echo "CURL Error: " . curl_error($curl);
} else {
    // Décoder la réponse JSON
    $responseArray = json_decode($response, true);
    
    // Vérifier si le décodage a réussi
    if (json_last_error() === JSON_ERROR_NONE) {
        // Afficher le message de réponse
        $message = $responseArray['message'] ?? 'Réponse reçue, mais le message n\'est pas défini.';
        echo "&ensp; Réponse reçue: " . htmlspecialchars($message);
    } else {
        echo "Erreur lors du décodage de la réponse JSON: " . json_last_error_msg();
    }
}

// Fermer la session cURL
curl_close($curl);

    
} else {
    echo "Désolé, il y a eu une erreur lors du téléchargement de votre fichier.";
}
?>
