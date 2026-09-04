# Production Deployment Guide — Thread &amp; Press Hub

This guide covers deploying the application to a production environment (shared hosting, VPS, or cloud).

---

## 1. Server Requirements

| Component | Minimum | Recommended |
|---|---|---|
| PHP | 8.0 | 8.2+ |
| MySQL / MariaDB | 5.7 / 10.3 | 8.0 / 10.6+ |
| Apache / Nginx | Apache 2.4 | Nginx + PHP-FPM |
| Disk | 500 MB | 2 GB+ (for uploads) |
| RAM | 512 MB | 1 GB+ |
| PHP extensions | `mysqli`, `mbstring`, `gd` (or `imagick`), `fileinfo`, `openssl`, `json`, `dom`, `xml` | + `opcache` |

---

## 2. Pre-Deployment Checklist

### Code preparation
- [ ] Run `composer install --no-dev --optimize-autoloader` (omits PHPUnit etc.)
- [ ] Remove `composer-setup.php` from production
- [ ] Ensure `vendor/` is built (or upload it)
- [ ] Apply all SQL migrations (`migrate_*.sql`) including `migrate_system_fixes.sql`

### Configuration (`includes/config.php`)
- [ ] Update DB credentials (`$db_host`, `$db_user`, `$db_pass`, `$db_name`)
- [ ] Set `ini_set('display_errors', '0');`
- [ ] Set `ini_set('display_startup_errors', '0');`
- [ ] Set `error_reporting(E_ERROR | E_PARSE);`
- [ ] Enable error logging: `ini_set('log_errors', '1'); ini_set('error_log', '/path/to/private/php-error.log');`
- [ ] Set session cookie params:
  ```php
  session_set_cookie_params([
      'lifetime' => 0,
      'path'     => '/',
      'domain'   => '',
      'secure'   => true,        // requires HTTPS
      'httponly' => true,
      'samesite' => 'Lax',
  ]);
  ```
- [ ] Update API keys (Gemini, SMTP, etc.) — never commit them; use environment variables

### Environment-based config (recommended)
Move secrets to a `.env` file (outside web root) or server environment vars. Example wrapper:
```php
// Load secrets without exposing them
$envFile = __DIR__ . '/../private/.env';
if (file_exists($envFile)) {
    foreach (parse_ini_file($envFile) as $k => $v) putenv("$k=$v");
}
$db_pass = getenv('DB_PASSWORD') ?: '';
```

Add `.env`, `vendor/`, `node_modules/`, `.phpunit.cache/` to `.gitignore`.

---

## 3. HTTPS / SSL

### Obtain a certificate
- **Free**: [Let's Encrypt](https://letsencrypt.org/) via Certbot
- **Paid**: From your hosting provider or CA

### Force HTTPS in `.htaccess` (Apache)
```apache
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# HSTS (force HTTPS for 1 year)
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

# Security headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set Referrer-Policy "strict-origin-when-cross-origin"

# Permissions-Policy is emitted per-page by includes/config.php so the Virtual
# Try-On page can opt in to the camera (camera=(self)). Do NOT set a global
# `camera=()` header here — Apache's `Header set` runs after PHP and would
# replace the per-page value, re-breaking getUserMedia on try-on.php.
# If you want a server-level baseline, only restrict what no page needs:
Header set Permissions-Policy "geolocation=()"
```

### Nginx
```nginx
server {
    listen 80;
    server_name threadpresshub.com;
    return 301 https://$host$request_uri;
}
server {
    listen 443 ssl http2;
    server_name threadpresshub.com;

    ssl_certificate     /etc/letsencrypt/live/.../fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/.../privkey.pem;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;

    root /var/www/threadpresshub/public;
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Block direct access to sensitive paths
    location ~ /(vendor|tests|includes|migrate|composer\.json|composer\.lock|phpunit\.xml|\.env) {
        deny all;
        return 404;
    }
}
```

---

## 4. Filesystem Permissions

```bash
# Web user (www-data on Ubuntu)
chown -R www-data:www-data /var/www/threadpresshub
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Writable directories only
chmod -R 775 uploads/ images/products/

# Protect sensitive files
chmod 600 includes/config.php
chmod 600 .env
```

---

## 5. Database

```bash
# Create production DB user with limited privileges
mysql -u root -p
> CREATE DATABASE threadpresshub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
> CREATE USER 'tphapp'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
> GRANT SELECT, INSERT, UPDATE, DELETE ON threadpresshub.* TO 'tphapp'@'localhost';
> FLUSH PRIVILEGES;

# Import schema and migrations
mysql -u tphapp -p threadpresshub < threadpresshub.sql
mysql -u tphapp -p threadpresshub < migrate_system_fixes.sql
# ...repeat for each migrate_*.sql
```

**Backups:** schedule daily `mysqldump`:
```bash
0 2 * * * mysqldump -u backup_user -pPASS threadpresshub | gzip > /backups/tph_$(date +\%F).sql.gz
```

---

## 6. Performance

### Enable OPcache (`php.ini`)
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.validate_timestamps=0   ; disable in prod, deploy clears cache
```

### Browser caching for assets (`.htaccess`)
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/png  "access plus 1 month"
    ExpiresByType text/css   "access plus 1 week"
    ExpiresByType application/javascript "access plus 1 week"
</IfModule>

<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json
</IfModule>
```

---

## 7. Security Hardening

| Check | Status |
|---|---|
| All forms use CSRF tokens (`csrfTokenField()`) | ✅ |
| All DB queries use prepared statements | ✅ |
| Passwords hashed with `password_hash()` | ✅ |
| File uploads validate MIME via `finfo` | ✅ |
| Session uses `httponly` + `secure` + `samesite` | ⚠️ Set in production |
| Admin pages check `$_SESSION['user_type']` | ✅ |
| Error display disabled in production | ⚠️ Must set |
| `vendor/`, `tests/`, `.env` blocked from web | ⚠️ Configure in vhost |
| Dependencies up to date (`composer audit`) | ⚠️ Run regularly |
| Rate limiting on login / password reset | ⚠️ Consider Cloudflare or fail2ban |

Run `composer audit` periodically to detect vulnerable dependencies.

---

## 8. Email (PHPMailer SMTP)

In `includes/email-helper.php`, set production SMTP credentials (via env vars):
```php
$mail->Host       = getenv('SMTP_HOST');
$mail->Username   = getenv('SMTP_USER');
$mail->Password   = getenv('SMTP_PASS');
$mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
```

Recommended providers: SendGrid, Mailgun, Amazon SES, Brevo.

---

## 9. Monitoring &amp; Logs

- **Application errors**: tail `/path/to/php-error.log`
- **Audit log**: built-in `audit_log` DB table (visible at `/admin/audit-log.php`)
- **Uptime**: UptimeRobot or BetterStack (free tier)
- **Performance**: New Relic Lite or Datadog free tier
- **Server logs**: rotate with `logrotate`

---

## 10. Post-Deployment Smoke Tests

After deploying, test these critical paths:

1. ✅ Home page loads over HTTPS (no mixed content warnings)
2. ✅ Register → email verification (if enabled)
3. ✅ Login → "Remember me" → close browser → still logged in
4. ✅ Browse shop, add to cart, checkout with COD
5. ✅ Apply coupon `WELCOME10`, see discount applied
6. ✅ Place order with GCash → upload payment proof
7. ✅ Admin login → verify payment → order status changes
8. ✅ Download invoice PDF
9. ✅ Notification bell shows new orders count
10. ✅ Mobile (Chrome DevTools → 375 px) — all key pages render

Run `vendor/bin/phpunit` once before deploy to verify pure-logic tests still pass.

---

## 11. Optional: Docker Deployment

A `Dockerfile` exists in the repo. To build and run:
```bash
docker build -t threadpresshub .
docker run -d -p 80:80 \
    -e DB_HOST=db -e DB_USER=tphapp -e DB_PASSWORD=secret \
    -v $(pwd)/uploads:/var/www/html/uploads \
    threadpresshub
```

Pair with `docker-compose.yml` to add MariaDB:
```yaml
services:
  app:
    build: .
    ports: ["80:80"]
    depends_on: [db]
    environment:
      DB_HOST: db
      DB_USER: tphapp
      DB_PASSWORD: secret
      DB_NAME: threadpresshub
  db:
    image: mariadb:10.11
    environment:
      MARIADB_ROOT_PASSWORD: rootsecret
      MARIADB_DATABASE: threadpresshub
      MARIADB_USER: tphapp
      MARIADB_PASSWORD: secret
    volumes: ["dbdata:/var/lib/mysql"]
volumes:
  dbdata:
```

---

## 12. Rollback Plan

Always keep:
- The previous release in `/releases/N-1/` (symlink `current` to active version)
- A pre-deploy DB dump (`mysqldump` taken right before migrations)

To rollback:
```bash
ln -sfn /releases/N-1 /var/www/current
mysql -u root -p threadpresshub < /backups/pre-deploy-2026-04-28.sql
systemctl reload nginx
```

---

**Last updated:** April 2026
