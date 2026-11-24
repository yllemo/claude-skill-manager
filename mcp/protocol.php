<?php
declare(strict_types=1);

const MCP_PROTOCOL_VERSION = '2026-07-28';
const MCP_SERVER_VERSION = '2.1.0';
const MCP_LEGACY_VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26'];
const MCP_SUPPORTED_VERSIONS = [MCP_PROTOCOL_VERSION, ...MCP_LEGACY_VERSIONS];

function mcp_request_version($req, array $headers): string {
    if (($req->method ?? '') === 'initialize') {
        $requested = $req->params->protocolVersion ?? '';
        return in_array($requested, MCP_LEGACY_VERSIONS, true) ? $requested : MCP_LEGACY_VERSIONS[0];
    }
    return ($headers['version'] ?? '') !== '' ? $headers['version'] : ($req->params->_meta->{'io.modelcontextprotocol/protocolVersion'} ?? '2025-03-26');
}


function mcp_server_info(): array {
    return ['name' => 'skill-manager-mcp', 'version' => MCP_SERVER_VERSION];
}

/** Validate one modern MCP request without coercing JSON types. */
function mcp_validate_request($req, array $headers): ?array {
    $error = static fn(int $code, string $message, array $data = []): array =>
        ['code' => $code, 'message' => $message, 'data' => $data];
    if (!$req instanceof stdClass || ($req->jsonrpc ?? null) !== '2.0'
        || !is_string($req->method ?? null) || $req->method === ''
        || (property_exists($req, 'id') && !is_int($req->id) && !is_string($req->id))
        || (property_exists($req, 'params') && !$req->params instanceof stdClass)) {
        return $error(-32600, 'Invalid JSON-RPC request');
    }
    if ($req->method === 'initialize') {
        $params = $req->params ?? null;
        if (!is_string($params->protocolVersion ?? null)
            || !(($params->capabilities ?? null) instanceof stdClass)
            || !is_string($params->clientInfo->name ?? null)
            || !is_string($params->clientInfo->version ?? null)
            || !property_exists($req, 'id')) {
            return $error(-32602, 'initialize requires protocolVersion, capabilities and clientInfo');
        }
        return null;
    }
    $selected = mcp_request_version($req, $headers);
    if (in_array($selected, MCP_LEGACY_VERSIONS, true)) {
        $bodyVersion = $req->params->_meta->{'io.modelcontextprotocol/protocolVersion'} ?? null;
        if ($bodyVersion !== null && $bodyVersion !== $selected) {
            return $error(-32020, 'MCP-Protocol-Version does not match params._meta protocolVersion');
        }
        // These revisions do not mirror method/name or capabilities in HTTP headers.
        return null;
    }
    if (!in_array($selected, MCP_SUPPORTED_VERSIONS, true)) {
        return $error(-32022, 'Unsupported protocol version', ['supported' => MCP_SUPPORTED_VERSIONS, 'requested' => $selected]);
    }
    if (!property_exists($req, 'id')) {
        return null; // Transport accepts notifications without returning a JSON-RPC response.
    }
    $meta = $req->params->_meta ?? null;
    $version = $meta->{'io.modelcontextprotocol/protocolVersion'} ?? null;
    if (!is_string($version) || ($headers['version'] ?? null) !== $version
        || ($headers['method'] ?? null) !== $req->method) {
        return $error(-32020, 'MCP-Protocol-Version and Mcp-Method must match params._meta protocolVersion and method (2026-07-28)' );
    }
    if ($req->method === 'tools/call') {
        $name = $headers['name'] ?? null;
        if (is_string($name) && str_starts_with($name, '=?base64?') && str_ends_with($name, '?=')) {
            $name = base64_decode(substr($name, 9, -2), true);
        }
        if (!is_string($name) || !is_string($req->params->name ?? null) || $name !== $req->params->name) {
            return $error(-32020, 'Missing or mismatched Mcp-Name header');
        }
    }
    if ($version !== MCP_PROTOCOL_VERSION) {
        return $error(-32022, 'Unsupported protocol version', ['supported' => MCP_SUPPORTED_VERSIONS, 'requested' => $version]);
    }
    if (!($meta instanceof stdClass) || !(($meta->{'io.modelcontextprotocol/clientCapabilities'} ?? null) instanceof stdClass)) {
        return $error(-32602, 'Client capabilities must be an object in params._meta');
    }
    return null;
}

function mcp_validate_tool(string $name, $args): ?string {
    $fields = ['list_skills' => [], 'read_skill' => ['file'], 'search_skills' => ['query']];
    if (!array_key_exists($name, $fields)) {
        return 'Unknown tool: ' . $name;
    }
    if (!$args instanceof stdClass) {
        return 'Tool arguments must be an object';
    }
    foreach ($fields[$name] as $field) {
        if (!is_string($args->$field ?? null) || trim($args->$field) === '') {
            return $field . ' must be a non-empty string';
        }
    }
    foreach (array_keys(get_object_vars($args)) as $field) {
        if (!in_array($field, $fields[$name], true)) {
            return 'Unexpected argument: ' . $field;
        }
    }
    return null;
}
