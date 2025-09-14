# Use a imagem oficial do PHP com Apache
FROM php:8.2-apache

# Copie todo o projeto para a pasta do servidor Apache
COPY . /var/www/html/

# Exponha a porta que o Render vai usar
EXPOSE 80