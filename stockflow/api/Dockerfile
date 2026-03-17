FROM php:8.2-cli

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

COPY . .

EXPOSE 8005

CMD ["php", "-S", "0.0.0.0:8005", "-t", "public"]
