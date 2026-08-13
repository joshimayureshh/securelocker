# 🔒 Secure Locker - Digital Cloud Vault

A modern, high-security digital cloud storage web application built with **PHP**, **PostgreSQL**, and **Vanilla CSS**. Secure Locker delivers an enterprise-grade cloud vault experience featuring encrypted file storage, real-time OTP recovery, structured user management, and light/dark theme customization.

---

## 🌟 Key Features

- **🔐 Multi-User Authentication**: Secure registration and login with Bcrypt password hashing, session tokens, and CSRF protection.
- **📧 Gmail SMTP Password Recovery**: 6-digit OTP verification codes delivered directly to the user's Gmail inbox with a 15-minute countdown expiration.
- **📁 File Management**:
  - Drag & drop multi-file uploader with extension and MIME-type validation.
  - In-place async file renaming with file extension preservation.
  - Star / Favorites filtering.
  - Soft deletion with a dedicated Recycle Bin (restore & permanent delete actions).
- **🎨 Theme Engine**:
  - Modern Light Mode & Dark Mode with interactive desktop window preview cards.
  - Persistent preference synchronization across sessions.
- **👤 Structured User Profile**:
  - Interactive avatar chooser.
  - Real-time password strength meter.
  - 1-click "Delete Profile Forever" danger zone that cascades data deletion.
- **📱 Fully Responsive Design**: Mobile bottom navigation bar, touch-friendly drawers, and fluid layouts for smartphones, tablets, and desktops.
- **📊 Activity Logging**: Complete audit log for logins, uploads, deletions, renames, and restores.

---

## 🛠️ Technology Stack

- **Backend**: PHP 8.x (Pure PHP, zero external dependencies)
- **Database**: PostgreSQL
- **Frontend**: Vanilla CSS, Modern JavaScript (ES6+ AJAX)
- **Mailer**: Native TLS/SSL Socket SMTP Engine

---

## 🚀 Getting Started

### 1. Prerequisites
- **XAMPP / WAMP / Apache Web Server**
- **PostgreSQL** installed and running on port `5432`
- **PHP 8.0+** with `pdo_pgsql` and `openssl` extensions enabled in `php.ini`

### 2. Database Setup
1. Open pgAdmin or psql:
   ```sql
   CREATE DATABASE secure_locker;
   ```
2. Import the database schema from `db.sql`:
   ```bash
   psql -U postgres -d secure_locker -f db.sql
   ```

### 3. Configuration
Open `config.php` and set your database and Gmail SMTP credentials:
```php
// Database Settings
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'secure_locker');
define('DB_USER', 'postgres');
define('DB_PASS', 'your_postgres_password');

// Gmail SMTP Settings for OTP Password Recovery
define('SMTP_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@gmail.com');
define('SMTP_PASS', 'your_16_digit_app_password');
define('SMTP_FROM_EMAIL', 'your_email@gmail.com');
define('SMTP_FROM_NAME', 'Secure Locker');
```

### 4. Run the Project
Place the project inside your web server root (e.g. `C:\xampp\htdocs\securelocker`) and navigate to:
```
http://localhost/securelocker/login.php
```

---

## 📄 License
This project is open-source and available under the [MIT License](LICENSE).
