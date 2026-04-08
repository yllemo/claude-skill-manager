# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-04-05

### Added
- AI-powered editing capabilities with comprehensive AI integration
- Support for OpenAI, Ollama, and LM Studio providers
- Interactive AI chat interface for skill creation and editing
- Configurable system prompts and model selection
- Local AI support for privacy-focused workflows
- Enhanced file management with smart templates
- New `download_content.php` for bulk content download
- Improved mobile responsiveness with hamburger menu
- Print stylesheet on `view/index.php`: browser print/PDF uses only the main content area (markdown body), hiding app chrome (header, sidebar, toolbar, footer, Mermaid overlay); dark theme variables are lightened for readable print output

### Fixed
- Mermaid fullscreen **Passa in** (fit): scales from rendered SVG size in pixels (with `getBBox`/`getScreenCTM` fallback) and removes the old max-scale cap so small diagrams fill the viewport instead of staying tiny and off-center

### Enhanced
- YAML frontmatter: block scalars (`>` / `>-` folded, `|` / `|-` literal) for multi-line fields (e.g. `description`), list form for `tags`, and client-side `parseFM` aligned with `parse_frontmatter` in `_common.php`
- Skill description and frontmatter value cells use `white-space: pre-wrap` where needed for multi-line display
- Enhanced ZIP entry path normalization and handling
- Improved editor UI with seamless switching between Monaco and AI modes
- Better security with API key management through environment files
- Enhanced documentation with comprehensive setup guides

### Security
- Added `config/key.env.example` for secure API key storage
- Enhanced gitignore protection for sensitive files
- Improved path sanitization for ZIP entries

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