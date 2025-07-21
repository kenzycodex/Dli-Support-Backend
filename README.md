# DLI Support Platform

A comprehensive student support ticketing system built with Laravel (Backend) and Next.js (Frontend). This platform enables students to submit support tickets while providing staff with tools to manage and respond to inquiries efficiently.

## 🚀 Features

### Core Functionality
- **Role-Based Access Control** - Student, Counselor, Advisor, Admin roles
- **Ticket Management** - Create, assign, update, and track support tickets
- **File Attachments** - Upload and download documents, images, and PDFs
- **Real-time Responses** - Conversation-style ticket responses
- **Crisis Detection** - Auto-escalation for urgent mental health cases
- **Help & Resources** - FAQ system and resource library
- **Notifications** - Real-time alerts for ticket updates
- **Analytics** - Comprehensive reporting and insights

### Technical Features
- **Authentication** - Laravel Sanctum token-based auth
- **File Storage** - Configurable local/cloud storage
- **Rate Limiting** - API protection and abuse prevention
- **CORS Support** - Cross-origin resource sharing
- **Caching** - Optimized performance with Redis/File cache
- **Logging** - Comprehensive error and activity logging

## 📋 Table of Contents

1. [System Requirements](#system-requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [File Storage Setup](#file-storage-setup)
5. [Deployment](#deployment)
6. [API Documentation](#api-documentation)
7. [Troubleshooting](#troubleshooting)
8. [Security](#security)
9. [Maintenance](#maintenance)
10. [Contributing](#contributing)

## 🔧 System Requirements

### Development Environment
- **PHP**: 8.1 or higher
- **Node.js**: 18.0 or higher
- **Database**: MySQL 8.0 or PostgreSQL 13+
- **Composer**: 2.0 or higher
- **NPM/Yarn**: Latest stable version

### Production Environment
- **Server**: Linux VPS (Ubuntu 20.04+ recommended)
- **Web Server**: Nginx or Apache
- **Database**: MySQL 8.0 or PostgreSQL 13+
- **Storage**: Minimum 10GB for attachments
- **Memory**: 2GB RAM minimum, 4GB recommended

## 🛠 Installation

### Backend Setup (Laravel)

```bash
# Clone the repository
git clone https://github.com/yourusername/dli-support-platform.git
cd dli-support-platform

# Navigate to backend directory
cd backend

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Create storage directories
mkdir -p storage/app/public/ticket-attachments
mkdir -p storage/app/private
chmod -R 755 storage bootstrap/cache

# Create storage symlink
php artisan storage:link

# Run database migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed

# Start development server
php artisan serve
```

### Frontend Setup (Next.js)

```bash
# Navigate to frontend directory
cd ../frontend

# Install dependencies
npm install

# Copy environment file
cp .env.example .env.local

# Start development server
npm run dev
```

### Access the Application

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000
- **API Documentation**: http://localhost:8000/api/docs

## ⚙️ Configuration

### Environment Variables

#### Backend (.env)
```env
# Application
APP_NAME="DLI Support Platform"
APP_ENV=local
APP_KEY=base64:your-generated-key
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dli_student_db
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Frontend Configuration
FRONTEND_URL=http://localhost:3000
CORS_ALLOWED_ORIGINS=http://localhost:3000

# File Storage
FILESYSTEM_DISK=public
MAX_FILE_SIZE=10240
ALLOWED_FILE_TYPES="pdf,png,jpg,jpeg,gif,doc,docx,txt"
MAX_ATTACHMENTS_PER_TICKET=5
MAX_ATTACHMENTS_PER_RESPONSE=3

# Session & Auth
SESSION_DRIVER=database
JWT_SECRET=your-jwt-secret
JWT_TTL=60

# Email Configuration (Optional)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@dlisupport.com"
MAIL_FROM_NAME="${APP_NAME}"

# Cache & Queue
CACHE_DRIVER=file
QUEUE_CONNECTION=database

# Logging
LOG_LEVEL=debug
API_LOGGING_ENABLED=false
```

#### Frontend (.env.local)
```env
# API Configuration
NEXT_PUBLIC_API_URL=http://localhost:8000/api
NEXT_PUBLIC_APP_URL=http://localhost:3000

# Application Settings
NEXT_PUBLIC_APP_NAME="DLI Support Platform"
NEXT_PUBLIC_APP_VERSION="1.0.0"
```

### Database Configuration

```bash
# Create MySQL database
mysql -u root -p
CREATE DATABASE dli_student_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dli_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON dli_student_db.* TO 'dli_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 📁 File Storage Setup

### Development (Local Storage)

The system uses Laravel's public disk for file storage in development:

```bash
# Ensure storage directories exist
mkdir -p storage/app/public/ticket-attachments
mkdir -p storage/app/private

# Set proper permissions
chmod -R 755 storage/

# Create storage symlink (if not exists)
php artisan storage:link

# Verify symlink
ls -la public/storage
```

**Storage Structure:**
```
storage/app/public/
├── ticket-attachments/
│   └── 2025/
│       └── 07/
│           ├── 1234567890_abcdef123.pdf
│           └── 1234567891_abcdef456.png
└── .gitkeep
```

### Production Storage Options

#### Option 1: Local Storage (VPS Server) - Recommended
```env
# Production .env
FILESYSTEM_DISK=public
APP_URL=https://yourdomain.com
```

**Advantages:**
- ✅ No additional costs
- ✅ Simple setup and maintenance
- ✅ Fast access (same server)
- ✅ Full control over files

#### Option 2: AWS S3 (Cloud Storage)
```env
# Production .env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
AWS_URL=https://your-bucket.s3.amazonaws.com
```

**Advantages:**
- ✅ Unlimited storage
- ✅ Global CDN
- ✅ Automatic backups
- ✅ Serverless compatibility

#### Option 3: Cloudflare R2 (S3-Compatible)
```env
# Production .env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_r2_access_key
AWS_SECRET_ACCESS_KEY=your_r2_secret_key
AWS_DEFAULT_REGION=auto
AWS_BUCKET=your-r2-bucket
AWS_ENDPOINT=https://your-account-id.r2.cloudflarestorage.com
AWS_URL=https://your-custom-domain.com
```

### File Upload Configuration

#### Laravel Configuration (config/filesystems.php)
```php
'disks' => [
    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
    ],
    
    'private' => [
        'driver' => 'local',
        'root' => storage_path('app/private'),
        'serve' => true,
    ],
    
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
    ],
],
```

#### PHP Configuration
```ini
# php.ini settings
upload_max_filesize = 10M
post_max_size = 50M
max_execution_time = 120
memory_limit = 512M
```

### Git Configuration for Files

**⚠️ IMPORTANT: Never commit uploaded files to Git!**

Add to `.gitignore`:
```gitignore
# Storage directories (exclude uploaded files)
/storage/app/public/ticket-attachments/
/storage/app/private/
/public/storage

# Keep directory structure
!/storage/app/public/.gitkeep
!/storage/app/private/.gitkeep
```

## 🚀 Deployment

### Production Deployment Script

```bash
#!/bin/bash
# deploy.sh - Production deployment script

echo "🚀 Deploying DLI Support Platform..."

# 1. Pull latest code (files are NOT included)
git pull origin main

# 2. Backend deployment
cd backend

# Install dependencies
composer install --no-dev --optimize-autoloader

# Create storage directories
mkdir -p storage/app/public/ticket-attachments
mkdir -p storage/app/private
mkdir -p storage/logs

# Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 755 storage bootstrap/cache

# Create storage symlink
php artisan storage:link

# Run migrations (careful in production!)
php artisan migrate --force

# Clear and cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. Frontend deployment
cd ../frontend

# Install dependencies and build
npm ci
npm run build

# 4. Restart services
sudo systemctl reload nginx
sudo systemctl restart php8.1-fpm

echo "✅ Deployment complete!"

# 5. Verify setup
echo "📁 Verifying storage setup..."
ls -la storage/app/public/
ls -la public/storage
readlink public/storage

echo "🎯 Platform ready!"
```

### Nginx Configuration

```nginx
# /etc/nginx/sites-available/dli-support
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/dli-support-platform/frontend/out;
    index index.html;

    # File upload size
    client_max_body_size 50M;

    # Frontend (Next.js static files)
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Backend API
    location /api {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Storage files
    location /storage {
        alias /var/www/dli-support-platform/backend/storage/app/public;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
}
```

### SSL Configuration (Let's Encrypt)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Get SSL certificate
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Auto-renewal (add to crontab)
0 12 * * * /usr/bin/certbot renew --quiet
```

## 📚 API Documentation

### Authentication

All API endpoints require authentication using Bearer tokens.

#### Login
```bash
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}

# Response
{
  "success": true,
  "data": {
    "user": {...},
    "token": "1|abcdef...",
    "token_type": "Bearer"
  }
}
```

#### Demo Login
```bash
POST /api/auth/demo-login
Content-Type: application/json

{
  "role": "admin" // or "student", "counselor", "advisor"
}
```

### Core Endpoints

#### Tickets
```bash
# Get tickets (role-filtered)
GET /api/tickets
Authorization: Bearer {token}

# Create ticket
POST /api/tickets
Authorization: Bearer {token}
Content-Type: multipart/form-data

# Get specific ticket
GET /api/tickets/{id}
Authorization: Bearer {token}

# Download attachment
GET /api/tickets/attachments/{attachment_id}/download
Authorization: Bearer {token}
```

#### User Management (Admin only)
```bash
# List users
GET /api/admin/users
Authorization: Bearer {token}

# Create user
POST /api/admin/users
Authorization: Bearer {token}

# Bulk create users
POST /api/admin/users/bulk-create
Authorization: Bearer {token}
```

#### Help & Resources
```bash
# Get FAQs
GET /api/help/faqs
Authorization: Bearer {token}

# Get resources
GET /api/resources
Authorization: Bearer {token}

# Suggest content (counselors only)
POST /api/help/suggest-content
Authorization: Bearer {token}
```

### Rate Limits

- **General API**: 60 requests/minute
- **File downloads**: 200 requests/minute
- **Admin operations**: 20 requests/minute
- **Bulk operations**: 5 requests/minute

### Response Format

All API responses follow this standard format:

```json
{
  "success": true,
  "status": 200,
  "message": "Operation completed successfully",
  "data": {...},
  "timestamp": "2025-01-20T10:30:00Z"
}
```

## 🔧 Troubleshooting

### Common Issues

#### File Download Errors (500 Internal Server Error)

**Symptoms:**
- Downloads fail with 500 error
- Frontend shows retry attempts
- Files exist in storage

**Solutions:**
```bash
# 1. Check storage symlink
ls -la public/storage
php artisan storage:link

# 2. Fix permissions
sudo chown -R www-data:www-data storage/
sudo chmod -R 755 storage/

# 3. Check file exists
ls -la storage/app/public/ticket-attachments/

# 4. Test with debug endpoint
curl -H "Authorization: Bearer {token}" \
     http://localhost:8000/api/debug/attachment/1

# 5. Check Laravel logs
tail -f storage/logs/laravel.log
```

#### Database Connection Issues

```bash
# Test database connection
php artisan tinker
DB::connection()->getPdo();

# Check database exists
mysql -u username -p
SHOW DATABASES;
```

#### CORS Errors

Update `config/cors.php`:
```php
'allowed_origins' => [
    'http://localhost:3000',
    'https://yourdomain.com',
],
```

#### Permission Denied Errors

```bash
# Fix Laravel permissions
sudo chown -R www-data:www-data /var/www/your-app
sudo chmod -R 755 /var/www/your-app/storage
sudo chmod -R 755 /var/www/your-app/bootstrap/cache
```

### Debug Endpoints

The system includes debug endpoints for troubleshooting:

```bash
# Storage configuration test
GET /api/debug/storage-test

# Specific attachment test
GET /api/debug/attachment/{id}

# System health check
GET /api/health
```

### Log Files

Important log locations:
- **Laravel**: `storage/logs/laravel.log`
- **Nginx**: `/var/log/nginx/error.log`
- **PHP-FPM**: `/var/log/php8.1-fpm.log`

## 🔒 Security

### File Upload Security

The system implements several security measures:

```php
// Allowed file types
'allowed_types' => [
    'application/pdf',
    'image/png', 'image/jpeg', 'image/gif',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/plain'
],

// File size limits
'max_file_size' => 10 * 1024 * 1024, // 10MB

// Filename sanitization
preg_replace('/[<>:"|?*]/', '_', $filename);
```

### Access Control

- **Role-based permissions** for all operations
- **Ticket ownership validation** for students
- **Assignment-based access** for staff
- **Admin-only operations** for sensitive actions

### Rate Limiting

Implemented across all endpoints to prevent abuse:
- IP-based rate limiting
- User-based rate limiting
- Operation-specific limits

### HTTPS Configuration

Always use HTTPS in production:
```nginx
# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    # ... rest of configuration
}
```

## 🛠 Maintenance

### Backup Strategy

#### Daily Backup Script
```bash
#!/bin/bash
# backup.sh - Daily backup script

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/dli-support"

# Create backup directory
mkdir -p $BACKUP_DIR

# 1. Database backup
mysqldump -u username -p password dli_student_db > $BACKUP_DIR/database_$DATE.sql

# 2. Files backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz \
    /var/www/dli-support-platform/backend/storage/app/public/ticket-attachments/

# 3. Configuration backup
tar -czf $BACKUP_DIR/config_$DATE.tar.gz \
    /var/www/dli-support-platform/backend/.env \
    /etc/nginx/sites-available/dli-support

# 4. Clean old backups (keep 30 days)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete

# 5. Upload to remote backup (optional)
rsync -av $BACKUP_DIR/ user@backup-server:/remote/backups/dli-support/

echo "✅ Backup completed: $DATE"
```

#### Crontab Configuration
```bash
# Edit crontab
crontab -e

# Add backup job (daily at 2 AM)
0 2 * * * /path/to/backup.sh >> /var/log/backup.log 2>&1

# Add certificate renewal (monthly)
0 3 1 * * /usr/bin/certbot renew --quiet
```

### Performance Optimization

#### Laravel Optimizations
```bash
# Production optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Database optimizations
php artisan model:cache
php artisan queue:work --daemon

# Storage cleanup
php artisan storage:cleanup-old-files
```

#### Database Maintenance
```sql
-- Optimize tables monthly
OPTIMIZE TABLE tickets, ticket_responses, ticket_attachments, users;

-- Analyze query performance
EXPLAIN SELECT * FROM tickets WHERE status = 'Open';

-- Index optimization
SHOW INDEX FROM tickets;
```

### Monitoring

#### Health Check Endpoints
```bash
# System health
curl http://localhost:8000/api/health

# Storage health
curl -H "Authorization: Bearer {token}" \
     http://localhost:8000/api/debug/storage-test
```

#### Log Monitoring
```bash
# Monitor Laravel logs
tail -f storage/logs/laravel.log | grep ERROR

# Monitor Nginx logs
tail -f /var/log/nginx/access.log

# Monitor system resources
htop
df -h
```

## 🤝 Contributing

### Development Workflow

1. **Fork the repository**
2. **Create feature branch**: `git checkout -b feature/amazing-feature`
3. **Make changes and test thoroughly**
4. **Commit changes**: `git commit -m 'Add amazing feature'`
5. **Push to branch**: `git push origin feature/amazing-feature`
6. **Open Pull Request**

### Code Standards

#### Backend (Laravel)
- Follow PSR-12 coding standards
- Use meaningful variable and method names
- Add comprehensive comments for complex logic
- Write unit tests for new features
- Use Laravel best practices

#### Frontend (Next.js)
- Use TypeScript for type safety
- Follow React best practices
- Use meaningful component names
- Implement proper error handling
- Add JSDoc comments for complex functions

### Testing

```bash
# Backend tests
cd backend
php artisan test

# Frontend tests
cd frontend
npm run test

# E2E tests
npm run test:e2e
```

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

### Getting Help

1. **Check the documentation** (this README)
2. **Search existing issues** on GitHub
3. **Check the troubleshooting section**
4. **Create a new issue** with detailed information

### Issue Reporting

When reporting issues, please include:
- System information (OS, PHP version, Node.js version)
- Steps to reproduce the issue
- Expected vs actual behavior
- Relevant log files
- Screenshots (if applicable)

### Contact

- **Project Maintainer**: [Your Name](mailto:your.email@example.com)
- **Institution**: Distance Learning Institute, University of Lagos
- **GitHub**: [Project Repository](https://github.com/yourusername/dli-support-platform)

---

## 🎯 Quick Start Summary

For a rapid deployment:

```bash
# 1. Clone and setup
git clone https://github.com/yourusername/dli-support-platform.git
cd dli-support-platform

# 2. Backend setup
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve

# 3. Frontend setup (new terminal)
cd frontend
npm install
cp .env.example .env.local
npm run dev

# 4. Access application
# Frontend: http://localhost:3000
# Backend: http://localhost:8000
```

🎉 **You're ready to go!** The platform is now running with file downloads working perfectly.

---

*Last updated: July 21, 2025*
*Version: 1.0.0*