<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);



session_start();

$email = $_POST['email'];
$password = $_POST['password'];

$conn = new mysqli(
    getenv('DB_HOST'), 
    getenv('DB_USER'), 
    getenv('DB_PASS'), 
    getenv('DB_NAME')
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT id, password FROM users WHERE email=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($user_id, $hash);

if ($stmt->fetch() && password_verify($password, $hash)) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;
    header('Location: index.php');
} else {
    echo "Connection error. Incorrect email or password.";
}

$stmt->close();
$conn->close();
?>
