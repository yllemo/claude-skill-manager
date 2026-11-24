# MCP Guide for AI Clients

This document is an AI-focused companion to `README.md`.  
It explains how to use this project as a lightweight MCP-compatible server for reading skills from disk.

## Purpose

The project exposes a simple JSON-RPC endpoint at:

- `POST /mcp/index.php`

The endpoint provides tools to:

- list available `.skill` archives
- read one skill (metadata + text files)
- search skills by metadata text

This is intended for AI assistants/agents that need structured access to local skill content.

## Important Paths

- `content/` - stores `.skill` files
- `mcp/index.php` - MCP 2026-07-28 Streamable HTTP server
- `mcp/test.php` - manual test panel for MCP calls
- `_common.php` - shared helpers for ZIP and metadata handling
- `skill-intro.md` - human-facing explanation of skill format

## Protocol Overview

The server implements MCP **2026-07-28** over stateless Streamable HTTP, with JSON responses.
For 2026-07-28 there is no initialize handshake or MCP session. Every request includes protocol version and
client capabilities in params._meta. Each POST must include:

- Content-Type: application/json
- Accept: application/json, text/event-stream
- MCP-Protocol-Version: 2026-07-28
- Mcp-Method: the JSON-RPC method
- Mcp-Name: the tool name for tools/call

Origin, when present, must match the application's origin. Browser calls use the current login
cookie; other clients use guest access and see only skills allowed by access/visibility settings.
GET and DELETE return 405. Notifications receive 202 with no response body.
Unsupported versions return HTTP 400 and error -32022 with supported versions.
Header/body mismatches return HTTP 400 and error -32020. Unknown methods return HTTP 404.
Successful results include resultType: complete and server identity in result._meta.
Discovery and tool lists include ttlMs: 0 and cacheScope: private.

The same endpoint also supports **2025-03-26**, **2025-06-18** and **2025-11-25**.
For those versions, first send initialize with protocolVersion, capabilities: {} and
clientInfo: {name, version}. The response contains the negotiated protocolVersion,
serverInfo and capabilities. Then send notifications/initialized (HTTP 202) and use
the negotiated MCP-Protocol-Version header for subsequent calls. No Mcp-Method,
Mcp-Name or per-request client metadata is required for these versions. Ping returns {}.
The server does not issue MCP session IDs; sessions are optional in Streamable HTTP.
Unknown initialize versions negotiate 2025-11-25; unknown versions on subsequent calls
return -32022. Requests without a version default to 2025-03-26 for compatibility.
The 2024-11-05 HTTP+SSE transport is not implemented.

Use /mcp/index.php or /mcp/ to avoid directory redirects. Apache can also use the root
.htaccess rule for /mcp; other servers must configure an internal route themselves.
Set MCP_PUBLIC_ORIGIN to the external origin behind a TLS proxy. Forwarded headers
are intentionally not trusted. Configure the proxy/web server's external scheme to
fix any redirects generated before PHP runs.
See the [official specification](https://modelcontextprotocol.io/specification/2026-07-28/basic/versioning).

### Supported methods

- `server/discover`
- `tools/list`
- `tools/call`
- `ping`

### Tool names (`tools/call`)

- `list_skills`
- `read_skill`
- `search_skills`

## Quick JSON-RPC Examples

### 1) Discover the server

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "server/discover",
  "params": {
    "_meta": {
      "io.modelcontextprotocol/protocolVersion": "2026-07-28",
      "io.modelcontextprotocol/clientCapabilities": {},
      "io.modelcontextprotocol/clientInfo": {
        "name": "example-client",
        "version": "1.0.0"
      }
    }
  }
}
```

### 2) List tools

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tools/list",
  "params": {
    "_meta": {
      "io.modelcontextprotocol/protocolVersion": "2026-07-28",
      "io.modelcontextprotocol/clientCapabilities": {},
      "io.modelcontextprotocol/clientInfo": {
        "name": "example-client",
        "version": "1.0.0"
      }
    }
  }
}
```

### 3) Call `list_skills`

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "tools/call",
  "params": {
    "name": "list_skills",
    "arguments": {},
    "_meta": {
      "io.modelcontextprotocol/protocolVersion": "2026-07-28",
      "io.modelcontextprotocol/clientCapabilities": {},
      "io.modelcontextprotocol/clientInfo": {
        "name": "example-client",
        "version": "1.0.0"
      }
    }
  }
}
```

### 4) Call `read_skill`

```json
{
  "jsonrpc": "2.0",
  "id": 4,
  "method": "tools/call",
  "params": {
    "name": "read_skill",
    "arguments": {
      "file": "example.skill"
    },
    "_meta": {
      "io.modelcontextprotocol/protocolVersion": "2026-07-28",
      "io.modelcontextprotocol/clientCapabilities": {},
      "io.modelcontextprotocol/clientInfo": {
        "name": "example-client",
        "version": "1.0.0"
      }
    }
  }
}
```

### 5) Call `search_skills`

```json
{
  "jsonrpc": "2.0",
  "id": 5,
  "method": "tools/call",
  "params": {
    "name": "search_skills",
    "arguments": {
      "query": "markdown"
    },
    "_meta": {
      "io.modelcontextprotocol/protocolVersion": "2026-07-28",
      "io.modelcontextprotocol/clientCapabilities": {},
      "io.modelcontextprotocol/clientInfo": {
        "name": "example-client",
        "version": "1.0.0"
      }
    }
  }
}
```

## Tool Behavior

### `list_skills`

Returns one record per `.skill` file with fields such as:

- `file`
- `title`
- `description`
- `tags`
- `author`
- `version`
- `numFiles`
- `sizeBytes`
- `modifiedTs`

### `read_skill`

Input:

- `file` (required): basename like `my-skill.skill`

Returns:

- skill-level metadata (frontmatter-derived)
- `textFiles[]` containing text entry path, size, and content

Note: response includes text entries only.

### `search_skills`

Input:

- `query` (required): free text

Matches against lowercased concatenation of:

- title
- description
- tags
- author

## Skill File Format

A `.skill` file is a ZIP archive.

Typical structure:

```text
my-skill.skill
├── SKILL.md
├── references/guide.md
└── templates/example.txt
```

### `SKILL.md` conventions

Top section usually contains YAML frontmatter:

```yaml
---
name: my-skill
title: My Skill
description: What this skill does
author: Your Name
version: 1.0.0
tags: php, mcp, docs
---
```

Then Markdown body with instructions and examples.

## Upload Validation Rules (Current Project Behavior)

When uploading `.skill` or `.zip` through the web UI:

- only `.md` and `.txt` entries are allowed
- a `.zip` upload is converted to a `.skill` file in `content/`

This protects the skill library from unexpected binary/script content via upload.

## Testing

Use:

- `/mcp/test.php`

It provides buttons/forms for `server/discover`, `tools/list`, `ping`, and each tool call, plus automated protocol tests.

## Operational Notes for AI Agents

- Always pass file basenames (not absolute paths) to `read_skill`.
- Expect tool responses in `result.content[0].text` as JSON string payloads.
- Handle error responses via JSON-RPC `error` or tool result with `isError: true`.
- Do not assume write operations exist; current MCP endpoint is read-oriented.

## Future Extensions (Suggested)

- Add authenticated write tools:
  - create/update skill
  - delete skill
  - rename skill
- Add MCP resources (`resources/list`, `resources/read`)
- Add pagination/filter params to `list_skills`
- Add full-text search inside file contents, not only metadata

## Testing

Open /mcp/test.php to call discovery, ping and the three tools using your current browser login.
The **Kör protokolltester** button checks success responses and protocol errors.
Run HTTP regression checks against a running PHP server with:

~~~sh
node mcp/check.mjs http://127.0.0.1:8000/mcp/index.php
~~~

PHP needs the ZIP extension to read skill archives. Tests of archive content should use
an existing skill file and verify both guest visibility and logged-in access.
