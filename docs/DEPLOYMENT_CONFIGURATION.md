# Render Deployment Configuration

## Build Command
```bash
docker build -t dli-support-platform .
```

## Start Command
```bash
/start.sh
```

## Complete Render Service Configuration

### Web Service Settings

| Setting | Value |
|---------|-------|
| **Runtime** | Docker |
| **Build Command** | `docker build -t dli-support-platform .` |
| **Start Command** | `/start.sh` |
| **Port** | `80` |
| **Health Check Path** | `/api/health` |

### Environment Variables (Required)

```bash
# Application
APP_NAME="DLI Support Platform"
APP_ENV=production
APP_KEY=base64:YOUR_32_CHARACTER_KEY_HERE
APP_DEBUG=false
APP_URL=https://your-app-name.onrender.com

# Database (use Render PostgreSQL or external MySQL)
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=your-database-name
DB_USERNAME=your-db-username
DB_PASSWORD=your-db-password

# Queue & Cache
QUEUE_CONNECTION=database
CACHE_DRIVER=file
SESSION_DRIVER=database

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="DLI Support"

# Deployment Type
DEPLOYMENT_TYPE=production
```

### Optional Environment Variables

```bash
# If you want to run migrations and seeders
DEPLOYMENT_TYPE=production-with-db

# For staging deployment
DEPLOYMENT_TYPE=staging

# Custom PHP settings
PHP_MEMORY_LIMIT=512M
PHP_MAX_EXECUTION_TIME=300
```

## Alternative Build Commands (if needed)

### If using specific build context:
```bash
docker build --no-cache -t dli-support-platform .
```

### If you need to pass build arguments:
```bash
docker build --build-arg APP_ENV=production -t dli-support-platform .
```

## Dockerfile Optimization Tips for Render

1. **Multi-stage builds** (if image size is a concern):
   ```dockerfile
   # Add to your Dockerfile if needed
   FROM php:8.2-apache-slim AS production
   # Copy only production files
   ```

2. **Reduce image size** by combining RUN commands (already done in your Dockerfile)

3. **Health check endpoint** - ensure your Laravel app has `/api/health` route

## Troubleshooting

### If build fails:
- Check Render build logs
- Ensure all required files are in repository
- Verify Dockerfile syntax

### If start fails:
- Check environment variables are set
- Verify database connection
- Check `/start.sh` script permissions

### Common Issues:
1. **Database connection**: Ensure DB credentials are correct
2. **File permissions**: Should be handled by Dockerfile
3. **Queue workers**: Monitor via Render logs for any queue processing issues

## Monitoring

Your setup includes:
- **Supervisor** for process management
- **Queue workers** running automatically
- **Health checks** every 30 seconds
- **Apache + PHP-FPM** for web serving

Check Render logs to monitor:
```bash
# Queue processing
grep "queue" logs

# Application errors  
grep "ERROR" logs

# Health check status
grep "health" logs
```