# Use a imagem oficial do PHP com Apache
FROM php:8.2-apache

# Instale o driver PDO para PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# ----------------------------
# Adições para GD (redimensionamento de imagens)
# ----------------------------
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libxpm-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copie todo o projeto para a pasta do servidor Apache
COPY . /var/www/html/

# Defina permissões para que o Apache possa escrever arquivos
RUN chown -R www-data:www-data /var/www/html

# Exponha a porta que o Render vai usar
EXPOSE 80
