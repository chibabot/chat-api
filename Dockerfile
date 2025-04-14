FROM php:8.2-fpm

WORKDIR /var/www

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Установка PHP расширений
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Установка composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Копирование файлов композера
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader

# Копирование остальных файлов
COPY . .

# Оптимизация автозагрузчика
RUN composer dump-autoload --optimize

# Установка прав
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

CMD php artisan serve --host=0.0.0.0 --port=8000

EXPOSE 8000