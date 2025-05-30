<?php
// Vérifier si le fichier a été uploadé correctement
if (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
    switch ($_FILES["file"]["error"] ?? UPLOAD_ERR_NO_FILE) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            echo "Sorry, your file is too large. Maximum size allowed is 50MB.";
            break;
        case UPLOAD_ERR_PARTIAL:
            echo "Sorry, the file was only partially uploaded.";
            break;
        case UPLOAD_ERR_NO_FILE:
            echo "Sorry, no file was uploaded.";
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            echo "Sorry, missing temporary folder.";
            break;
        case UPLOAD_ERR_CANT_WRITE:
            echo "Sorry, failed to write file to disk.";
            break;
        default:
            echo "Sorry, an unknown upload error occurred.";
            break;
    }
    exit;
}

$target_dir = "/var/www/html/Downloaded/malware_upload/";
$target_file = $target_dir . basename($_FILES["file"]["name"]);

// Check if the file already exists
if (file_exists($target_file)) {
    echo "Sorry, the file already exists.";
    exit;
}

// Check file size (double check)
if ($_FILES["file"]["size"] > 52428800) { // 50MB
    echo "Sorry, your file is too large. Maximum size is 50MB.";
    exit;
}

// Allow certain file formats
$extension = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
if(!in_array($extension, ['exe', 'dll', 'bin', 'py', 'ps1', 'pdf', 'ods', 'xlsx'])) {
    echo "Sorry, only EXE, DLL, BIN, PY, PS1, PDF, ODS, XLSX files are allowed.";
    exit;
}

// Try to upload the file
if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
    echo "The file ". htmlspecialchars(basename($_FILES["file"]["name"])) . " has been uploaded successfully.";

    // Send file to VM
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, "http://127.0.0.1:5000/upload_to_vm");
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, [
        'file' => new CURLFile($target_file, $_FILES["file"]["type"], $_FILES["file"]["name"])
    ]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        // Log CURL errors but don't show to user
        error_log("CURL Error: " . curl_error($curl));
    } else {
        // Try to parse JSON response
        $responseArray = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($responseArray['message'])) {
            $message = $responseArray['message'];
            
            // Only show positive responses from VM
            if (!empty($message)) {
                $messageLower = strtolower($message);
                if (strpos($messageLower, 'error') === false && 
                    strpos($messageLower, 'already exists') === false && 
                    strpos($messageLower, 'sorry') === false &&
                    strpos($messageLower, 'failed') === false) {
                    echo " VM Response: " . htmlspecialchars($message);
                }
            }
        }
    }

    curl_close($curl);
    
} else {
    echo "Sorry, there was an error uploading your file.";
}
?>
