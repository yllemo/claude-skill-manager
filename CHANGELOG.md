# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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