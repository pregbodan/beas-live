FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html \
    PORT=10000

WORKDIR /var/www/html

RUN a2enmod rewrite headers \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
       libfreetype6-dev \
       libjpeg62-turbo-dev \
       libpng-dev \
       libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip \
    && rm -rf /var/lib/apt/lists/*

RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && sed -ri "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf \
    && sed -ri "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/*.conf

COPY . /var/www/html

RUN mkdir -p /var/www/html/uploads/profiles /var/www/html/uploads/biometric \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/api /var/www/html/modules \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 10000

CMD ["apache2-foreground"]
