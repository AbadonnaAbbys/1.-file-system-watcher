
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

# Set working directory
WORKDIR /app

# Copy only composer files first for better layer caching
COPY composer.* ./

# Check if composer.json is valid, if not create a basic one
RUN if ! composer validate --no-check-publish 2>/dev/null; then \
    echo '{"name": "laravel/laravel", "type": "project", "require": {"php": "^8.3"}}' > composer.json; \
    fi

# Create a Laravel project structure if needed
RUN if [ ! -d "app" ]; then \
    composer create-project --prefer-dist laravel/laravel:^10.0 temp && \
    mv temp/* . && \
    mv temp/.* . 2>/dev/null || true && \
    rmdir temp; \
    fi

# Copy the rest of the application code
COPY . .

# Make sure storage directory is writable
RUN mkdir -p storage/framework/{sessions,views,cache} && \
    chmod -R 777 storage

# Command to run PHP-FPM server
CMD ["php-fpm"]