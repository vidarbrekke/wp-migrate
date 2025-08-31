# MotherKnitter.com Server Environment Reference

## ⚠️ SECURITY NOTES ⚠️

- This file contains sensitive information; do not share with unauthorized personnel
- Do not commit this file to version control (add to .gitignore)
- Store securely with encryption when not in use
- Revoke and rotate credentials if compromised

---

## PRODUCTION ENVIRONMENT

### Server Access
- **IP Address**: 45.33.31.79
- **SSH User**: motherknitter
- **SSH Key Path**: `/Users/vidarbrekke/Dev/socialintent/motherknitter.pem`
- **SSH Command**:
  ```
  ssh -i /Users/vidarbrekke/Dev/socialintent/motherknitter.pem motherknitter@45.33.31.79
  ```
- **Root Directory**: `/home/motherknitter/public_html/`

### Database Access
- **DB Name**: motherknitter
- **DB User**: motherknitter
- **DB Password**: PRroNJpDP78pEzocGCsjaS3dCSXA6Ze
- **DB Host**: localhost
- **MySQL Access Command**:
  ```
  mysql -u motherknitter -pPRroNJpDP78pEzocGCsjaS3dCSXA6Ze motherknitter
  ```

### WordPress Access
- **Frontend URL**: https://motherknitter.com
- **Admin URL**: https://motherknitter.com/wp-admin/

### Important File Paths
- **WordPress Root**: `/home/motherknitter/public_html/`
- **wp-config.php**: `/home/motherknitter/public_html/wp-config.php`
- **Plugins Directory**: `/home/motherknitter/public_html/wp-content/plugins/`
- **Themes Directory**: `/home/motherknitter/public_html/wp-content/themes/`
- **Uploads Directory**: `/home/motherknitter/public_html/wp-content/uploads/`
- **Error Logs**: `/home/motherknitter/public_html/wp-content/uploads/wc-logs/`
- **Debug Log**: `/home/motherknitter/public_html/wp-content/debug.log`

---

## STAGING ENVIRONMENT

### Server Access
- **IP Address**: 45.33.31.79
- **SSH User**: staging
- **SSH Key Path**: `/Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem`
- **SSH Command**:
  ```
  ssh -i /Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem staging@45.33.31.79
  ```
- **Root Directory**: `/home/staging/public_html/`

### Database Access
- **DB Name**: staging
- **DB User**: staging
- **DB Password**: uNd8afbfFhEU4QDvh7UvW5FCtXWrKna
- **DB Host**: localhost
- **MySQL Access Command**:
  ```
  mysql -u staging -puNd8afbfFhEU4QDvh7UvW5FCtXWrKna staging
  ```

### WordPress Access
- **Frontend URL**: https://staging.motherknitter.com
- **Admin URL**: https://staging.motherknitter.com/wp-admin/

### Critical Configuration
- **WP_HOME**: Must be set to `https://staging.motherknitter.com`
- **WP_SITEURL**: Must be set to `https://staging.motherknitter.com`
- **Debug Mode**: Enabled (`WP_DEBUG` set to `true`)

### Important File Paths
- **WordPress Root**: `/home/staging/public_html/`
- **wp-config.php**: `/home/staging/public_html/wp-config.php`
- **Plugins Directory**: `/home/staging/public_html/wp-content/plugins/`
- **Themes Directory**: `/home/staging/public_html/wp-content/themes/`
- **Uploads Directory**: `/home/staging/public_html/wp-content/uploads/`
- **Error Logs**: `/home/staging/public_html/wp-content/uploads/wc-logs/`

---

## WHOLESALE ENVIRONMENT

### Server Access
- **IP Address**: 45.33.31.79
- **SSH User**: wholesale
- **SSH Key Path**: `/Users/vidarbrekke/Dev/socialintent/wholesale.motherknitter.pem`
- **SSH Command**:
  ```
  ssh -i /Users/vidarbrekke/Dev/socialintent/wholesale.motherknitter.pem wholesale@45.33.31.79
  ```
- **Root Directory**: `/home/wholesale/public_html/`

### Database Access
- **DB Name**: wholesale
- **DB User**: wholesale
- **DB Password**: yvF7cW5mZXSbu4x5pAdeZadJKBjChap
- **DB Host**: localhost
- **MySQL Access Command**:
  ```
  mysql -u wholesale -pyvF7cW5mZXSbu4x5pAdeZadJKBjChap wholesale
  ```

### WordPress Access
- **Frontend URL**: https://wholesale.motherknitter.com
- **Admin URL**: https://wholesale.motherknitter.com/wp-admin/

### Important File Paths
- **WordPress Root**: `/home/wholesale/public_html/`
- **wp-config.php**: `/home/wholesale/public_html/wp-config.php`
- **Plugins Directory**: `/home/wholesale/public_html/wp-content/plugins/`
- **Themes Directory**: `/home/wholesale/public_html/wp-content/themes/`
- **Uploads Directory**: `/home/wholesale/public_html/wp-content/uploads/`
- **Error Logs**: `/home/wholesale/public_html/wp-content/uploads/wc-logs/`

---

## COMMON COMMANDS

### Database Operations

#### Check Site URLs:
```bash
# Production
mysql -u motherknitter -pPRroNJpDP78pEzocGCsjaS3dCSXA6Ze -e "SELECT option_name, option_value FROM wp_options WHERE option_name IN ('siteurl','home');" motherknitter

# Staging
mysql -u staging -puNd8afbfFhEU4QDvh7UvW5FCtXWrKna -e "SELECT option_name, option_value FROM wp_options WHERE option_name IN ('siteurl','home');" staging

# Wholesale
mysql -u wholesale -pyvF7cW5mZXSbu4x5pAdeZadJKBjChap -e "SELECT option_name, option_value FROM wp_options WHERE option_name IN ('siteurl','home');" wholesale
```

#### Update Site URLs:
```bash
# Production
mysql -u motherknitter -pPRroNJpDP78pEzocGCsjaS3dCSXA6Ze -e "UPDATE wp_options SET option_value='https://motherknitter.com' WHERE option_name IN ('siteurl','home');" motherknitter

# Staging
mysql -u staging -puNd8afbfFhEU4QDvh7UvW5FCtXWrKna -e "UPDATE wp_options SET option_value='https://staging.motherknitter.com' WHERE option_name IN ('siteurl','home');" staging

# Wholesale
mysql -u wholesale -pyvF7cW5mZXSbu4x5pAdeZadJKBjChap -e "UPDATE wp_options SET option_value='https://wholesale.motherknitter.com' WHERE option_name IN ('siteurl','home');" wholesale
```

#### Backup Database:
```bash
# Production
mysqldump -u motherknitter -pPRroNJpDP78pEzocGCsjaS3dCSXA6Ze motherknitter > ~/production_backup_$(date +%Y%m%d).sql

# Staging
mysqldump -u staging -puNd8afbfFhEU4QDvh7UvW5FCtXWrKna staging > ~/staging_backup_$(date +%Y%m%d).sql

# Wholesale
mysqldump -u wholesale -pyvF7cW5mZXSbu4x5pAdeZadJKBjChap wholesale > ~/wholesale_backup_$(date +%Y%m%d).sql
```

### Plugin Management

#### Disable All Plugins:
```bash
# Production
ssh -i /Users/vidarbrekke/Dev/socialintent/motherknitter.pem motherknitter@45.33.31.79 "mv /home/motherknitter/public_html/wp-content/plugins /home/motherknitter/public_html/wp-content/plugins-disabled"

# Staging
ssh -i /Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem staging@45.33.31.79 "mv /home/staging/public_html/wp-content/plugins /home/staging/public_html/wp-content/plugins-disabled"

# Wholesale
ssh -i /Users/vidarbrekke/Dev/socialintent/wholesale.motherknitter.pem wholesale@45.33.31.79 "mv /home/wholesale/public_html/wp-content/plugins /home/wholesale/public_html/wp-content/plugins-disabled"
```

#### Re-enable All Plugins:
```bash
# Production
ssh -i /Users/vidarbrekke/Dev/socialintent/motherknitter.pem motherknitter@45.33.31.79 "mv /home/motherknitter/public_html/wp-content/plugins-disabled /home/motherknitter/public_html/wp-content/plugins"

# Staging
ssh -i /Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem staging@45.33.31.79 "mv /home/staging/public_html/wp-content/plugins-disabled /home/staging/public_html/wp-content/plugins"

# Wholesale
ssh -i /Users/vidarbrekke/Dev/socialintent/wholesale.motherknitter.pem wholesale@45.33.31.79 "mv /home/wholesale/public_html/wp-content/plugins-disabled /home/wholesale/public_html/wp-content/plugins"
```

#### Clear Cache:
```bash
# Production
ssh -i /Users/vidarbrekke/Dev/socialintent/motherknitter.pem motherknitter@45.33.31.79 "rm -rf /home/motherknitter/public_html/wp-content/cache/*"

# Staging
ssh -i /Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem staging@45.33.31.79 "rm -rf /home/staging/public_html/wp-content/cache/*"

# Wholesale
ssh -i /Users/vidarbrekke/Dev/socialintent/wholesale.motherknitter.pem wholesale@45.33.31.79 "rm -rf /home/wholesale/public_html/wp-content/cache/*"
```

---

## TROUBLESHOOTING

### Server Access Tips
- **All environments are on the same host (45.33.31.79)** but use different users and SSH keys
- **Always use the correct SSH key and user** as specified above for each environment
- **Home directories** follow pattern: `/home/[username]/`
- **WordPress root** follows pattern: `/home/[username]/public_html/`

### Permissions & Sudo
- The `motherknitter` user requires `sudo` for many system-level commands
- **Sudo password** (as of May 2025): `@Flatbygdi73?`
- Use `ssh -tt ...` for commands requiring TTY

### Common Issues
- **Redirect loops**: Check database settings and clear caches
- **Plugin conflicts**: Disable plugins temporarily to isolate issues
- **Large log files**: Clear debug.log and wc-logs directory regularly
- **Disk space**: Monitor with `df -h`

### PHP & Apache
- **Apache runs with PHP-FPM**. Restart with: `systemctl restart apache2` and `systemctl restart php8.2-fpm`
- **PHP 8.2** is the active version
- Check loaded modules: `php -m | grep <module>`

---

Last Updated: May 7, 2025
