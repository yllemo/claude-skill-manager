# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability, please report it responsibly:

1. **Do NOT** create a public GitHub issue
2. Send an email or create a private security advisory through GitHub
3. Include detailed information about the vulnerability
4. Allow reasonable time for the issue to be addressed before public disclosure

## Security Best Practices

### For Production Deployment

- **Use HTTPS**: Always deploy with SSL certificates
- **Strong Passwords**: Use complex passwords and consider bcrypt hashing:
  ```bash
  php -r "echo password_hash('complex-password', PASSWORD_BCRYPT);"
  ```
- **File Permissions**: 
  - Set `/content/` to writable (755) but not executable
  - Protect `/config/` directory from direct web access
  - Consider setting up proper `.htaccess` rules
- **Web Server Configuration**: Block access to sensitive files and directories
- **Regular Updates**: Keep PHP and web server updated

### Security Features

- Session-based authentication with configurable lifetime  
- Protection against path traversal attacks in ZIP file handling
- Frontend validation and backend sanitization of file names
- Protected configuration directory with `.htaccess`
- Input validation and escaping for user data

### Known Limitations

- No rate limiting on login attempts (implement if needed)
- Single password authentication (no user accounts)
- File uploads limited to .skill extensions only

## Security Considerations for .skill Files

- Skills are ZIP archives that can contain any file type
- The viewer renders Markdown which could potentially include embedded content
- Only upload skills from trusted sources
- Review skill contents before installation in Claude AI