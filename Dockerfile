FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli

# Fix: More than one MPM loaded — paksa cuma prefork yang aktif
RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true
RUN a2enmod mpm_prefork
RUN a2enmod rewrite

RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/
RUN chmod -R 755 /var/www/html/

EXPOSE 80

CMD ["apache2-foreground"]
