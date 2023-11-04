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
if(!in_array($extension, ['exe', 'dll', 'bin', 'py'])) {
    echo "Désolé, seuls les fichiers EXE, DLL, BIN, PY sont autorisés.";
    exit;
}

// Essayer de télécharger le fichier
if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
    echo "Le fichier ". htmlspecialchars(basename($_FILES["file"]["name"])). " a été téléchargé.";
} else {
    echo "Désolé, il y a eu une erreur lors du téléchargement de votre fichier.";
}
?>
