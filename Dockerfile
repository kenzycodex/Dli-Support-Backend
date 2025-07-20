# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Install system dependencies + supervisor for queue workers
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    supervisor \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies without running scripts
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application code
COPY . .

# Run composer scripts now that artisan is available
RUN composer run-script post-autoload-dump

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Configure Apache
RUN a2enmod rewrite

# Create Apache virtual host configuration
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    ServerName localhost\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# FIXED: Create clean supervisor configuration without logging conflicts
RUN echo '[supervisord]\n\
nodaemon=true\n\
silent=true\n\
user=root\n\
logfile=/dev/null\n\
logfile_maxbytes=0\n\
pidfile=/var/run/supervisord.pid\n\
\n\
[program:apache2]\n\
command=/usr/sbin/apache2ctl -D FOREGROUND\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
stderr_logfile=/dev/stderr\n\
stderr_logfile_maxbytes=0\n\
\n\
[program:laravel-queue]\n\
command=php /var/www/html/artisan queue:work --queue=emails,bulk-emails,admin-reports,password-resets --tries=3 --timeout=60 --sleep=3 --quiet\n\
autostart=true\n\
autorestart=true\n\
user=www-data\n\
numprocs=1\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
stderr_logfile=/dev/stderr\n\
stderr_logfile_maxbytes=0' > /etc/supervisor/conf.d/laravel.conf

# Expose port
EXPOSE 80

# Create startup script with clean output
RUN echo '#!/bin/bash\n\
set -e\n\
echo "🚀 Starting DLI Support Platform..."\n\
\n\
# Run your custom deploy-staging script from composer.json\n\
composer run deploy-staging\n\
\n\
echo "📧 Starting services (Apache + Queue Workers)..."\n\
echo "✅ Application ready at http://localhost"\n\
\n\
# Start supervisor with clean logging\n\
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/laravel.conf' > /start.sh && chmod +x /start.sh

# Start with supervisor
CMD ["/start.sh"]