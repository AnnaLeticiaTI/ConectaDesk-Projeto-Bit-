FROM php:8.3-cli

RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html
COPY . .

EXPOSE 8000

CMD ["sh", "-c", "php database/seed.php && php -S 0.0.0.0:8000 router.php"]
