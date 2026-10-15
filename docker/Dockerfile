# Стенд SquirrelStock для разработки и тестов. Исходники берутся из www/ этого репозитория как есть, без заплаток:
# все исправления делаются коммитами в коде.
FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev libgmp-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip git \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install -j"$(nproc)" mysqli zip gmp gd \
 && a2enmod rewrite \
 && perl -0pi -e 's#(<Directory /var/www/>.*?AllowOverride) None#$1 All#s' /etc/apache2/apache2.conf \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
# зависимости отдельным слоем: правка кода не запускает composer заново
COPY www/composer.json www/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-autoloader --no-scripts
# rclone — копия в облако (App\SQS\BackupJob); отдельным слоем, после тяжелых слоев: расширения PHP и composer берутся из кэша
RUN apt-get update && apt-get install -y --no-install-recommends rclone ca-certificates && rm -rf /var/lib/apt/lists/*

COPY www/ ./
RUN composer dump-autoload --no-dev --optimize \
 && mkdir -p upload logs /var/www/squirrelstock-backup \
 && chown -R www-data:www-data /var/www/html /var/www/squirrelstock-backup

COPY docker/php.ini /usr/local/etc/php/conf.d/squirrelstock.ini
