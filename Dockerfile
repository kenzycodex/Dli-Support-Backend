# Production Dockerfile for DLI Support Platform (Railway/Single Container Deployment)
FROM php:8.2-apache AS base

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    supervisor \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure Apache
RUN a2enmod rewrite headers

# Create Apache virtual host for Laravel
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    ServerName localhost\n\
    \n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options -Indexes\n\
        \n\
        # Security headers\n\
        Header always set X-Content-Type-Options nosniff\n\
        Header always set X-Frame-Options DENY\n\
        Header always set X-XSS-Protection "1; mode=block"\n\
        \n\
        # CORS headers for API\n\
        Header always set Access-Control-Allow-Origin "*"\n\
        Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS, PATCH"\n\
        Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"\n\
    </Directory>\n\
    \n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application code
COPY . .

# Create required directories and set permissions
RUN mkdir -p storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/public \
    storage/app/reports \
    storage/app/guides \
    bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache \
    && chmod -R 755 /var/www/html/public

# Run composer scripts
RUN composer run-script post-autoload-dump

# Create supervisor configuration for queue workers with ALL queues
RUN echo '[supervisord]\n\
nodaemon=true\n\
user=root\n\
logfile=/var/log/supervisor/supervisord.log\n\
pidfile=/var/run/supervisord.pid\n\
\n\
[program:apache2]\n\
command=/usr/sbin/apache2ctl -D FOREGROUND\n\
autostart=true\n\
autorestart=true\n\
stderr_logfile=/var/log/apache2/error.log\n\
stdout_logfile=/var/log/apache2/access.log\n\
\n\
[program:laravel-queue]\n\
command=php /var/www/html/artisan queue:work --queue=emails,bulk-emails,admin-reports,password-resets,status-changes --tries=3 --timeout=300 --max-jobs=1000 --max-time=3600\n\
autostart=true\n\
autorestart=true\n\
user=www-data\n\
numprocs=2\n\
redirect_stderr=true\n\
stdout_logfile=/var/www/html/storage/logs/queue.log\n\
stderr_logfile=/var/www/html/storage/logs/queue-error.log' > /etc/supervisor/conf.d/laravel.conf

# Create startup script with deployment control to prevent re-running
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
echo "🚀 Starting DLI Support Platform..."\n\
\n\
# Only run deployment once using a flag file\n\
if [ ! -f /tmp/.deployed ]; then\n\
    # Set deployment type based on environment variable\n\
    DEPLOYMENT_TYPE=${DEPLOYMENT_TYPE:-production}\n\
    \n\
    echo "📦 Running deployment: $DEPLOYMENT_TYPE"\n\
    \n\
    # Use your composer scripts for deployment\n\
    case $DEPLOYMENT_TYPE in\n\
        "production")\n\
            composer run deploy\n\
            ;;\n\
        "production-with-db")\n\
            composer run deploy-with-db\n\
            ;;\n\
        "staging")\n\
            composer run deploy-staging\n\
            ;;\n\
        *)\n\
            echo "Using default production deployment"\n\
            composer run deploy\n\
            ;;\n\
    esac\n\
    \n\
    # Mark as deployed\n\
    touch /tmp/.deployed\n\
    echo "✅ Initial deployment completed"\n\
else\n\
    echo "📋 Application already deployed, skipping deployment steps"\n\
fi\n\
\n\
echo "🔧 Setting up queue system..."\n\
php artisan queue:restart\n\
\n\
echo "🏥 Running health check..."\n\
composer run check-health || echo "⚠️ Health check completed with warnings"\n\
\n\
echo "📧 Testing email configuration..."\n\
php artisan config:show mail.default || echo "⚠️ Mail config check completed"\n\
php artisan config:show queue.default || echo "⚠️ Queue config check completed"\n\
\n\
echo "✅ Application ready! Starting services (Apache + Queue Workers)..."\n\
\n\
# Start supervisor (Apache + Queue workers)\n\
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/laravel.conf\n\
' > /start.sh && chmod +x /start.sh

# Enhanced health check script
RUN echo '#!/bin/bash\n\
# Check if application is responding\n\
curl -f http://localhost/api/health || exit 1\n\
\n\
# Check if queue workers are running\n\
if ! pgrep -f "queue:work" > /dev/null; then\n\
    echo "Queue workers not running"\n\
    exit 1\n\
fi\n\
\n\
echo "Health check passed: App responding and queue workers active"\n\
' > /health-check.sh && chmod +x /health-check.sh

# Expose port
EXPOSE 80

# Add health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD /health-check.sh

# Environment variables with defaults
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV SESSION_DRIVER=database
ENV CACHE_DRIVER=file
ENV QUEUE_CONNECTION=database
ENV DEPLOYMENT_TYPE=production

# Labels
LABEL maintainer="DLI Support Team"
LABEL description="DLI Student Support Platform - Single Container with Queue Workers"
LABEL version="1.0.0"

# Start the application
CMD ["/start.sh"]