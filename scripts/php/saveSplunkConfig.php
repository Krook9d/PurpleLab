<?php
session_start();

require '/var/www/html/scripts/php/encryption.php';

if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@local.com') {
    header('connexion.html');
    exit();
}

$encryptionKey = getenv('ENCRYPTION_KEY');
if (!$encryptionKey) {
    die("Encryption key not found.");
}

$splunkHost = encryptData($_POST['splunkHost'], $encryptionKey);
$splunkPort = encryptData($_POST['splunkPort'], $encryptionKey);
$splunkToken = encryptData($_POST['splunkToken'], $encryptionKey);

file_put_contents('/var/www/html/config/splunk_config.json', json_encode([
    'splunkHost' => $splunkHost,
    'splunkPort' => $splunkPort,
    'splunkToken' => $splunkToken,
]));

$_SESSION['splunk_config_saved'] = true;
header('Location: /admin.php');
exit();
?>
