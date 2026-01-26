FROM php:8.2-apache

# Descargar script de instalación de extensiones (más robusto)
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Instalar extensiones y dependencias
RUN install-php-extensions pdo pdo_pgsql pgsql imap zip

# Instalar herramientas básicas y Composer
RUN apt-get update && apt-get install -y \
    unzip \
    && rm -rf /var/lib/apt/lists/* \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Habilitar mod_rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

EXPOSE 80

