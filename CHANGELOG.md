# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-03-26

### Added
- MCP server endpoint at `mcp/index.php` with JSON-RPC methods:
  - `initialize`
  - `tools/list`
  - `tools/call`
  - `ping`
- MCP tools for skill access:
  - `list_skills`
  - `read_skill`
  - `search_skills`
- MCP web test panel at `mcp/test.php`
- AI-focused MCP documentation in `MCP.md`
- Favicon support across app pages

### Changed
- Dashboard (`/`) is now publicly viewable for skill overview and `View`
- Action visibility is auth-aware:
  - Guests: view + download
  - Authenticated users: edit/delete/upload/new
- Viewer (`/view/`) now hides Edit buttons for guests and shows Login instead
- Download UI updated to one dropdown button with format choices:
  - `.skill` (default)
  - `.zip`
- `download.php` now supports `ext=skill|zip` for download filename extension
- Editor (`/edit/`) now supports archive file operations:
  - rename/move
  - delete
  - add file improvements
- Template insertion for `SKILL.md` now includes YAML frontmatter with metadata fields

### Security
- Upload now accepts both `.skill` and `.zip`, but enforces archive content rules:
  - only `.md` and `.txt` entries are allowed
  - `.zip` uploads are converted into `.skill` files in `/content`
  - invalid archive uploads are rejected

## [1.0.0] - 2026-03-22

### Added
- Initial release of Claude Skill Manager
- Complete skill library management with searchable dashboard
- Monaco Editor integration with syntax highlighting
- Live preview with Mermaid diagram support
- Secure authentication system with session management
- Responsive design for desktop and mobile
- Tag-based organization and filtering
- Import/export functionality for .skill files  
- Public skill viewing without authentication
- File tree navigation within skill archives
- Template system for creating new skills
- Comprehensive help documentation

### Features
- **Dashboard**: Searchable table of all skills with metadata
- **Editor**: Full VS Code editor experience with multi-file support
- **Viewer**: Public skill viewing with rendered Markdown and Mermaid
- **Authentication**: Password-protected editing with configurable sessions
- **File Management**: Upload, download, edit, and delete skill files
- **UI/UX**: Clean, professional interface with intuitive navigation

### Technical
- PHP 8.1+ compatibility with ZipArchive support
- Works with Apache, Nginx, and PHP built-in server
- Secure configuration with protected directories
- Clean codebase with proper separation of concerns
- Comprehensive error handling and validation