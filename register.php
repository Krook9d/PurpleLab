<?php

$servername = "localhost";
$username = "toor";
$password = "root";
$dbname = "myDatabase";

// Création de la connexion
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Vérification que le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    echo "Form submitted<br>";

    // Nettoyage et validation des entrées de l'utilisateur
    $firstName = filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING);
    $lastName = filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $analystLevel = filter_input(INPUT_POST, 'analystLevel', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email format");
    }

    // Validation du mot de passe
    if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $password)) {
        die("Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.");
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Vérification que le champ "avatar" existe
    if (isset($_FILES["avatar"])) {
        echo "Avatar field exists<br>";

        // Upload de l'avatar
        $targetDir = "uploads/";
        $avatarFile = $targetDir . basename($_FILES["avatar"]["name"]);
        $imageFileType = strtolower(pathinfo($avatarFile, PATHINFO_EXTENSION));

        // Vérifier si le fichier a été uploadé sans erreur
        if ($_FILES["avatar"]["error"] == UPLOAD_ERR_OK) {
            // Vérifie si le fichier image est une image réelle ou une fausse image
            $check = getimagesize($_FILES["avatar"]["tmp_name"]);
            if ($check !== false) {
                // Autoriser certains formats de fichier
                if ($imageFileType == "jpg" || $imageFileType == "png" || $imageFileType == "jpeg") {
                    // Déplacer le fichier dans le dossier de destination
                    if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $avatarFile)) {
                        echo "The file " . basename($_FILES["avatar"]["name"]) . " has been uploaded.<br>";
                    } else {
                        die("Sorry, there was an error uploading your file.");
                    }
                } else {
                    die("Sorry, only JPG, JPEG & PNG files are allowed.");
                }
            } else {
                die("File is not an image.");
            }
        } else {
            die("Error uploading file.");
        }

        // Utiliser des déclarations préparées pour éviter les injections SQL
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, analyst_level, avatar, password) VALUES (?, ?, ?, ?, ?, ?)");
        
        // Vérifier si la préparation de la requête a réussi
        if ($stmt === false) {
            die("Error in prepared statement<br>");
        }

        $stmt->bind_param("ssssss", $firstName, $lastName, $email, $analystLevel, $avatarFile, $hashedPassword);

        // Exécuter la requête d'insertion
        if ($stmt->execute()) {
            echo "New record created successfully<br>";
        } else {
            echo "Error: " . $stmt->error . "<br>";
        }

        $stmt->close();

    } else {
        die("Avatar field is required.");
    }
} else {
    echo "Form not submitted.<br>";
}

$conn->close();

?>

