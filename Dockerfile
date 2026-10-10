FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends unzip \
 && docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --optimize

EXPOSE 80
