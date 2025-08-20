# Production Docker Setup for Linux Server

## 1. Production Docker Compose (docker-compose.prod.yml)

```yaml
version: '3.8'

services:
  # Main Laravel Application
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: dli-support-app
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"  # Add HTTPS support
    environment:
      - APP_NAME=DLI Support Platform
      - APP_ENV=production
      - APP_DEBUG=false
      - APP_URL=https://yourdomain.com  # Change to your actual domain
      - APP_FRONTEND_URL=https://yourdomain.com
      
      # Database Configuration
      - DB_CONNECTION=mysql
      - DB_HOST=db
      - DB_PORT=3306
      - DB_DATABASE=dli_student_db
      - DB_USERNAME=dli_user
      - DB_PASSWORD=${DB_PASSWORD}  # Use secrets
      
      # Production Email (Choose one)
      - MAIL_MAILER=smtp
      - MAIL_HOST=${MAIL_HOST}
      - MAIL_PORT=${MAIL_PORT}
      - MAIL_USERNAME=${MAIL_USERNAME}
      - MAIL_PASSWORD=${MAIL_PASSWORD}
      - MAIL_ENCRYPTION=tls
      - MAIL_FROM_ADDRESS=noreply@yourdomain.com
      - MAIL_FROM_NAME=DLI Support Platform
      
      # Security Settings
      - TEMPORARY_PASSWORD_EXPIRY_DAYS=7
      - MAX_LOGIN_ATTEMPTS=5
      - LOCKOUT_DURATION=30
      
      # Queue & Cache (Use Redis for production)
      - QUEUE_CONNECTION=redis
      - CACHE_DRIVER=redis
      - SESSION_DRIVER=redis
      - REDIS_HOST=redis
      
    volumes:
      - app_storage:/var/www/html/storage
      - app_public:/var/www/html/public
      # Add SSL certificates
      - ./ssl:/etc/ssl/certs/custom
    depends_on:
      - db
      - redis
    networks:
      - dli-network

  # MySQL Database
  db:
    image: mysql:8.0
    container_name: dli-support-db
    restart: unless-stopped
    environment:
      - MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
      - MYSQL_DATABASE=dli_student_db
      - MYSQL_USER=dli_user
      - MYSQL_PASSWORD=${DB_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./backups:/backups  # For database backups
    networks:
      - dli-network
    command: --default-authentication-plugin=mysql_native_password --innodb-buffer-pool-size=512M

  # Redis for Caching & Queues
  redis:
    image: redis:7-alpine
    container_name: dli-support-redis
    restart: unless-stopped
    volumes:
      - redis_data:/data
    networks:
      - dli-network
    command: redis-server --appendonly yes --maxmemory 256mb --maxmemory-policy allkeys-lru

  # Nginx Reverse Proxy (Optional but recommended)
  nginx:
    image: nginx:alpine
    container_name: dli-support-nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
      - ./ssl:/etc/ssl/certs
    depends_on:
      - app
    networks:
      - dli-network

volumes:
  mysql_data:
    driver: local
  redis_data:
    driver: local
  app_storage:
    driver: local
  app_public:
    driver: local

networks:
  dli-network:
    driver: bridge
```

## 2. Environment Variables (.env.production)

```bash
# Create this file with your production secrets
DB_PASSWORD=your_super_secure_db_password_2025
MYSQL_ROOT_PASSWORD=your_super_secure_root_password_2025

# Email Configuration (Choose your provider)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-app-password

# Or for SendGrid
# MAIL_HOST=smtp.sendgrid.net
# MAIL_USERNAME=apikey
# MAIL_PASSWORD=your-sendgrid-api-key

# Application Key (generate new one for production)
APP_KEY=base64:your-production-app-key
```

## 3. Deployment Script (deploy.sh)

```bash
#!/bin/bash
set -e

echo "🚀 Deploying DLI Support Platform to Production..."

# Create necessary directories
mkdir -p ssl backups logs

# Pull latest code
git pull origin main

# Build and start containers
docker-compose -f docker-compose.prod.yml down
docker-compose -f docker-compose.prod.yml build --no-cache
docker-compose -f docker-compose.prod.yml up -d

# Wait for database to be ready
echo "⏳ Waiting for database..."
sleep 30

# Run migrations and optimizations
docker-compose -f docker-compose.prod.yml exec app composer run deploy-with-db

echo "✅ Deployment complete!"
echo "🌐 Application running at: http://your-server-ip"
```

## 4. SSL Certificate Setup (Recommended)

### Option A: Let's Encrypt (Free)
```bash
# Install certbot on your server
sudo apt update
sudo apt install certbot

# Get certificate
sudo certbot certonly --standalone -d yourdomain.com

# Copy certificates to your project
cp /etc/letsencrypt/live/yourdomain.com/fullchain.pem ./ssl/
cp /etc/letsencrypt/live/yourdomain.com/privkey.pem ./ssl/
```

### Option B: Self-signed (For testing)
```bash
mkdir -p ssl
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout ssl/privkey.pem \
    -out ssl/fullchain.pem \
    -subj "/C=NG/ST=Lagos/L=Lagos/O=DLI/CN=yourdomain.com"
```

## 5. Server Preparation Commands

```bash
# SSH into your server
ssh root@your-server-ip

# Update system
apt update && apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh

# Install Docker Compose
curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose

# Clone your repository
git clone https://github.com/your-username/dlisupport.git
cd dlisupport

# Make deploy script executable
chmod +x deploy.sh
```

## 6. Production Monitoring

### Health Check Endpoint
Add this to your routes/api.php:
```php
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
        'services' => [
            'database' => DB::connection()->getPdo() ? 'up' : 'down',
            'cache' => Cache::store()->getRedis()->ping() ? 'up' : 'down'
        ]
    ]);
});
```

### Log Monitoring
```bash
# View application logs
docker-compose -f docker-compose.prod.yml logs -f app

# View specific service logs
docker-compose -f docker-compose.prod.yml logs -f db
docker-compose -f docker-compose.prod.yml logs -f redis
```

## 7. Backup Strategy

```bash
# Database backup script (backup.sh)
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
docker-compose -f docker-compose.prod.yml exec db mysqldump -u dli_user -p dli_student_db > backups/backup_$DATE.sql

# Add to crontab for daily backups
# 0 2 * * * /path/to/your/project/backup.sh
```

## 8. Security Considerations

- **Firewall**: Only open ports 22 (SSH), 80 (HTTP), 443 (HTTPS)
- **SSH**: Use key-based authentication, disable password auth
- **Database**: Never expose MySQL port 3306 to public
- **Secrets**: Use Docker secrets or external secret management
- **Updates**: Regular security updates for base images

## 9. Final Production Checklist

- [ ] Domain name pointed to server IP
- [ ] SSL certificate configured
- [ ] Email provider configured and tested
- [ ] Database backups scheduled
- [ ] Monitoring/logging setup
- [ ] Firewall configured
- [ ] SSH hardened
- [ ] Environment variables secured
- [ ] Health checks working
- [ ] Queue workers running