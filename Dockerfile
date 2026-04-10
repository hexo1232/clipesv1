# Usando a imagem oficial do PHP com Apache
FROM php:8.2-apache

# 1. Instala dependências do sistema e do PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql

# 2. Instala o Composer (Copiando o binário da imagem oficial)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Habilita o mod_rewrite do Apache
RUN a2enmod rewrite

# 4. Define o diretório de trabalho
WORKDIR /var/www/html/

# 5. Copia os arquivos de dependências primeiro (otimiza o cache do Docker)
COPY composer.json composer.lock* ./

# 6. Instala as dependências (Cloudinary, etc.)
RUN composer install --no-interaction --no-dev --optimize-autoloader

# 7. Copia o restante dos arquivos do seu projeto
COPY . /var/www/html/

# 8. Define as permissões para o Apache
RUN chown -R www-data:www-data /var/www/html/

# 9. Ajusta a porta dinamicamente para o Render
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE 80

CMD ["apache2-foreground"]