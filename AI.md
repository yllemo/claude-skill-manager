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
- `mcp/index.php` - MCP-like JSON-RPC server
- `mcp/test.php` - manual test panel for MCP calls
- `_common.php` - shared helpers for ZIP and metadata handling
- `skill-intro.md` - human-facing explanation of skill format

## Protocol Overview

The MCP server currently accepts JSON-RPC 2.0 style calls.

### Supported methods

- `initialize`
- `tools/list`
- `tools/call`
- `ping`

### Tool names (`tools/call`)

- `list_skills`
- `read_skill`
- `search_skills`

## Quick JSON-RPC Examples

### 1) Initialize

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "initialize",
  "params": {}
}
```

### 2) List tools

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tools/list",
  "params": {}
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
    "arguments": {}
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

It provides buttons/forms for `initialize`, `tools/list`, `ping`, and each tool call.

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
