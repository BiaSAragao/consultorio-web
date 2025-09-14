# Use a imagem oficial do PHP com Apache
FROM php:8.2-apache

# Instale o driver PDO para PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Copie todo o projeto para a pasta do servidor Apache
COPY . /var/www/html/

# Exponha a porta que o Render vai usar
EXPOSE 80
