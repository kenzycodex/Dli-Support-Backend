# Laravel + Next.js Deployment Guide

This guide walks you through deploying your Laravel backend and Next.js frontend from your local machine to a remote server.

## Prerequisites

- Remote server with Ubuntu/Debian
- SSH access to the server
- Domain name (optional, can use IP address)

## Table of Contents

1. [Server Setup](#server-setup)
2. [Database Setup](#database-setup)
3. [Transfer Project Files](#transfer-project-files)
4. [Backend Setup (Laravel)](#backend-setup-laravel)
5. [Frontend Setup (Next.js)](#frontend-setup-nextjs)
6. [Web Server Configuration](#web-server-configuration)
7. [Process Management](#process-management)
8. [Testing & Verification](#testing--verification)
9. [Troubleshooting](#troubleshooting)

## Server Setup

### 1. Update System Packages

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Install Required Software

```bash
# Install basic utilities
sudo apt install -y curl wget git unzip

# Install PHP and extensions for Laravel
sudo apt install -y php8.1 php8.1-fpm php8.1-mysql php8.1-xml php8.1-curl php8.1-zip php8.1-mbstring php8.1-gd php8.1-intl php8.1-bcmath

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js and npm
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install PM2 for process management
sudo npm install pm2 -g
```

## Database Setup

### 1. Install MySQL/MariaDB

```bash
# Install MySQL
sudo apt install -y mysql-server

# Or install MariaDB (alternative)
# sudo apt install -y mariadb-server
```

### 2. Secure MySQL Installation

```bash
sudo mysql_secure_installation
```

Follow the prompts to:
- Set root password
- Remove anonymous users
- Disallow root login remotely
- Remove test database
- Reload privilege tables

### 3. Create Database and User

```bash
# Connect to MySQL
sudo mysql -u root -p

# Create database
CREATE DATABASE your_database_name;

# Create user and grant privileges
CREATE USER 'your_db_user'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT ALL PRIVILEGES ON your_database_name.* TO 'your_db_user'@'localhost';
FLUSH PRIVILEGES;

# Exit MySQL
EXIT;
```

### 4. Test Database Connection

```bash
mysql -u your_db_user -p your_database_name
```

## Transfer Project Files

Choose one of the following methods to transfer your project:

### Option 1: Using SCP (Secure Copy)

```bash
# From your local machine
scp -r /path/to/local/project root@your-server-ip:/root/project-name
```

### Option 2: Using Rsync (Recommended for large projects)

```bash
# From your local machine
rsync -avz --progress /path/to/local/project root@your-server-ip:/root/project-name
```

### Option 3: Using Git (Recommended)

```bash
# On your remote server
cd /root
git clone https://github.com/yourusername/your-repository.git
cd your-repository
```

## Backend Setup (Laravel)

### 1. Navigate to Laravel Directory

```bash
cd /root/your-project/backend
# or wherever your Laravel project is located
```

### 2. Install Composer Dependencies

```bash
composer install --optimize-autoloader --no-dev
```

### 3. Set Up Environment Variables

```bash
# Copy environment file
cp .env.example .env

# Edit environment file
nano .env
```

Update your `.env` file with the following configuration:

```env
APP_NAME="Your App Name"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_strong_password

CACHE_DRIVER=file
FILESYSTEM_DRIVER=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
```

### 4. Generate Application Key

```bash
php artisan key:generate
```

### 5. Set Proper Permissions

```bash
# Set ownership
sudo chown -R www-data:www-data /root/your-project/backend

# Set permissions
sudo chmod -R 755 /root/your-project/backend
sudo chmod -R 775 /root/your-project/backend/storage
sudo chmod -R 775 /root/your-project/backend/bootstrap/cache
```

### 6. Run Database Migrations

```bash
php artisan migrate --force
```

### 7. Cache Configuration (Optional but recommended)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Frontend Setup (Next.js)

### 1. Navigate to Frontend Directory

```bash
cd /root/your-project/frontend
# or wherever your Next.js project is located
```

### 2. Install Dependencies

```bash
npm install
```

### 3. Set Up Environment Variables

```bash
# Copy environment file (if exists)
cp .env.example .env.local

# Edit environment file
nano .env.local
```

Example `.env.local` configuration:

```env
NEXT_PUBLIC_API_URL=http://your-domain.com/api
NEXT_PUBLIC_APP_URL=http://your-domain.com
NODE_ENV=production
```

### 4. Build the Application

```bash
npm run build
```

## Web Server Configuration

Choose either Apache or Nginx:

### Option 1: Apache Configuration

#### Install Apache

```bash
sudo apt install -y apache2
```

#### Configure Virtual Host

```bash
sudo nano /etc/apache2/sites-available/your-site.conf
```

Add the following configuration:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /root/your-project/backend/public

    <Directory /root/your-project/backend/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Proxy Next.js app
    ProxyPass /app/ http://localhost:3000/
    ProxyPassReverse /app/ http://localhost:3000/

    ErrorLog ${APACHE_LOG_DIR}/your-site_error.log
    CustomLog ${APACHE_LOG_DIR}/your-site_access.log combined
</VirtualHost>
```

#### Enable Site and Modules

```bash
sudo a2enmod rewrite
sudo a2enmod proxy
sudo a2enmod proxy_http
sudo a2ensite your-site.conf
sudo systemctl restart apache2
```

### Option 2: Nginx Configuration (Recommended)

#### Install Nginx

```bash
sudo apt install -y nginx
```

#### Configure Server Block

```bash
sudo nano /etc/nginx/sites-available/your-site
```

Add the following configuration:

```nginx
server {
    listen 80;
    server_name your-domain.com;

    # Laravel backend
    root /root/your-project/backend/public;
    index index.php index.html;

    # Handle Laravel routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Handle PHP files
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Proxy Next.js app
    location /app {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }

    # Block access to sensitive files
    location ~ /\.ht {
        deny all;
    }
}
```

#### Enable Site

```bash
sudo ln -s /etc/nginx/sites-available/your-site /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## Process Management

### Start Next.js with PM2

```bash
cd /root/your-project/frontend

# Start the application
pm2 start npm --name "nextjs-app" -- start

# Save PM2 configuration
pm2 save

# Set up PM2 to start on boot
pm2 startup
# Follow the instructions provided by the command above
```

### PM2 Useful Commands

```bash
# View running processes
pm2 list

# View logs
pm2 logs nextjs-app

# Restart application
pm2 restart nextjs-app

# Stop application
pm2 stop nextjs-app

# Delete application from PM2
pm2 delete nextjs-app
```

## Testing & Verification

### 1. Test Laravel Backend

```bash
# Test with Artisan serve (development)
cd /root/your-project/backend
php artisan serve --host=0.0.0.0 --port=8000

# Access: http://your-server-ip:8000
```

### 2. Test Next.js Frontend

```bash
# Check if PM2 is running the app
pm2 status

# Access: http://your-server-ip:3000 or http://your-domain.com/app
```

### 3. Test Database Connection

```bash
# From Laravel directory
php artisan tinker

# In tinker, test database connection
DB::connection()->getPdo();
```

### 4. Check Web Server Status

```bash
# For Apache
sudo systemctl status apache2

# For Nginx
sudo systemctl status nginx

# For PHP-FPM
sudo systemctl status php8.1-fpm
```

## Troubleshooting

### Common Issues and Solutions

#### Laravel Issues

1. **Permission Errors**
   ```bash
   sudo chown -R www-data:www-data /root/your-project/backend
   sudo chmod -R 775 /root/your-project/backend/storage
   sudo chmod -R 775 /root/your-project/backend/bootstrap/cache
   ```

2. **Database Connection Error**
   - Check `.env` database credentials
   - Ensure MySQL service is running: `sudo systemctl status mysql`
   - Test connection: `mysql -u your_db_user -p your_database_name`

3. **500 Server Error**
   - Check Laravel logs: `tail -f /root/your-project/backend/storage/logs/laravel.log`
   - Check web server error logs: `sudo tail -f /var/log/nginx/error.log`

#### Next.js Issues

1. **Build Failures**
   ```bash
   # Clear cache and reinstall
   rm -rf node_modules package-lock.json
   npm install
   npm run build
   ```

2. **PM2 Not Starting**
   ```bash
   # Check PM2 logs
   pm2 logs nextjs-app
   
   # Restart PM2
   pm2 restart nextjs-app
   ```

#### Web Server Issues

1. **Nginx Configuration Test**
   ```bash
   sudo nginx -t
   ```

2. **Apache Configuration Test**
   ```bash
   sudo apache2ctl configtest
   ```

### Log Files Locations

- **Laravel**: `/root/your-project/backend/storage/logs/laravel.log`
- **Nginx**: `/var/log/nginx/error.log` and `/var/log/nginx/access.log`
- **Apache**: `/var/log/apache2/error.log` and `/var/log/apache2/access.log`
- **MySQL**: `/var/log/mysql/error.log`
- **PM2**: `pm2 logs nextjs-app`

## Security Considerations

1. **Firewall Setup**
   ```bash
   sudo ufw allow ssh
   sudo ufw allow 80
   sudo ufw allow 443
   sudo ufw enable
   ```

2. **SSL Certificate (Recommended)**
   ```bash
   # Install Certbot
   sudo apt install certbot python3-certbot-nginx
   
   # Get SSL certificate
   sudo certbot --nginx -d your-domain.com
   ```

3. **Regular Updates**
   ```bash
   sudo apt update && sudo apt upgrade
   composer update
   npm update
   ```

## Backup Strategy

1. **Database Backup**
   ```bash
   mysqldump -u your_db_user -p your_database_name > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Code Backup**
   ```bash
   tar -czf project_backup_$(date +%Y%m%d_%H%M%S).tar.gz /root/your-project
   ```

## Additional Resources

- [Laravel Deployment Documentation](https://laravel.com/docs/deployment)
- [Next.js Deployment Documentation](https://nextjs.org/docs/deployment)
- [PM2 Documentation](https://pm2.keymetrics.io/docs/)
- [Nginx Documentation](https://nginx.org/en/docs/)

---

**Note**: Replace `your-domain.com`, `your-server-ip`, `your_database_name`, `your_db_user`, and other placeholders with your actual values throughout this guide.