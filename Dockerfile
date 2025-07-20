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
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Create supervisor configuration for queue workers
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
command=php /var/www/html/artisan queue:work --queue=emails,bulk-emails,admin-reports,password-resets --tries=3 --timeout=60 --verbose\n\
autostart=true\n\
autorestart=true\n\
user=www-data\n\
numprocs=1\n\
redirect_stderr=true\n\
stdout_logfile=/var/www/html/storage/logs/queue.log\n\
stderr_logfile=/var/www/html/storage/logs/queue-error.log' > /etc/supervisor/conf.d/laravel.conf

# Expose port
EXPOSE 80

# Create startup script with supervisor (Apache + Queue Workers)
RUN echo '#!/bin/bash\n\
set -e\n\
echo "🚀 Starting DLI Support Platform..."\n\
\n\
# Run your custom deploy-staging script from composer.json\n\
composer run deploy-staging\n\
\n\
echo "📧 Starting queue workers..."\n\
# Start supervisor (Apache + Queue workers)\n\
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/laravel.conf' > /start.sh && chmod +x /start.sh

# Start with supervisor
CMD ["/start.sh"]