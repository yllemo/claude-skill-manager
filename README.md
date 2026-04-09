# Claude Skill Manager

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Monaco Editor](https://img.shields.io/badge/Editor-Monaco-0078d4?logo=visual-studio-code&logoColor=white)](https://microsoft.github.io/monaco-editor/)

A professional PHP-based web application for creating, editing, and managing `.skill` files — ZIP archives containing Markdown instructions that describe reusable workflows for Claude AI.

![Skill Manager Screenshot](screenshot.png)

## 🚀 Latest Updates

- 🌐 **UI language (i18n)** — Swedish and English interface via `config/lang.php` (default: Swedish); optional overrides and support for additional language codes
- 🔍 **Enhanced Mermaid Diagrams** — Interactive fullscreen viewing with pan/zoom, text selection, and diagram source code access
- 📄 **Improved YAML Support** — Enhanced frontmatter parsing with multi-line description support using YAML block scalars
- 🖨️ **Print-Friendly Styles** — Optimized printing layouts with clean, professional output
- 🤖 **AI-Powered Editing** — Complete AI integration with OpenAI, Ollama, and LM Studio support
- 💬 **Interactive AI Chat** — Real-time AI assistance for skill creation and editing with configurable system prompts
- ⚡ **Dual Editing Modes** — Seamlessly switch between Monaco editor and AI-assisted editing modes
- 🏗️ **Smart Templates** — Enhanced file structure templates with predefined skill directory organization
- 🔧 **Enhanced Configuration** — Comprehensive AI provider settings with secure API key management
- 📱 **Mobile Responsive** — Improved mobile navigation with hamburger menu and responsive design
- 🔐 **Security Enhanced** — Secure API key storage via `key.env` with comprehensive gitignore protection
- 📥 **Bulk Operations** — New bulk content download functionality for complete archive management

## ✨ Features

- 🤖 **AI-Powered Editing** — Chat with AI assistants to create and refine skills using OpenAI, Ollama, or LM Studio
- 🗂️ **Skill Library Management** — Upload, organize, and manage .skill files with searchable metadata
- ✏️ **Dual Editor Experience** — Choose between Monaco Editor (VS Code-style) or AI-assisted editing
- 👁️ **Live Preview** — Real-time Markdown rendering with enhanced Mermaid diagram support and fullscreen viewing
- 🖱️ **Interactive Diagrams** — Pan, zoom, and select text in Mermaid diagrams with dedicated fullscreen mode
- 🔐 **Secure Authentication** — Password-protected editing with session management
- 📱 **Responsive Design** — Works seamlessly across desktop and mobile devices with print-friendly styles
- 🏷️ **Tag System** — Organize skills with tags and advanced filtering
- 📥 **Import/Export** — Upload existing .skill files or download for backup
- 🌐 **Public Viewing** — Share skills publicly while keeping editing secure
- ⚡ **Performance Optimized** — Fast loading and efficient file handling
- 🧩 **MCP Integration** — JSON-RPC endpoint for AI client integration
- 🌐 **Localization** — Built-in Swedish (`sv`) and English (`en`); switch UI language in `config/lang.php`

## 💡 Why Use Claude Skill Manager?

- **Professional Workflow**: Create and maintain reusable Claude AI skills with a proper development environment
- **Team Collaboration**: Share skills publicly while keeping editing secure with authentication
- **Version Control Ready**: Integrates well with Git workflows for backing up your skill library
- **No Dependencies**: Self-contained PHP application that runs anywhere PHP is supported
- **Production Ready**: Built with security best practices and professional code standards

## 📱 Project Structure

```
skill/
├── index.php           # Dashboard — searchable skill library with upload
├── login.php           # Authentication page  
├── logout.php          # Logout handler
├── download.php        # Serves downloads as .skill or .zip (filename extension option)
├── download_content.php # Bulk content download (requires authentication)
├── favicon.ico         # Custom favicon for the application
├── _common.php         # Shared functions, CSS and helpers
├── _lang.php           # UI translations (built-in sv/en) and __( ) helper
├── _auth.php           # Session authentication
├── AI.md               # AI functionality documentation
├── MCP.md              # AI-focused MCP documentation
├── config/
│   ├── config.php      # Password and settings
│   ├── lang.php        # UI locale (sv/en/…) and optional string overrides
│   ├── ai.php          # AI configuration (providers, models, system prompts)
│   ├── key.env.example # Template for API keys and environment variables
│   └── .htaccess       # Blocks direct HTTP access to /config/
├── ai/
│   ├── index.php       # AI-powered skill editor
│   ├── chat.php        # AI chat API endpoint
│   └── ai_lib.php      # AI integration library
├── mcp/
│   ├── index.php       # MCP JSON-RPC endpoint
│   └── test.php        # MCP web test panel
├── view/
│   └── index.php       # Skill viewer — file tree, render markdown
├── edit/
│   └── index.php       # Create/edit skills — Monaco editor
├── content/            # Storage for .skill files (web server writable)
└── skill-intro.md      # Help text about .skill format (shown via ? button)
```

## 🖥️ Application Pages

### 🏠 Dashboard (`/`)
**Public overview (no login required).**
- Searchable and sortable table of all `.skill` files
- Filter by tags via dropdown or click on tag in list
- Columns: title, description, tags, author, file count, size, modified
- Upload (authenticated): accepts `.skill` and `.zip`
- Upload validation: archives may only contain `.md` and `.txt`
- `.zip` upload conversion: automatically creates `.skill` in `/content`
- Actions by auth state:
  - Guest: View, Download
  - Authenticated: View, Download, Edit, Delete
- Download button includes dropdown format choice (`.skill` or `.zip`)

### 👁️ Skill Viewer (`/view/?file=name.skill`)
**Public access — no authentication required.**
- File tree sidebar showing all files in the ZIP archive
- Renders Markdown via [marked.js](https://marked.js.org/) with enhanced [Mermaid](https://mermaid.js.org/) diagram support
- **Interactive Mermaid diagrams** — Fullscreen viewing with pan/zoom, text selection in SVG elements
- Sidebar shows frontmatter metadata, tags, and file info with support for multi-line descriptions
- Toggle between rendered view and raw text
- Copy button for file contents
- Edit button shown only when authenticated (guests see Login button instead)
- Download button includes format dropdown (`.skill` / `.zip`)
- **Print-friendly** — Optimized printing with clean layout (hides navigation, headers, etc.)

### ✏️ Skill Editor (`/edit/?file=name.skill` or `/edit/` for new)
**Requires authentication.**
- [Monaco Editor](https://microsoft.github.io/monaco-editor/) (VS Code's editor) with syntax highlighting per file type
- File tree sidebar — click to switch files, each file has its own undo/redo
- Live preview with Mermaid diagram support
- Add new files to archive via `+ File` button with smart structure templates
- Rename/move files inside archive
- Delete files inside archive
- Binary files (images etc.) preserved when saving
- Template button for `SKILL.md` inserts YAML frontmatter with metadata (including `author` and `tags`)
- Switch to AI editing mode button
- Help button (`?`) shows `skill-intro.md` as modal

### 🤖 AI Editor (`/ai/?file=name.skill` or `/ai/` for new)
**Requires authentication.**
- AI-powered skill creation and editing with chat interface
- Support for multiple AI providers: OpenAI, Ollama, and LM Studio
- Configurable system prompts for different editing styles
- File content inclusion in AI prompts for context-aware editing
- Direct file replacement or selective content insertion from AI responses
- Smart markdown extraction from AI responses
- Local AI support (browser-to-localhost) for privacy-focused workflows  
- Seamless switching between AI and Monaco editor modes
- All traditional editing features (file management, templates, etc.)
- AI provider settings with model selection and temperature control

### 🤖 MCP Endpoint (`/mcp/index.php`)
**Public read endpoint for AI clients (JSON-RPC style).**
- Methods: `initialize`, `tools/list`, `tools/call`, `ping`
- Tools:
  - `list_skills`
  - `read_skill`
  - `search_skills`
- See [`MCP.md`](MCP.md) for payload examples and integration details

## 📦 .skill File Format

A `.skill` file is a **ZIP archive** with a defined folder structure:

```
my-skill/
├── SKILL.md            # Required — main file with frontmatter + instructions
├── scripts/            # Optional — executable code, e.g. Python or shell 
├── references/         # Optional — reference documents, style guides, specs
└── templates/          # Optional — output templates
```

### SKILL.md — Frontmatter Structure

```markdown
---
name: my-skill
title: My Skill  
description: >
  Multi-line descriptions are supported using YAML block scalars.
  This allows for detailed explanations that span multiple lines
  while maintaining proper formatting.
author: Your Name
version: 1.0
tags: php, web, api
location: /optional/path/reference
---

# My Skill

## Purpose
...

## Instructions
1. Step one
2. Step two
```

**Enhanced YAML Support**: The frontmatter parser now supports YAML block scalars (`>` and `|`) for multi-line descriptions, allowing for better documentation formatting.

## 🚀 Quick Start

### Prerequisites
- **PHP 8.1+** with `ZipArchive` extension enabled
- **Web server** (Apache, Nginx, or PHP built-in server for development)
- **Write permissions** on the `/content/` directory for skill storage

### Installation Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/yllemo/claude-skill-manager.git
   cd claude-skill-manager
   ```

2. **Set up permissions**
   ```bash
   # Make content directory writable
   chmod 755 content/
   # Ensure config directory is protected (optional, .htaccess handles this)
   chmod 750 config/
   ```

3. **Configure your password and AI settings**
   ```bash
   # Copy the configuration template
   cp config/config.php.example config/config.php
   ```
   Edit `config/config.php` and update the password:
   ```php
   'password' => 'your-secure-password',
   ```

   **Optional: Configure AI providers**
   ```bash
   # Copy AI environment template for API keys
   cp config/key.env.example config/key.env
   ```
   Edit `config/key.env` for AI features (OpenAI API key, custom AI endpoints):
   ```env
   OPENAI_API_KEY=your-api-key-here
   # OLLAMA_BASE=http://127.0.0.1:11434/v1  # Local Ollama (default)
   # LMSTUDIO_BASE=http://127.0.0.1:1234/v1  # Local LM Studio (default)
   ```

4. **Launch the application**
   ```bash
   # Development server (recommended for local use)
   php -S localhost:8000
   
   # For production: Configure with Apache/Nginx
   ```

5. **Start managing skills!**
   - Browse to http://localhost:8000
   - Login with your configured password
   - Create your first skill by clicking "New Skill"

### ⚡ Quick Test
Upload a sample skill or create a new one to test all features are working correctly.

## 🔧 Configuration Options

Edit `config/config.php` to customize:
- `password`: Login password (supports bcrypt hashing)
- `session_lifetime`: How long login sessions last (default: 1 month)

Edit `config/lang.php` to customize the **UI language** (labels, buttons, errors, etc.):
- **`locale`**: Active interface language. Built-in values: `sv` (Swedish, default), `en` (English). If `config/lang.php` is missing, the app behaves as **`sv`** using strings from `_lang.php`.
- **`strings`**: Optional per-language overrides. Keys match the identifiers in `_lang.php` (for example `index.hdr_overview`, `common.login`). Example:
  ```php
  return [
      'locale' => 'en',
      'strings' => [
          'en' => [
              'index.hdr_overview' => 'Overview',
          ],
      ],
  ];
  ```
- **More languages**: Add another code under `strings` (for example `'de' => [...]`) and set `'locale' => 'de'`. Any key you omit falls back to the Swedish built-ins.

Edit `config/ai.php` to configure AI features:
- `default_provider`: Choose between `openai`, `ollama`, or `lmstudio`
- `models`: Default model names for each provider
- `system_prompt`: Default system prompt for AI editing (overrideable in UI)
- `openai_base`, `ollama_base`, `lmstudio_base`: API endpoints for each provider
- `curl_timeout_seconds`: Timeout for AI requests

Edit `config/key.env` for sensitive configuration:
- `OPENAI_API_KEY`: Your OpenAI API key (required for OpenAI provider)
- `OLLAMA_BASE`: Custom Ollama endpoint (optional, overrides config/ai.php)
- `LMSTUDIO_BASE`: Custom LM Studio endpoint (optional, overrides config/ai.php)
- `app_name`: Application title shown in UI

## 🔒 Security Notes

- **Production deployment**: Use HTTPS and strong passwords
- **File permissions**: Ensure `/content/` is writable but not executable
- **Web server config**: Block direct access to `/config/` directory
- **Password hashing**: Use bcrypt for password storage in production:
  ```bash
  php -r "echo password_hash('your-password', PASSWORD_BCRYPT);"
  ```

## 📖 Usage

1. **Create a new skill:** Click "New Skill" or visit `/edit/`
2. **Edit existing skills:** Click "Edit" next to any skill in the dashboard
3. **View skills:** Skills can be viewed publicly at `/view/?file=skillname.skill`
4. **Upload skills:** Drag and drop `.skill` files onto the dashboard
5. **Organize with tags:** Use frontmatter tags for easy filtering and searching

## 🔧 Troubleshooting

**Common Issues:**
- **"Cannot write to content directory"**: Ensure `/content/` has write permissions (`chmod 755 content/`)
- **"ZipArchive not found"**: Install PHP zip extension (`php-zip` package)
- **Login not working**: Verify `config/config.php` exists and password is set correctly
- **Styles not loading**: Check that all files were uploaded and web server can serve static files

**Need Help?** Open an issue on GitHub with your PHP version and error details.

## 🤝 Contributing

We welcome contributions from the community! Whether you're fixing bugs, adding features, or improving documentation, your help makes this project better for everyone.

### How to Contribute

1. **Fork the project** on GitHub
2. **Create your feature branch** (`git checkout -b feature/amazing-feature`)
3. **Make your changes** and test thoroughly
4. **Commit your changes** (`git commit -m 'Add some amazing feature'`)
5. **Push to your branch** (`git push origin feature/amazing-feature`)
6. **Open a Pull Request** with a clear description

### Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/claude-skill-manager.git
cd claude-skill-manager

# Set up for development
cp config/config.php.example config/config.php
php -S localhost:8000

# Make your changes and test!
```

### What We Need Help With

- 🐛 Bug fixes and optimizations
- 🎨 UI/UX improvements  
- 📚 Documentation updates
- 🧪 Test coverage
- 🌐 Additional locales and translation polish (strings live in `_lang.php` + `config/lang.php`)
- ♿ Accessibility improvements

Please read our [Contributing Guide](CONTRIBUTING.md) for detailed guidelines.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- [Monaco Editor](https://microsoft.github.io/monaco-editor/) for the excellent code editing experience
- [marked.js](https://marked.js.org/) for Markdown rendering
- [Mermaid](https://mermaid.js.org/) for diagram support
- The Claude AI team for making skills an amazing feature

## 📊 Project Status

This is an **active project** currently in production use. We're continuously improving the codebase and adding new features based on user feedback.

**Current Status:** Stable ✅  
**Version:** 1.0+  
**Maintenance:** Active development  

## 🗺️ Roadmap

- [ ] **API Integration** — REST API for programmatic skill management
- [ ] **Bulk Operations** — Multi-select actions for managing multiple skills
- [ ] **Better Search** — Full-text search within skill contents
- [ ] **Themes** — Light/dark mode and custom themes
- [ ] **Backup/Restore** — Automated backup system
- [ ] **Collaboration** — Multi-user support with permissions

## 🔗 Related Links

- [Claude AI Skills Documentation](https://docs.anthropic.com/claude/docs/skills)
- [Skill File Format Specification](skill-intro.md)
- [Report Issues](https://github.com/yllemo/claude-skill-manager/issues)
- [View Changelog](CHANGELOG.md)

---

**Made with ❤️ for the Claude AI community**

**Need help?** Open an issue or check the [skill-intro.md](skill-intro.md) for detailed information about the .skill file format.
