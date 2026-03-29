<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/mitre_ai_lib.php';

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body) || !isset($body['messages']) || !is_array($body['messages'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit();
}

if (count($body['messages']) > 24) {
    $body['messages'] = array_slice($body['messages'], -24);
}

$cfg = mitre_ai_get_full_config();
if ($cfg === null) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'MITRE AI is not configured. Ask an admin to set the API in Admin.']);
    exit();
}

$systemPrompt = <<<'PROMPT'
You are a cybersecurity assistant for PurpleLab MITRE ATT&CK testing. The user describes an attack scenario (e.g. infostealer, ransomware, lateral movement). Your job is to suggest relevant MITRE ATT&CK techniques that would be interesting to test or detect for that scenario.

Rules:
- Use only plausible MITRE ATT&CK technique IDs: format T followed by 4 digits, optionally a dot and 3 digits for sub-techniques (e.g. T1059.001, T1486).
- Explain briefly why each technique matters for the scenario.
- Prefer techniques that map to executable Atomic tests where possible.
- Be concise but structured (short paragraphs or bullet points).
PROMPT;

$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach ($body['messages'] as $m) {
    if (!is_array($m) || empty($m['role']) || !isset($m['content'])) {
        continue;
    }
    $role = $m['role'];
    if (!in_array($role, ['user', 'assistant'], true)) {
        continue;
    }
    $content = is_string($m['content']) ? $m['content'] : '';
    if ($content === '') {
        continue;
    }
    if (strlen($content) > 12000) {
        $content = substr($content, 0, 12000);
    }
    $messages[] = ['role' => $role, 'content' => $content];
}

if (count($messages) < 2) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No user messages']);
    exit();
}

$provider = $cfg['provider'] ?? 'openai';
if ($provider === 'gemini') {
    $geminiContents = [];
    foreach ($messages as $m) {
        $role = $m['role'] === 'assistant' ? 'model' : 'user';
        $geminiContents[] = [
            'role' => $role,
            'parts' => [['text' => $m['content']]],
        ];
    }
    $url = rtrim($cfg['api_base'], '/') . '/models/' . rawurlencode($cfg['model']) . ':generateContent?key=' . rawurlencode($cfg['api_key']);
    $payload = [
        'contents' => $geminiContents,
        'generationConfig' => [
            'temperature' => 0.35,
            'maxOutputTokens' => 1200,
        ],
    ];
    $headers = ['Content-Type: application/json'];
} else {
    $url = $cfg['api_base'] . '/chat/completions';
    $payload = [
        'model' => $cfg['model'],
        'messages' => $messages,
        'temperature' => 0.35,
    ];
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $cfg['api_key'],
    ];
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 120,
]);
$response = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $err]);
    exit();
}

$data = json_decode($response, true);
if ($code < 200 || $code >= 300) {
    $msg = is_array($data) && isset($data['error']['message'])
        ? $data['error']['message']
        : (is_string($response) ? substr($response, 0, 800) : 'Upstream error');
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'API error: ' . $msg]);
    exit();
}

$text = null;
if ($provider === 'gemini') {
    if (is_array($data) && isset($data['candidates'][0]['content']['parts']) && is_array($data['candidates'][0]['content']['parts'])) {
        $parts = $data['candidates'][0]['content']['parts'];
        $chunks = [];
        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $chunks[] = $part['text'];
            }
        }
        $text = trim(implode("\n", $chunks));
    }
} else {
    if (is_array($data) && isset($data['choices'][0]['message']['content'])) {
        $text = $data['choices'][0]['message']['content'];
    }
}

if ($text === null || $text === '') {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Empty model response']);
    exit();
}

echo json_encode(['ok' => true, 'message' => $text]);
