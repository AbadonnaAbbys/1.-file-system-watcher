FROM php:8.3-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    zip \
    libzip-dev \
    oniguruma-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    git

# Configure GD extension
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Install extensions
RUN docker-php-ext-install pdo pdo_mysql zip gd

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory for Laravel application
WORKDIR /app

# Create watched directory and other required directories
RUN mkdir -p /app/watched && chmod -R 777 /app/watched && \
    mkdir -p /app/storage/framework/sessions /app/storage/framework/views /app/storage/framework/cache /app/storage/logs && \
    mkdir -p /app/bootstrap/cache && \
    chmod -R 777 /app/storage && \
    chmod -R 777 /app/bootstrap/cache

# Create startup script
RUN echo '#!/bin/sh' > /usr/local/bin/start.sh && \
    echo 'if [ -f /app/composer.json ]; then' >> /usr/local/bin/start.sh && \
    echo '  if [ ! -d /app/vendor ] || [ ! -f /app/vendor/autoload.php ]; then' >> /usr/local/bin/start.sh && \
    echo '    composer install --no-interaction --no-progress' >> /usr/local/bin/start.sh && \
    echo '  fi' >> /usr/local/bin/start.sh && \
    echo '  if [ ! -f /app/.env ] && [ -f /app/.env.example ]; then' >> /usr/local/bin/start.sh && \
    echo '    cp /app/.env.example /app/.env' >> /usr/local/bin/start.sh && \
    echo '    php artisan key:generate --ansi' >> /usr/local/bin/start.sh && \
    echo '  fi' >> /usr/local/bin/start.sh && \
    echo 'fi' >> /usr/local/bin/start.sh && \
    echo 'exec php -S 0.0.0.0:9000 -t /app/public' >> /usr/local/bin/start.sh && \
    chmod +x /usr/local/bin/start.sh

# Use startup script
CMD ["/usr/local/bin/start.sh"]