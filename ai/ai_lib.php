<?php
declare(strict_types=1);

/**
 * AI-hjälpare: laddar config/key.env, config/ai.php och anropar OpenAI-kompatibla slutpunkter.
 */

function skill_ai_key_env_path(): string
{
    return dirname(__DIR__) . '/config/key.env';
}

/**
 * @return array<string, string>
 */
function skill_ai_load_key_env(): array
{
    $path = skill_ai_key_env_path();
    if (!is_file($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    $out   = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m)) {
            continue;
        }
        $out[$m[1]] = trim($m[2], " \t\"'");
    }
    return $out;
}

/**
 * @return array<string, mixed>
 */
function skill_ai_config(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $f = dirname(__DIR__) . '/config/ai.php';
    $defaults = [
        'openai_base'      => 'https://api.openai.com/v1',
        'ollama_base'      => 'http://127.0.0.1:11434/v1',
        'lmstudio_base'    => 'http://127.0.0.1:1234/v1',
        'default_provider' => 'ollama',
        'models'           => [
            'openai'   => 'gpt-4o-mini',
            'ollama'   => 'llama3.2',
            'lmstudio' => '',
        ],
        'system_prompt'    => '',
    ];
    $fileCfg = is_file($f) ? (array)(require $f) : [];
    $c       = array_merge($defaults, $fileCfg);

    // key.env / miljö kan översätta bas-URL:er (t.ex. Ollama på annan värd än PHP)
    $env = skill_ai_load_key_env();
    $urlKeys = [
        'OPENAI_BASE'   => 'openai_base',
        'OLLAMA_BASE'   => 'ollama_base',
        'LMSTUDIO_BASE' => 'lmstudio_base',
    ];
    foreach ($urlKeys as $envKey => $cfgKey) {
        $v = $env[$envKey] ?? '';
        if ($v === '' && function_exists('getenv')) {
            $g = getenv($envKey);
            $v = is_string($g) ? $g : '';
        }
        if ($v !== '') {
            $c[$cfgKey] = rtrim($v, '/');
        }
    }

    return $c;
}

/**
 * @param list<array{role: string, content: string}> $messages
 * @return array{ok: bool, content?: string, error?: string}
 */
function skill_ai_chat_completions(string $baseUrl, string $apiKey, string $model, array $messages, float $temperature = 0.7): array
{
    $cfg = skill_ai_config();
    $timeout        = (int)($cfg['curl_timeout_seconds'] ?? 600);
    $connectTimeout = (int)($cfg['curl_connect_timeout_seconds'] ?? 60);
    if ($timeout < 30) {
        $timeout = 30;
    }
    if ($connectTimeout < 5) {
        $connectTimeout = 5;
    }

    $url = rtrim($baseUrl, '/') . '/chat/completions';
    $payload = [
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => $temperature,
    ];
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'error' => 'Kunde inte initiera HTTP-klient.'];
    }
    $headers = ['Content-Type: application/json'];
    if ($apiKey !== '') {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    }
    $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($jsonBody === false) {
        return ['ok' => false, 'error' => 'Kunde inte serialisera begäran.'];
    }
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonBody,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
    ]);
    $response = curl_exec($ch);
    $code     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr     = curl_error($ch);
    curl_close($ch);
    if ($response === false) {
        return ['ok' => false, 'error' => $cerr !== '' ? $cerr : 'Nätverksfel mot AI-tjänsten.'];
    }
    try {
        $json = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Ogiltigt JSON-svar: ' . substr($response, 0, 200)];
    }
    if ($code >= 400) {
        $msg = $json['error']['message'] ?? $json['error'] ?? $response;
        if (is_array($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
        }
        return ['ok' => false, 'error' => is_string($msg) ? $msg : 'HTTP ' . $code];
    }
    $content = $json['choices'][0]['message']['content'] ?? null;
    if (!is_string($content) || $content === '') {
        return ['ok' => false, 'error' => 'Tomt svar från modellen.'];
    }
    return ['ok' => true, 'content' => $content];
}
