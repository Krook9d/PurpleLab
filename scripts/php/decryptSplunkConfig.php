<?php
require '/var/www/html/scripts/php/encryption.php';

header('Content-Type: application/json');

$encryptionKey = getenv('ENCRYPTION_KEY');
if (!$encryptionKey) {
    echo json_encode(['error' => 'Encryption key not found.']);
    exit();
}


$configFile = '/var/www/html/config/splunk_config.json';
if (!file_exists($configFile)) {
    echo json_encode(['error' => 'Configuration file not found.']);
    exit();
}

$configData = json_decode(file_get_contents($configFile), true);
if (!$configData) {
    echo json_encode(['error' => 'Failed to read configuration.']);
    exit();
}


$splunkHost = decryptData($configData['splunkHost'], $encryptionKey);
$splunkPort = decryptData($configData['splunkPort'], $encryptionKey);
$splunkToken = decryptData($configData['splunkToken'], $encryptionKey);

echo json_encode([
    'SPLUNK_HOST' => $splunkHost,
    'SPLUNK_PORT' => $splunkPort,
    'SPLUNK_API_TOKEN' => $splunkToken
]);
?>
