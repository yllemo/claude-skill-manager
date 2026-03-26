<?php
declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

header('Content-Type: application/json; charset=utf-8');

const MCP_PROTOCOL_VERSION = '2024-11-05';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'ok' => true,
        'name' => 'skill-manager-mcp',
        'message' => 'Send JSON-RPC 2.0 POST requests to use MCP methods.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$raw = (string)file_get_contents('php://input');
$req = json_decode($raw, true);

if (!is_array($req)) {
    mcp_send_error(null, -32700, 'Parse error: invalid JSON');
}

$method = (string)($req['method'] ?? '');
$id = $req['id'] ?? null;
$params = is_array($req['params'] ?? null) ? $req['params'] : [];

switch ($method) {
    case 'initialize':
        mcp_send_result($id, [
            'protocolVersion' => MCP_PROTOCOL_VERSION,
            'serverInfo' => [
                'name' => 'skill-manager-mcp',
                'version' => '1.0.0',
            ],
            'capabilities' => [
                'tools' => new stdClass(),
            ],
        ]);
        break;

    case 'tools/list':
        mcp_send_result($id, ['tools' => mcp_tools()]);
        break;

    case 'tools/call':
        $toolName = (string)($params['name'] ?? '');
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        $toolResult = mcp_call_tool($toolName, $args);
        mcp_send_result($id, $toolResult);
        break;

    case 'ping':
        mcp_send_result($id, ['pong' => true]);
        break;

    default:
        mcp_send_error($id, -32601, 'Method not found: ' . $method);
}

function mcp_tools(): array {
    return [
        [
            'name' => 'list_skills',
            'description' => 'List all available .skill files with metadata.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [],
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
            $skills = get_skills();
            $payload = array_map(static function (array $s): array {
                $meta = $s['meta'] ?? [];
                return [
                    'file' => (string)$s['filename'],
                    'title' => (string)($meta['title'] ?? pathinfo((string)$s['filename'], PATHINFO_FILENAME)),
                    'description' => (string)($meta['description'] ?? ''),
                    'tags' => (string)($meta['tags'] ?? ''),
                    'author' => (string)($meta['author'] ?? ''),
                    'version' => (string)($meta['version'] ?? ''),
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
            foreach (get_skills() as $s) {
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
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mcp_send_error($id, int $code, string $message): void {
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
