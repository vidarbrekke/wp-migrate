# MK Mobile Theme – Environment & Server Setup

---

## Important: there is no local wordpress installation, just an IDE.

## 1. Quick Access & Credentials

**Staging/Development Server:**
- **Host:** `45.33.31.79`
- **SSH Login:** `ssh staging@45.33.31.79`
    - **SSH Key:** `/Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem` (no password needed)
- **Sudo Password:** `@Flatbygdi73?` (use with `echo '@Flatbygdi73?' | sudo -S ...` for non-interactive sudo)
- **Home Directory:** `/home/staging/`
- **WordPress Root:** `/home/staging/public_html/`
- **Theme Directory:** `/home/staging/public_html/wp-content/themes/mk-mobile-theme/`

**Database (Staging):**
- **Name:** `staging`
- **User:** `staging`
- **Password:** `uNd8afbfFhEU4QDvh7UvW5FCtXWrKna`

---

## 2. Server & Environment Details

- **OS:** Linux (PHP 8.2, Apache, PHP-FPM)
- **PHP Version:** 8.2 (with `php8.2-mysql` for MySQL support)
- **Apache/PHP-FPM:** Restart with `systemctl restart apache2` and `systemctl restart php8.2-fpm` (sudo required)
- **Production and Staging:** Same host, different users/keys

---

## 3. Permissions & Sudo
- Use `sudo` for system-level commands (e.g., `/var/log/apache2/` access)
- For TTY-requiring commands: `ssh -tt ...`
- Sudo password: `@Flatbygdi73?`

---

## 4. Logs & Disk Management

### WordPress & Theme Logs
- **WordPress Debug Log:** `/home/staging/public_html/wp-content/debug.log` (clear with `truncate -s 0 ...`)
- **WooCommerce Logs:** `/home/staging/public_html/wp-content/uploads/wc-logs/` (clear with `find ... -exec truncate -s 0 {}` or delete)
- **Theme Error Logs:** Check WordPress debug log and Apache error logs for theme-related issues

### System Logs
- **Apache Access Logs:** `/var/log/apache2/access.log` (sudo required)
- **Apache Error Logs:** `/var/log/apache2/error.log` (sudo required)
- **Disk Space:** `df -h` (watch for large logs filling disk)

### Quick Log Access Commands
```bash
# Check theme status
ssh -i /Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem staging@45.33.31.79 "cd /home/staging/public_html && wp theme list | grep mk-mobile-theme"

# Check active theme
ssh -i /Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem staging@45.33.31.79 "cd /home/staging/public_html && wp theme status"

# View theme files
ssh -i /Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem staging@45.33.31.79 "ls -la /home/staging/public_html/wp-content/themes/mk-mobile-theme/"

# View WordPress debug log
ssh -i /Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem staging@45.33.31.79 "tail -50 /home/staging/public_html/wp-content/debug.log"

# View Apache error logs (requires sudo)
ssh -i /Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem staging@45.33.31.79 "echo '@Flatbygdi73?' | sudo -S tail -50 /var/log/apache2/error.log"

# Check for theme-related errors in debug log
ssh -i /Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem staging@45.33.31.79 "grep -i 'mk-mobile-theme\|theme' /home/staging/public_html/wp-content/debug.log | tail -20"
```

---

## 5. PHP & Apache Tips
- Check loaded PHP modules: `php -m | grep <module>`
- If you see `Unable to load dynamic library 'mysqli'`, ensure `php8.2-mysql` is installed and restart Apache
- For repository label errors: `apt-get update --allow-releaseinfo-change`
- For PHP extensions: always specify version (e.g., `php8.2-mysql`)
- MaxRequestWorkers Apache warning: tune Apache config if needed

---

## 6. Dependency Management (Composer)
- **Composer config:** `mk-mobile-theme/composer.json` (if using Composer for theme dependencies)
- **Dev dependencies only** (PHPUnit, PHPCS, WP Stubs)
- **Local setup:** Run `composer install` in theme directory for local dev tools/linting
- **Distribution:** Exclude `vendor/` from theme distribution, or use `composer install --no-dev --optimize-autoloader` if runtime deps are needed

---

## 7. Testing & QA
- **Tests run only on staging server**
- **Test files:** `tests/` directory
- **Bootstrap:** `tests/bootstrap.php` (includes Composer autoloader, stubs WP functions)
- **Run tests:** SSH to server, run `phpunit` in the theme directory
- **Local linter warnings** for PHPUnit are expected and can be ignored (suppressed with comments)

---

## 8. Deployment Workflow
- **Develop locally**
- **Deploy to staging** via deployment script (`deploy-staging.sh`)
- **After deploy:**
    - Clear PHP OPcache/server caches
    - Restart PHP-FPM/Apache
    - Verify file contents on server if changes don't appear
    - Activate theme in WordPress admin if needed
- **Theme activation:** `wp theme activate mk-mobile-theme` via WP-CLI

---

## 8a. Version Control & Git Workflow
- **For commit, branching, and push best practices, see:** [`docs/development/git-workflow.md`](git-workflow.md)

---

## 9. Common Server Commands

### File Viewing & Search
- `ssh user@server "ls -l /path/to/directory"` – List directory
- `ssh user@server "cat /path/to/file"` – View file
- `ssh user@server "grep 'pattern' /path/to/file"` – Search in file
- `ssh user@server "find /path -name 'pattern'"` – Find files
- `ssh user@server "tail -n [lines] /path/to/file"` – Tail file

### File Transfer
- `scp -i /path/to/key.pem /local/file user@server:/remote/path` – Upload
- `scp -i /path/to/key.pem user@server:/remote/file /local/path` – Download

### File Manipulation
- `ssh user@server "cp /source/file /dest/file"` – Copy
- `ssh user@server "mv /source/file /dest/file"` – Move/rename
- `ssh user@server "rm /path/to/file"` – Delete
- `ssh user@server "chmod permissions /path/to/file"` – Change permissions

### WordPress Theme Commands
- `wp theme list` – List all themes
- `wp theme status` – Show active theme
- `wp theme activate mk-mobile-theme` – Activate the mk-mobile-theme
- `wp theme delete mk-mobile-theme` – Delete theme (careful!)

### Advanced
- Download, edit locally, upload back
- Use `sed` for in-place edits
- Always backup before editing: `cp` command

---

## 10. Troubleshooting & Common Issues
- **PHP Startup: Unable to load dynamic library 'mysqli'**: Install `php8.2-mysql`, restart Apache
- **Permission denied on logs**: Use sudo/TTY
- **MaxRequestWorkers warning**: Tune Apache config
- **Repository label errors**: `apt-get update --allow-releaseinfo-change`
- **Theme not appearing**: Check file permissions (755 for directories, 644 for files)
- **Theme errors**: Check WordPress debug log and Apache error log for PHP errors

---

**Note:** For extensive edits, always download, edit locally, and upload back to the server using the deployment script.
