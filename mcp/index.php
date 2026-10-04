<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../_common.php';

require_once __DIR__ . '/protocol.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// Browser requests must originate from this application's origin.
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $expected = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    if (strcasecmp($_SERVER['HTTP_ORIGIN'], $expected) !== 0) {
        mcp_send_error(null, -32600, 'Forbidden origin', 403);
    }
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    mcp_send_error(null, -32600, 'Use POST for MCP requests', 405);
}
if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    mcp_send_error(null, -32600, 'Content-Type must be application/json', 415);
}
$accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
if (!str_contains($accept, 'application/json') || !str_contains($accept, 'text/event-stream')) {
    mcp_send_error(null, -32600, 'Accept must include application/json and text/event-stream', 406);
}
$req = json_decode((string)file_get_contents('php://input'));
if (json_last_error() !== JSON_ERROR_NONE) {
    mcp_send_error(null, -32700, 'Parse error: invalid JSON', 400);
}
$id = ($req instanceof stdClass && (is_string($req->id ?? null) || is_int($req->id ?? null))) ? $req->id : null;
$error = mcp_validate_request($req, [
    'version' => $_SERVER['HTTP_MCP_PROTOCOL_VERSION'] ?? null,
    'method' => $_SERVER['HTTP_MCP_METHOD'] ?? null,
    'name' => $_SERVER['HTTP_MCP_NAME'] ?? null,
]);
if ($error !== null) {
    mcp_send_error($id, $error['code'], $error['message'], 400, $error['data']);
}
if (!property_exists($req, 'id')) {
    http_response_code(202);
    header_remove('Content-Type');
    exit;
}
$params = $req->params ?? new stdClass();
switch ($req->method) {
    case 'server/discover':
        mcp_send_result($id, [
            'supportedVersions' => [MCP_PROTOCOL_VERSION],
            'capabilities' => ['tools' => new stdClass()],
            'ttlMs' => 0,
            'cacheScope' => 'private',
        ]);
        break;
    case 'tools/list':
        if (isset($params->cursor)) {
            mcp_send_error($id, -32602, 'Pagination cursors are not supported', 400);
        }
        mcp_send_result($id, ['tools' => mcp_tools(), 'ttlMs' => 0, 'cacheScope' => 'private']);
        break;
    case 'tools/call':
        if (!is_string($params->name ?? null)) {
            mcp_send_error($id, -32602, 'Tool name must be a string', 400);
        }
        $args = $params->arguments ?? new stdClass();
        $validation = mcp_validate_tool($params->name, $args);
        if ($validation !== null) {
            mcp_send_result($id, mcp_error_result($validation));
        }
        mcp_send_result($id, mcp_call_tool($params->name, get_object_vars($args)));
        break;
    case 'ping':
        mcp_send_result($id, []);
        break;
    default:
        mcp_send_error($id, -32601, 'Method not found: ' . $req->method, 404);
}

function mcp_tools(): array {
    return [
        [
            'name' => 'list_skills',
            'description' => 'List all available .skill files with metadata.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => new stdClass(),
                'additionalProperties' => false,
            ],
        ],
        [
            'name' => 'read_skill',
            'description' => 'Read metadata and text files for one .skill file.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'file' => ['type' => 'string', 'description' => 'Skill filename, e.g. my-skill.skill'],
                ],
                'required' => ['file'],
                'additionalProperties' => false,
            ],
        ],
        [
            'name' => 'search_skills',
            'description' => 'Search skills by title, description, tags, and author.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string'],
                ],
                'required' => ['query'],
                'additionalProperties' => false,
            ],
        ],
    ];
}

function mcp_call_tool(string $toolName, array $args): array {
    try {
        if ($toolName === 'list_skills') {
            $skills = skill_get_skills_for_user();
            $payload = array_map(static function (array $s): array {
                $meta = $s['meta'] ?? [];
                return [
                    'file' => (string)$s['filename'],
                    'title' => (string)($meta['title'] ?? pathinfo((string)$s['filename'], PATHINFO_FILENAME)),
                    'description' => (string)($meta['description'] ?? ''),
                    'tags' => (string)($meta['tags'] ?? ''),
                    'author' => (string)($meta['author'] ?? ''),
                    'version' => (string)($meta['version'] ?? ''),
                    'visibility' => skill_skill_visibility($meta),
                    'numFiles' => (int)($s['numFiles'] ?? 0),
                    'sizeBytes' => (int)($s['size'] ?? 0),
                    'modifiedTs' => (int)($s['modified'] ?? 0),
                ];
            }, $skills);
            return mcp_text_result($payload);
        }

        if ($toolName === 'read_skill') {
            $file = basename((string)($args['file'] ?? ''));
            $path = validate_file_param($file);
            if (!$path) {
                return mcp_error_result('Skill not found: ' . $file);
            }
            $meta = get_skill_meta($path);
            if (!skill_user_can_view_skill($meta)) {
                return mcp_error_result('Access denied: ' . $file);
            }
            $entries = read_zip_files($path);
            $texts = [];
            foreach ($entries as $name => $entry) {
                if (($entry['type'] ?? '') === 'text') {
                    $texts[] = [
                        'path' => (string)$name,
                        'sizeBytes' => (int)($entry['size'] ?? 0),
                        'content' => (string)($entry['content'] ?? ''),
                    ];
                }
            }
            return mcp_text_result([
                'file' => $file,
                'meta' => $meta,
                'textFiles' => $texts,
            ]);
        }

        if ($toolName === 'search_skills') {
            $query = trim((string)($args['query'] ?? ''));
            if ($query === '') {
                return mcp_error_result('query is required');
            }
            $q = strtolower($query);
            $hits = [];
            foreach (skill_get_skills_for_user() as $s) {
                $meta = $s['meta'] ?? [];
                $title = (string)($meta['title'] ?? pathinfo((string)$s['filename'], PATHINFO_FILENAME));
                $desc = (string)($meta['description'] ?? '');
                $tags = (string)($meta['tags'] ?? '');
                $author = (string)($meta['author'] ?? '');
                $hay = strtolower($title . ' ' . $desc . ' ' . $tags . ' ' . $author);
                if (str_contains($hay, $q)) {
                    $hits[] = [
                        'file' => (string)$s['filename'],
                        'title' => $title,
                        'description' => $desc,
                        'tags' => $tags,
                        'author' => $author,
                    ];
                }
            }
            return mcp_text_result([
                'query' => $query,
                'count' => count($hits),
                'hits' => $hits,
            ]);
        }

        return mcp_error_result('Unknown tool: ' . $toolName);
    } catch (Throwable $e) {
        return mcp_error_result('Tool failed: ' . $e->getMessage());
    }
}

function mcp_text_result(array $payload): array {
    return [
        'content' => [[
            'type' => 'text',
            'text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]],
        'isError' => false,
    ];
}

function mcp_error_result(string $msg): array {
    return [
        'content' => [[
            'type' => 'text',
            'text' => $msg,
        ]],
        'isError' => true,
    ];
}

function mcp_send_result($id, array $result): void {
    $result['resultType'] = 'complete';
    $result['_meta'] = ['io.modelcontextprotocol/serverInfo' => mcp_server_info()];
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mcp_send_error($id, int $code, string $message, int $status = 400, array $data = []): void {
    http_response_code($status);
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'error' => [
            'code' => $code,
            'message' => $message,
            ...($data !== [] ? ['data' => $data] : []),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
