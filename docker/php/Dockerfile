FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libonig-dev \
    libpq-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install zip mbstring xml pcntl pdo_mysql pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN groupadd -g 1000 appuser && useradd -u 1000 -g appuser -m appuser
USER appuser

WORKDIR /var/www/html

CMD ["bash"]
