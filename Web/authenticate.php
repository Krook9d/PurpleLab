<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

$email = $_POST['email'];
$password = $_POST['password'];

$conn_string = sprintf(
    "host=%s port=5432 dbname=%s user=%s password=%s",
    getenv('DB_HOST'),
    getenv('DB_NAME'),
    getenv('DB_USER'),
    getenv('DB_PASS')
);

$conn = pg_connect($conn_string);

if (!$conn) {
    die("PostgreSQL connection failure");
}

$sql = "SELECT id, password FROM users WHERE email=$1";
$result = pg_query_params($conn, $sql, array($email));

if ($result && $row = pg_fetch_assoc($result)) {
    $user_id = $row['id'];
    $hash = $row['password'];
    
    if (password_verify($password, $hash)) {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;
        header('Location: index.php');
        exit;
    }
}

echo "Login error. Incorrect email or password.";
pg_free_result($result);
pg_close($conn);
?>
