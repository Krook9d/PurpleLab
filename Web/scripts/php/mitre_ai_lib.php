<?php
/**
 * Shared helpers for MITRE AI assistant (OpenAI-compatible API storage and decryption).
 */

if (!defined('MITRE_AI_DIR')) {
    define('MITRE_AI_DIR', dirname(__DIR__, 2) . '/mitre_ai');
}
define('MITRE_AI_CONFIG', MITRE_AI_DIR . '/config.json');
define('MITRE_AI_ENC_KEY', MITRE_AI_DIR . '/api_key.enc');
define('MITRE_AI_SECRET_FILE', MITRE_AI_DIR . '/.secret_key');

/**
 * @return array{configured:bool,provider?:string,api_base?:string,model?:string,key_preview?:string,last_updated?:string}|null
 */
function mitre_ai_read_meta(): ?array
{
    if (!is_readable(MITRE_AI_CONFIG)) {
        return null;
    }
    $raw = file_get_contents(MITRE_AI_CONFIG);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * @return array{provider:string,api_key:string,api_base:string,model:string}|null
 */
function mitre_ai_get_full_config(): ?array
{
    $meta = mitre_ai_read_meta();
    if (!$meta || empty($meta['configured'])) {
        return null;
    }
    $provider = $meta['provider'] ?? 'openai';
    if (!in_array($provider, ['openai', 'gemini'], true)) {
        return null;
    }
    if (empty($meta['model'])) {
        return null;
    }
    $apiBase = isset($meta['api_base']) ? rtrim((string) $meta['api_base'], '/') : '';
    if ($provider === 'openai' && $apiBase === '') {
        return null;
    }
    if ($provider === 'gemini' && $apiBase === '') {
        $apiBase = 'https://generativelanguage.googleapis.com/v1beta';
    }
    $key = mitre_ai_decrypt_api_key();
    if ($key === null || $key === '') {
        return null;
    }
    return [
        'provider' => $provider,
        'api_key' => $key,
        'api_base' => $apiBase,
        'model' => $meta['model'],
    ];
}

function mitre_ai_decrypt_api_key(): ?string
{
    if (!is_readable(MITRE_AI_ENC_KEY) || !is_readable(MITRE_AI_SECRET_FILE)) {
        return null;
    }
    $bundle = file_get_contents(MITRE_AI_ENC_KEY);
    $secretHex = trim(file_get_contents(MITRE_AI_SECRET_FILE));
    if ($bundle === false || $secretHex === '') {
        return null;
    }
    $parts = explode(':', $bundle, 2);
    if (count($parts) !== 2) {
        return null;
    }
    $iv = base64_decode($parts[0], true);
    $encrypted = $parts[1];
    if ($iv === false || strlen($iv) !== 16) {
        return null;
    }
    $secretKey = @hex2bin($secretHex);
    if ($secretKey === false) {
        return null;
    }
    $plain = openssl_decrypt($encrypted, 'aes-256-cbc', $secretKey, 0, $iv);
    return $plain !== false ? $plain : null;
}

function mitre_ai_encrypt_and_store_key(string $apiKey): bool
{
    if (!is_dir(MITRE_AI_DIR)) {
        if (!mkdir(MITRE_AI_DIR, 0750, true)) {
            return false;
        }
    }
    if (!file_exists(MITRE_AI_SECRET_FILE)) {
        $secretKey = bin2hex(random_bytes(32));
        file_put_contents(MITRE_AI_SECRET_FILE, $secretKey);
        chmod(MITRE_AI_SECRET_FILE, 0600);
    } else {
        $secretKey = trim(file_get_contents(MITRE_AI_SECRET_FILE));
    }
    $bin = @hex2bin($secretKey);
    if ($bin === false) {
        return false;
    }
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($apiKey, 'aes-256-cbc', $bin, 0, $iv);
    if ($encrypted === false) {
        return false;
    }
    $data = base64_encode($iv) . ':' . $encrypted;
    if (file_put_contents(MITRE_AI_ENC_KEY, $data) === false) {
        return false;
    }
    chmod(MITRE_AI_ENC_KEY, 0640);
    return true;
}

/**
 * Quick connectivity test (OpenAI-compatible or Gemini).
 *
 * @return array{ok:bool,message:string}
 */
function mitre_ai_test_connection(string $provider, string $apiBase, string $apiKey, string $model): array
{
    if ($provider === 'gemini') {
        $base = $apiBase !== '' ? rtrim($apiBase, '/') : 'https://generativelanguage.googleapis.com/v1beta';
        $url = $base . '/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => 'Reply with exactly: ok']],
            ]],
            'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 8],
        ];
        $headers = ['Content-Type: application/json'];
    } else {
        $url = rtrim($apiBase, '/') . '/chat/completions';
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => 'Reply with exactly: ok'],
            ],
            'max_tokens' => 8,
            'temperature' => 0,
        ];
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 45,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) {
        return ['ok' => false, 'message' => $err];
    }
    if ($code < 200 || $code >= 300) {
        $snippet = is_string($body) ? substr($body, 0, 500) : '';
        return ['ok' => false, 'message' => 'HTTP ' . $code . ($snippet ? ': ' . $snippet : '')];
    }
    return ['ok' => true, 'message' => 'Connection OK'];
}
