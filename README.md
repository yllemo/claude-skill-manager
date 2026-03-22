# Claude Skill Manager

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Monaco Editor](https://img.shields.io/badge/Editor-Monaco-0078d4?logo=visual-studio-code&logoColor=white)](https://microsoft.github.io/monaco-editor/)

A professional PHP-based web application for creating, editing, and managing `.skill` files — ZIP archives containing Markdown instructions that describe reusable workflows for Claude AI.

![Skill Manager Screenshot](screenshot.png)

## ✨ Features

- 🗂️ **Skill Library Management** — Upload, organize, and manage .skill files with searchable metadata
- ✏️ **Monaco Editor Integration** — Full VS Code editor experience with syntax highlighting
- 👁️ **Live Preview** — Real-time Markdown rendering with Mermaid diagram support  
- 🔐 **Secure Authentication** — Password-protected editing with session management
- 📱 **Responsive Design** — Works seamlessly across desktop and mobile devices
- 🏷️ **Tag System** — Organize skills with tags and advanced filtering
- 📥 **Import/Export** — Upload existing .skill files or download for backup
- 🌐 **Public Viewing** — Share skills publicly while keeping editing secure

## 📱 Project Structure

```
skill/
├── index.php           # Dashboard — searchable skill library with upload
├── login.php           # Authentication page  
├── logout.php          # Logout handler
├── download.php        # Serves .skill files for download
├── _common.php         # Shared functions, CSS and helpers
├── _auth.php           # Session authentication
├── config/
│   ├── config.php      # Password and settings
│   └── .htaccess       # Blocks direct HTTP access to /config/
├── view/
│   └── index.php       # Skill viewer — file tree, render markdown
├── edit/
│   └── index.php       # Create/edit skills — Monaco editor
├── content/            # Storage for .skill files (web server writable)
└── skill-intro.md      # Help text about .skill format (shown via ? button)
```

## 🖥️ Application Pages

### 🏠 Dashboard (`/`)
**Requires authentication.**
- Searchable and sortable table of all `.skill` files
- Filter by tags via dropdown or click on tag in list
- Columns: title, description, tags, author, file count, size, modified
- Drag-and-drop upload of existing `.skill` files
- Actions per row: View · Edit · Download · Delete

### 👁️ Skill Viewer (`/view/?file=name.skill`)
**Public access — no authentication required.**
- File tree sidebar showing all files in the ZIP archive
- Renders Markdown via [marked.js](https://marked.js.org/) with [Mermaid](https://mermaid.js.org/) diagram support
- Sidebar shows frontmatter metadata, tags, and file info
- Toggle between rendered view and raw text
- Copy button for file contents

### ✏️ Skill Editor (`/edit/?file=name.skill` or `/edit/` for new)
**Requires authentication.**
- [Monaco Editor](https://microsoft.github.io/monaco-editor/) (VS Code's editor) with syntax highlighting per file type
- File tree sidebar — click to switch files, each file has its own undo/redo
- Live preview with Mermaid diagram support
- Add new files to archive via `+ File` button
- Binary files (images etc.) preserved when saving
- Template button for SKILL.md structure 
- Help button (`?`) shows `skill-intro.md` as modal

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
description: Describe when and how this skill should be used
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

## 🚀 Quick Start

### Requirements
- PHP 8.1+ with `ZipArchive` extension
- Web server (Apache, Nginx, or PHP built-in server)
- Write permissions on `/content/` directory

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yllemo/claude-skill-manager.git
   cd claude-skill-manager
   ```

2. **Set directory permissions**
   ```bash
   chmod 755 content/
   ```

3. **Configure authentication**
   Copy the configuration template and set your password:
   ```bash
   cp config/config.php.example config/config.php
   ```
   Edit `config/config.php` and set your password:
   ```php
   'password' => 'your-secure-password',
   ```

4. **Start the server**
   ```bash
   # Using PHP built-in server (recommended for development)
   php -S localhost:8000
   
   # Or configure with Apache/Nginx for production
   ```

5. **Access the application**
   - Open http://localhost:8000 in your browser
   - Login with your configured password
   - Start creating and managing skills!

## 🔧 Configuration Options

Edit `config/config.php` to customize:
- `password`: Login password (supports bcrypt hashing)
- `session_lifetime`: How long login sessions last (default: 1 month)
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

## 🤝 Contributing

Contributions are welcome! Please read the [Contributing Guide](CONTRIBUTING.md) for details on how to get started.

1. Fork the project
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- [Monaco Editor](https://microsoft.github.io/monaco-editor/) for the excellent code editing experience
- [marked.js](https://marked.js.org/) for Markdown rendering
- [Mermaid](https://mermaid.js.org/) for diagram support
- The Claude AI team for making skills an amazing feature

## 🔗 Related Links

- [Claude AI Skills Documentation](https://docs.anthropic.com/claude/docs/skills)
- [Skill File Format Specification](skill-intro.md)

---

**Need help?** Open an issue or check the [skill-intro.md](skill-intro.md) for detailed information about the .skill file format.
- Skrivbehörighet på `/content/`-mappen

### Snabbstart

```bash
git clone <repo> skill
cd skill
chmod 755 content/
php -S localhost:8080
```

Öppna `http://localhost:8080` i webbläsaren och logga in.

### Apache / Nginx
Peka webbroten mot mappen. Inga `.htaccess`-regler krävs utöver den i `/config/`.

---

## Konfiguration

Redigera `/config/config.php`:

```php
return [
    'password'         => 'admin123',   // Byt till eget lösenord
    'session_lifetime' => 2592000,      // Sekunder — standard 1 månad
    'app_name'         => 'Skill Manager',
];
```

### Bcrypt-lösenord (rekommenderat)

```bash
php -r "echo password_hash('ditt-lösenord', PASSWORD_BCRYPT);"
```

Klistra in den genererade hashen som `password`-värde — systemet känner igen `$2y$...` automatiskt.

---

## Åtkomstkontroll

| Sida | Inloggning krävs |
|------|-----------------|
| `/` (lista) | ✅ Ja |
| `/edit/` | ✅ Ja |
| `/view/` | ❌ Nej — öppen |
| `/login.php` | ❌ Nej |
| `/download.php` | ❌ Nej |

Sessionen varar i konfigurerad tid (standard: 1 månad) och överlever webbläsarstängning.

---

## Beroenden (CDN)

Alla externa bibliotek laddas från CDN — ingen byggprocess krävs.

| Bibliotek | Version | Användning |
|-----------|---------|------------|
| [marked.js](https://marked.js.org/) | latest | Markdown-rendering |
| [Mermaid](https://mermaid.js.org/) | latest | Diagram i Markdown |
| [Monaco Editor](https://microsoft.github.io/monaco-editor/) | 0.47.0 | Kodredigerare i /edit/ |

---

## Tema

Ljust och mörkt tema — sparas i `localStorage`. Klicka på 🌓 i headern för att växla. Monaco och Mermaid synkar automatiskt med valt tema.
