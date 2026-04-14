# IT Manager Pro — Enterprise IT Department Management System

A complete, production-ready IT Department Management System built with **pure PHP**, **Bootstrap 5**, and **MySQL**. No frameworks required.

---

## 🚀 Features

| Module | Features |
|--------|----------|
| **Dashboard** | Live stats, Chart.js charts, expiry alerts, activity timeline |
| **Asset Management** | CRUD, barcode/QR generation, assignment history, camera scanner |
| **License Management** | Seat tracking, expiry alerts, per-user/device/enterprise types |
| **Employee Management** | Full profiles, assigned assets & licenses, activity log |
| **Department Management** | Budget tracking, manager assignment, cost analysis |
| **Vendor Management** | Contact info, linked assets/licenses, spend tracking |
| **Procurement** | Purchase orders, line items, approval workflow |
| **Document Management** | Upload/preview/download, link to assets/employees/vendors |
| **Password Vault** | AES-256 encrypted, per-user, optional master key, show/copy |
| **Email Management** | SMTP configs, send log, automated expiry alerts |
| **Reports** | 6 report types, Chart.js charts, CSV export, print/PDF |
| **Audit Logs** | Every action tracked with user, IP, timestamp, data diff |
| **Roles & Permissions** | RBAC, 43 permissions, 5 default roles, interactive matrix |
| **Settings** | App config, DB backup/restore, system info, cron jobs |
| **REST API** | Full JSON API v1 with Bearer token auth |
| **Bulk Import** | CSV import for assets and employees |
| **Dark Mode** | Per-user preference, persisted server-side |

---

## 📋 Requirements

- PHP 8.1+ with extensions: `pdo_mysql`, `openssl`, `fileinfo`, `mbstring`
- MySQL 8.0+ or MariaDB 10.6+
- Apache 2.4+ with `mod_rewrite` enabled, or Nginx
- Web server write permissions on `/uploads/`, `/logs/`, `/backups/`, `/config/settings.json`

---

## 🛠 Installation

### 1. Clone / Extract
```bash
git clone https://github.com/yourorg/itsm.git /var/www/html/itsm
# OR extract the zip to your web root
```

### 2. Database Setup
```sql
-- Create database
CREATE DATABASE itsm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'itsm_user'@'localhost' IDENTIFIED BY 'strongpassword';
GRANT ALL PRIVILEGES ON itsm_db.* TO 'itsm_user'@'localhost';
FLUSH PRIVILEGES;
```

```bash
# Import schema and seed data
mysql -u itsm_user -p itsm_db < /var/www/html/itsm/config/schema.sql
```

### 3. Environment Configuration
Set environment variables (recommended) or edit `config/config.php`:

```bash
# Apache: /etc/apache2/sites-available/itsm.conf
SetEnv DB_HOST     localhost
SetEnv DB_PORT     3306
SetEnv DB_NAME     itsm_db
SetEnv DB_USER     itsm_user
SetEnv DB_PASS     strongpassword
SetEnv APP_URL     https://yourdomain.com/itsm
SetEnv ENCRYPTION_KEY  your-32-char-secret-key-here!!
SetEnv API_KEY     your-api-key-here
SetEnv APP_ENV     production
```

### 4. File Permissions
```bash
chmod 750 /var/www/html/itsm/uploads
chmod 750 /var/www/html/itsm/uploads/documents
chmod 750 /var/www/html/itsm/uploads/avatars
chmod 750 /var/www/html/itsm/logs
chmod 750 /var/www/html/itsm/backups
chmod 640 /var/www/html/itsm/config/settings.json  # if exists
chown -R www-data:www-data /var/www/html/itsm/uploads
chown -R www-data:www-data /var/www/html/itsm/logs
chown -R www-data:www-data /var/www/html/itsm/backups
```

### 5. Apache VirtualHost
```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /var/www/html

    <Directory /var/www/html/itsm>
        AllowOverride All
        Require all granted
    </Directory>

    SSLEngine on
    SSLCertificateFile    /etc/ssl/certs/yourdomain.crt
    SSLCertificateKeyFile /etc/ssl/private/yourdomain.key
</VirtualHost>
```

### 6. Nginx Config (alternative)
```nginx
server {
    listen 443 ssl;
    server_name yourdomain.com;
    root /var/www/html;
    index index.php;

    location /itsm/api/v1/ {
        rewrite ^/itsm/api/v1/(.*)$ /itsm/api/v1/index.php last;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* /(config|classes|includes|logs|backups)/ {
        deny all;
    }
}
```

### 7. Cron Jobs
```bash
crontab -e
# Add:
0 9 * * *  php /var/www/html/itsm/cron/send_alerts.php >> /var/www/html/itsm/logs/cron.log 2>&1
0 2 * * 0  php /var/www/html/itsm/cron/backup.php     >> /var/www/html/itsm/logs/cron.log 2>&1
0 0 1 * *  php /var/www/html/itsm/cron/cleanup.php    >> /var/www/html/itsm/logs/cron.log 2>&1
```

---

## 🔐 Default Login

| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `password` |

> ⚠️ **Change this immediately after first login!**

---

## 🔑 API Usage

```bash
# Set your API key via env var API_KEY, then:
curl -H "Authorization: Bearer YOUR_API_KEY" \
     https://yourdomain.com/itsm/api/v1/dashboard

curl -H "Authorization: Bearer YOUR_API_KEY" \
     https://yourdomain.com/itsm/api/v1/assets?status=available&page=1

curl -X POST \
     -H "Authorization: Bearer YOUR_API_KEY" \
     -H "Content-Type: application/json" \
     -d '{"name":"New Laptop","brand":"Dell","price":1500}' \
     https://yourdomain.com/itsm/api/v1/assets
```

Full API documentation available at `/pages/api_docs.php` when logged in.

---

## 📁 Directory Structure

```
itsm/
├── actions/          POST handlers (form submissions)
├── api/              AJAX endpoints + REST API v1
│   └── v1/           REST API router
├── assets/           CSS + JS
│   ├── css/app.css   Full design system
│   └── js/app.js     CSRF-aware API wrapper, UI logic
├── backups/          SQL backup files (protected)
├── classes/          Auth.php (RBAC, sessions, CSRF)
├── config/           config.php, database.php, schema.sql
├── cron/             Scheduled tasks (alerts, backup, cleanup)
├── includes/         bootstrap.php, functions.php, header.php, footer.php
├── logs/             Application logs (protected)
├── pages/            UI pages
├── uploads/          User uploads (PHP execution blocked)
│   ├── avatars/
│   └── documents/
├── .htaccess         Security rules, rewrite engine
├── index.php         Entry point (redirects to login/dashboard)
└── login.php         Login page
```

---

## 🛡 Security Features

- `password_hash()` / `password_verify()` with bcrypt cost 12
- CSRF tokens on every form and AJAX request header
- XSS prevention via `h()` wrapper on all output
- SQL injection protection via PDO prepared statements exclusively
- Session regeneration every 5 minutes + timeout after 60 min
- Brute-force lockout: 5 failed attempts → 15-minute account lock
- AES-256-CBC encryption for vault passwords with per-entry IV
- Path traversal protection on all file downloads
- PHP execution blocked in `/uploads/` via `.htaccess`
- Direct access blocked on `/config/`, `/classes/`, `/includes/`, `/logs/`, `/backups/`
- Security headers: X-Frame-Options, X-Content-Type-Options, CSP, Referrer-Policy

---

## 📦 Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.1+ (Pure OOP, no framework) |
| Database | MySQL 8 / MariaDB 10.6 |
| Frontend | Bootstrap 5.3, DM Sans font, JetBrains Mono |
| Charts | Chart.js 4.4 |
| Barcodes | JsBarcode 3.11 |
| QR Codes | qrcode.js 1.5 |
| Icons | Bootstrap Icons 1.11 |

---

## 📄 License

MIT License — Free to use, modify, and distribute.

---

*Built with ❤️ as a complete enterprise-grade system. No frameworks, no compromises.*
