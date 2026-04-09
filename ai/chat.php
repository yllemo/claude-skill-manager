<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/ai_lib.php';

skill_require_auth('../login.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => __('chat.err_post_only')], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    echo json_encode(['ok' => false, 'error' => __('chat.err_empty')], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    echo json_encode(['ok' => false, 'error' => __('chat.err_bad_json')], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_array($body)) {
    echo json_encode(['ok' => false, 'error' => __('chat.err_bad_json')], JSON_UNESCAPED_UNICODE);
    exit;
}

$provider = $body['provider'] ?? '';
if (!in_array($provider, ['openai', 'ollama', 'lmstudio'], true)) {
    echo json_encode(['ok' => false, 'error' => __('chat.err_provider')], JSON_UNESCAPED_UNICODE);
    exit;
}

$messages = $body['messages'] ?? null;
if (!is_array($messages) || $messages === []) {
    echo json_encode(['ok' => false, 'error' => __('chat.err_messages')], JSON_UNESCAPED_UNICODE);
    exit;
}

$norm = [];
foreach ($messages as $m) {
    if (!is_array($m)) {
        continue;
    }
    $role = $m['role'] ?? '';
    $content = $m['content'] ?? '';
    if (!is_string($role) || !is_string($content)) {
        continue;
    }
    if (!in_array($role, ['system', 'user', 'assistant'], true)) {
        continue;
    }
    if (strlen($content) > 400000) {
        echo json_encode(['ok' => false, 'error' => __('chat.err_msg_too_long')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $norm[] = ['role' => $role, 'content' => $content];
}

if ($norm === []) {
    echo json_encode(['ok' => false, 'error' => __('chat.err_no_valid_messages')], JSON_UNESCAPED_UNICODE);
    exit;
}

$cfg = skill_ai_config();
$env = skill_ai_load_key_env();

$baseKey = $provider . '_base';
$base    = rtrim((string)($cfg[$baseKey] ?? ''), '/');
if ($base === '') {
    echo json_encode(['ok' => false, 'error' => __('chat.err_no_base')], JSON_UNESCAPED_UNICODE);
    exit;
}

$model = trim((string)($body['model'] ?? ''));
if ($model === '') {
    $models = $cfg['models'] ?? [];
    $model  = is_array($models) ? trim((string)($models[$provider] ?? '')) : '';
}
if ($model === '') {
    echo json_encode(['ok' => false, 'error' => __('chat.err_no_model')], JSON_UNESCAPED_UNICODE);
    exit;
}

$apiKey = '';
if ($provider === 'openai') {
    $apiKey = $env['OPENAI_API_KEY'] ?? '';
    if ($apiKey === '' && function_exists('getenv')) {
        $g = getenv('OPENAI_API_KEY');
        $apiKey = is_string($g) ? $g : '';
    }
    if ($apiKey === '') {
        echo json_encode(['ok' => false, 'error' => __('chat.err_no_openai_key')], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$temperature = $body['temperature'] ?? 0.7;
$temperature = is_numeric($temperature) ? max(0.0, min(2.0, (float)$temperature)) : 0.7;

try {
    $result = skill_ai_chat_completions($base, $apiKey, $model, $norm, $temperature);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error'] ?? __('chat.err_unknown')], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'content' => $result['content']], JSON_UNESCAPED_UNICODE);
