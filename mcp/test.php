<?php
declare(strict_types=1);

function build_mcp_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/mcp/test.php'), '/\\');
    return $scheme . '://' . $host . $base . '/index.php';
}

function send_jsonrpc(string $url, array $payload): array {
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $json,
            'timeout' => 20,
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) {
        return ['error' => 'Kunde inte nå MCP-servern på ' . $url];
    }
    $decoded = json_decode($resp, true);
    return is_array($decoded) ? $decoded : ['raw' => $resp];
}

$endpoint = build_mcp_url();
$action = $_POST['action'] ?? '';
$readFile = trim((string)($_POST['read_file'] ?? ''));
$searchQuery = trim((string)($_POST['search_query'] ?? ''));

$requestPayload = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'initialize') {
        $requestPayload = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => new stdClass()];
    } elseif ($action === 'tools_list') {
        $requestPayload = ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => new stdClass()];
    } elseif ($action === 'ping') {
        $requestPayload = ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'ping', 'params' => new stdClass()];
    } elseif ($action === 'list_skills') {
        $requestPayload = [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => ['name' => 'list_skills', 'arguments' => new stdClass()],
        ];
    } elseif ($action === 'read_skill') {
        $requestPayload = [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => ['name' => 'read_skill', 'arguments' => ['file' => $readFile]],
        ];
    } elseif ($action === 'search_skills') {
        $requestPayload = [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => ['name' => 'search_skills', 'arguments' => ['query' => $searchQuery]],
        ];
    }

    if ($requestPayload !== null) {
        $result = send_jsonrpc($endpoint, $requestPayload);
    }
}
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>MCP Test</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; color: #222; }
    .card { border: 1px solid #ddd; border-radius: 8px; padding: 14px; margin-bottom: 14px; }
    .row { display: flex; gap: 8px; flex-wrap: wrap; }
    input[type=text] { padding: 8px; min-width: 280px; }
    button { padding: 8px 12px; cursor: pointer; }
    pre { background: #f7f7f7; border: 1px solid #eee; border-radius: 6px; padding: 12px; overflow: auto; }
    .muted { color: #666; font-size: 0.9rem; }
  </style>
</head>
<body>
  <h1>MCP Testpanel</h1>
  <p class="muted">Endpoint: <code><?= htmlspecialchars($endpoint, ENT_QUOTES) ?></code></p>

  <div class="card">
    <div class="row">
      <form method="post"><input type="hidden" name="action" value="initialize"><button type="submit">initialize</button></form>
      <form method="post"><input type="hidden" name="action" value="tools_list"><button type="submit">tools/list</button></form>
      <form method="post"><input type="hidden" name="action" value="ping"><button type="submit">ping</button></form>
      <form method="post"><input type="hidden" name="action" value="list_skills"><button type="submit">tools/call: list_skills</button></form>
    </div>
  </div>

  <div class="card">
    <form method="post" class="row">
      <input type="hidden" name="action" value="read_skill">
      <input type="text" name="read_file" placeholder="exempel: my-skill.skill" value="<?= htmlspecialchars($readFile, ENT_QUOTES) ?>">
      <button type="submit">tools/call: read_skill</button>
    </form>
  </div>

  <div class="card">
    <form method="post" class="row">
      <input type="hidden" name="action" value="search_skills">
      <input type="text" name="search_query" placeholder="sökterm..." value="<?= htmlspecialchars($searchQuery, ENT_QUOTES) ?>">
      <button type="submit">tools/call: search_skills</button>
    </form>
  </div>

  <?php if ($requestPayload !== null): ?>
    <div class="card">
      <h3>Request</h3>
      <pre><?= htmlspecialchars((string)json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES) ?></pre>
      <h3>Response</h3>
      <pre><?= htmlspecialchars((string)json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES) ?></pre>
    </div>
  <?php endif; ?>
</body>
</html>
