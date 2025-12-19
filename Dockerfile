FROM php:8.2-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    autoconf \
    g++ \
    make \
    linux-headers \
    bash \
    curl

# Install pcov for code coverage
RUN pecl install pcov && docker-php-ext-enable pcov

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure git safe directory
RUN git config --global --add safe.directory /app

# Set working directory
WORKDIR /app

# Set environment
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/app/vendor/bin:${PATH}"

# Install project dependencies on build (optional, can be done at runtime)
# This ensures the container is ready to run tests immediately
# RUN composer install --no-interaction --prefer-dist --no-scripts || true
