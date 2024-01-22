<?php
$target_dir = "/var/www/html/Downloaded/malware_upload/";
$target_file = $target_dir . basename($_FILES["file"]["name"]);

// Check if the file already exists
if (file_exists($target_file)) {
    echo "Sorry, the file already exists.";
    exit;
}

// Check file size
if ($_FILES["file"]["size"] > 15097152) { // 2MB
    echo "Sorry, your file is too large.";
    exit;
}

// Allow certain file formats
$extension = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
if(!in_array($extension, ['exe', 'dll', 'bin', 'py', 'ps1'])) {
    echo "Sorry, only EXE, DLL, BIN, PY, PS1 files are allowed..";
    exit;
}

// Try to download the file
if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
    echo "Le fichier ". htmlspecialchars(basename($_FILES["file"]["name"])) . " a été téléchargé.";

    
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, "http://127.0.0.1:5000/upload_to_vm");
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, [
        'file' => new CURLFile($target_file, $_FILES["file"]["type"], $_FILES["file"]["name"])
    ]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($curl);

if (curl_errno($curl)) {
    echo "CURL Error: " . curl_error($curl);
} else {
 
    $responseArray = json_decode($response, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {

        $message = $responseArray['message'] ?? 'Answer received, but message not defined.';
        echo "&ensp; Answer received: " . htmlspecialchars($message);
    } else {
        echo "Error decoding JSON response: " . json_last_error_msg();
    }
}

curl_close($curl);

    
} else {
    echo "Sorry, there was an error uploading your file.";
}
?>
