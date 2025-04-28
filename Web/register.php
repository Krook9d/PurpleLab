<?php

// PostgreSQL connection
$conn_string = sprintf(
    "host=%s port=5432 dbname=%s user=%s password=%s",
    getenv('DB_HOST'),
    getenv('DB_NAME'),
    getenv('DB_USER'),
    getenv('DB_PASS')
);

$conn = pg_connect($conn_string);

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Cleaning and validation of user inputs
    $firstName = filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING);
    $lastName = filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $analystLevel = filter_input(INPUT_POST, 'analystLevel', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email format");
    }

    // Password validation
    if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $password)) {
        die("Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.");
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    if (isset($_FILES["avatar"])) {

        $targetDir = "uploads/";
        $avatarFile = $targetDir . basename($_FILES["avatar"]["name"]);
        $imageFileType = strtolower(pathinfo($avatarFile, PATHINFO_EXTENSION));

        // Check that the file has been uploaded without errors
        if ($_FILES["avatar"]["error"] == UPLOAD_ERR_OK) {
            // Checks whether the image file is a real image or a fake image
            $check = getimagesize($_FILES["avatar"]["tmp_name"]);
            if ($check !== false) {
                // Allow certain file formats
                if ($imageFileType == "jpg" || $imageFileType == "png" || $imageFileType == "jpeg") {
                    // Move file to destination folder
                    if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $avatarFile)) {
                        // Use PostgreSQL parameterized query to avoid SQL injections
                        $sql = "INSERT INTO users (first_name, last_name, email, analyst_level, avatar, password) VALUES ($1, $2, $3, $4, $5, $6)";
                        
                        $result = pg_query_params($conn, $sql, array($firstName, $lastName, $email, $analystLevel, $avatarFile, $hashedPassword));
                        
                        if ($result) {
                            echo "<script type='text/javascript'>
                                    alert('User successfully created');
                                    if (confirm('User successfully created. Do you want to go to the login page?')) {
                                        window.location.href = 'connexion.html';
                                    }
                                  </script>";
                        } else {
                            echo "Error: " . pg_last_error($conn) . "<br>";
                        }
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
    } else {
        die("Avatar field is required.");
    }
} else {
    echo "Form not submitted.<br>";
}

pg_close($conn);

?>
