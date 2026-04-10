# Usando a imagem oficial do PHP com Apache
FROM php:8.2-apache

# Instala as bibliotecas necessárias para o PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Habilita o mod_rewrite do Apache (comum em projetos PHP)
RUN a2enmod rewrite

# Copia os arquivos do seu projeto para dentro do container
COPY . /var/www/html/

# Define as permissões para o Apache ler os arquivos
RUN chown -R www-data:www-data /var/www/html/

# O Render define a porta dinamicamente via variável de ambiente $PORT
# Vamos garantir que o Apache escute a porta correta
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Expõe a porta (informativo)
EXPOSE 80

# Inicia o Apache
CMD ["apache2-foreground"]