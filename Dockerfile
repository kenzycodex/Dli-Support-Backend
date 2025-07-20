# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
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

# Configure Apache with CORS support
RUN a2enmod rewrite headers

# Create Apache virtual host configuration with CORS
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    \n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options -Indexes\n\
        \n\
        # CORS headers for your frontend\n\
        Header always set Access-Control-Allow-Origin "https://dli-support.vercel.app"\n\
        Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS, PATCH"\n\
        Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin"\n\
        Header always set Access-Control-Allow-Credentials "true"\n\
        Header always set Access-Control-Expose-Headers "Content-Disposition, Content-Length"\n\
    </Directory>\n\
    \n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Expose port
EXPOSE 80

# Create startup script that uses your composer scripts
RUN echo '#!/bin/bash\n\
set -e\n\
echo "🚀 Starting DLI Support Platform..."\n\
\n\
# Run your custom deploy-staging-nuclear script from composer.json\n\
composer run deploy-staging-nuclear\n\
\n\
echo "✅ Deployment completed, starting Apache..."\n\
apache2-foreground' > /start.sh && chmod +x /start.sh

# Environment variables
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV DEPLOYMENT_TYPE=staging-nuclear

# Start Apache
CMD ["/start.sh"]