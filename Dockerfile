FROM php:8.2-apache

# Descargar script de instalación de extensiones (más robusto)
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Instalar librerías necesarias para GD con soporte WebP
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libxpm-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configurar e instalar GD manualmente con soporte WebP
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    --with-xpm \
    && docker-php-ext-install -j$(nproc) gd

# Instalar otras extensiones PHP necesarias
RUN install-php-extensions pdo pdo_pgsql pgsql imap zip

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Habilitar mod_rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

EXPOSE 80

