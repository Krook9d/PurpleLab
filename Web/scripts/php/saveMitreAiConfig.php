<?php
session_start();

if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@local.com') {
    header('Location: /connexion.html');
    exit();
}

require_once __DIR__ . '/mitre_ai_lib.php';

if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (file_exists(MITRE_AI_ENC_KEY)) {
        unlink(MITRE_AI_ENC_KEY);
    }
    $meta = [
        'configured' => false,
        'last_updated' => date('Y-m-d H:i:s'),
    ];
    file_put_contents(MITRE_AI_CONFIG, json_encode($meta, JSON_PRETTY_PRINT));
    $_SESSION['mitre_ai_deleted'] = true;
    header('Location: /admin.php');
    exit();
}

$provider = isset($_POST['provider']) ? trim($_POST['provider']) : 'openai';
$apiBase = isset($_POST['api_base']) ? trim($_POST['api_base']) : '';
$model = isset($_POST['model']) ? trim($_POST['model']) : '';
$apiKey = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';

if (!in_array($provider, ['openai', 'gemini'], true)) {
    $_SESSION['mitre_ai_error'] = 'Invalid provider.';
    header('Location: /admin.php');
    exit();
}

if (($provider === 'openai' && $apiBase === '') || $model === '') {
    $_SESSION['mitre_ai_error'] = 'Provider, model name and API base URL (for OpenAI-compatible) are required.';
    header('Location: /admin.php');
    exit();
}

if ($provider === 'gemini' && $apiBase === '') {
    $apiBase = 'https://generativelanguage.googleapis.com/v1beta';
}

$existing = mitre_ai_read_meta();
$hadKey = $existing && !empty($existing['configured']) && file_exists(MITRE_AI_ENC_KEY);

if ($apiKey === '' && $hadKey) {
    $apiKey = mitre_ai_decrypt_api_key();
    if ($apiKey === null || $apiKey === '') {
        $_SESSION['mitre_ai_error'] = 'Stored API key could not be read. Enter a new key.';
        header('Location: /admin.php');
        exit();
    }
} elseif ($apiKey === '') {
    $_SESSION['mitre_ai_error'] = 'API key is required.';
    header('Location: /admin.php');
    exit();
}

$test = mitre_ai_test_connection($provider, $apiBase, $apiKey, $model);
if (!$test['ok']) {
    $_SESSION['mitre_ai_error'] = 'Connection test failed: ' . $test['message'];
    header('Location: /admin.php');
    exit();
}

if (!mitre_ai_encrypt_and_store_key($apiKey)) {
    $_SESSION['mitre_ai_error'] = 'Could not save encrypted API key (check directory permissions).';
    header('Location: /admin.php');
    exit();
}

$keyPreview = strlen($apiKey) > 8
    ? substr($apiKey, 0, 4) . '****' . substr($apiKey, -4)
    : '****';

$configData = [
    'configured' => true,
    'provider' => $provider,
    'api_base' => rtrim($apiBase, '/'),
    'model' => $model,
    'key_preview' => $keyPreview,
    'last_updated' => date('Y-m-d H:i:s'),
];
file_put_contents(MITRE_AI_CONFIG, json_encode($configData, JSON_PRETTY_PRINT));

$_SESSION['mitre_ai_saved'] = true;
header('Location: /admin.php');
exit();
