<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@local.com') {
    header('Location: /connexion.html');
    exit();
}

// Directory to store keys
$keysDir = '/var/www/html/alienvault';
$encryptedKeyFile = $keysDir . '/api_key.enc';
$keyFile = $keysDir . '/.secret_key';
$configFile = $keysDir . '/config.json';

// Handle API key deletion
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    // Remove the encrypted key file if it exists
    if (file_exists($encryptedKeyFile)) {
        unlink($encryptedKeyFile);
    }
    
    // Update config file to indicate no key is configured
    $configData = json_encode([
        'api_key_configured' => false,
        'last_updated' => date('Y-m-d H:i:s'),
        'key_preview' => null
    ]);
    
    file_put_contents($configFile, $configData);
    
    // Set session variable to indicate success
    $_SESSION['alienvault_config_deleted'] = true;
    
    // Redirect back to admin page
    header('Location: /admin.php');
    exit();
}

// Check if API key was submitted
if (isset($_POST['apiKey']) && !empty($_POST['apiKey'])) {
    $apiKey = trim($_POST['apiKey']);
    
    // Test the API key validity by making a simple request
    $validKey = testApiKey($apiKey);
    
    if (!$validKey) {
        $_SESSION['alienvault_config_error'] = "The API key appears to be invalid. Please check and try again.";
        header('Location: /admin.php');
        exit();
    }
    
    // Create directory if it doesn't exist
    if (!file_exists($keysDir)) {
        mkdir($keysDir, 0750, true);
    }
    
    // Generate a secret key for encryption (or use an existing one)
    if (!file_exists($keyFile)) {
        $secretKey = bin2hex(random_bytes(32));
        file_put_contents($keyFile, $secretKey);
        chmod($keyFile, 0600); // Only readable by owner
    } else {
        $secretKey = file_get_contents($keyFile);
    }
    
    // Encrypt the API key
    $iv = random_bytes(16); // Generate initialization vector
    $encrypted = openssl_encrypt($apiKey, 'aes-256-cbc', hex2bin($secretKey), 0, $iv);
    
    // Save the encrypted key with the IV
    $data = base64_encode($iv) . ':' . $encrypted;
    file_put_contents($encryptedKeyFile, $data);
    chmod($encryptedKeyFile, 0640); // Readable by owner and group
    
    // Create a readable config indicator file
    $configData = json_encode([
        'api_key_configured' => true,
        'last_updated' => date('Y-m-d H:i:s'),
        'key_preview' => substr($apiKey, 0, 4) . '****' . substr($apiKey, -4)
    ]);
    file_put_contents($configFile, $configData);
    
    // Set session variable to indicate success
    $_SESSION['alienvault_config_saved'] = true;
    
    // Update script permissions to ensure it can access the encrypted key
    $pythonScript = $keysDir . '/alienvault.py';
    if (file_exists($pythonScript)) {
        chmod($pythonScript, 0750);
    }
    
    // Run the script to update the dashboard data
    shell_exec('python3 /var/www/html/alienvault/alienvault.py > /dev/null 2>&1 &');
}

// Redirect back to admin page
header('Location: /admin.php');
exit();

/**
 * Test if the API key is valid
 * 
 * @param string $apiKey The API key to test
 * @return bool True if the key is valid, false otherwise
 */
function testApiKey($apiKey) {
    // The URL for a simple API test
    $url = 'https://otx.alienvault.com/api/v1/user/me';
    
    // Set up cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-OTX-API-KEY: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check if the response is successful (200 OK)
    return $httpCode === 200 && !empty($response);
}
?> 
