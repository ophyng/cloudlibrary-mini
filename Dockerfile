FROM php:8.2-apache

# Cache bust v3 - paksa rebuild dari sini
RUN echo "rebuild-2026-06-17-1906"

RUN docker-php-ext-install pdo pdo_mysql mysqli

# Hapus PAKSA semua MPM, enable prefork only
RUN rm -rf /etc/apache2/mods-enabled/mpm_event.load \
    /etc/apache2/mods-enabled/mpm_event.conf \
    /etc/apache2/mods-enabled/mpm_worker.load \
    /etc/apache2/mods-enabled/mpm_worker.conf \
    /etc/apache2/mods-enabled/mpm_prefork.load \
    /etc/apache2/mods-enabled/mpm_prefork.conf
RUN ls -la /etc/apache2/mods-enabled/ | grep mpm || echo "NO MPM ENABLED - GOOD"
RUN a2enmod mpm_prefork
RUN a2enmod rewrite
RUN apachectl -V | grep MPM

RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/

EXPOSE 80

CMD ["apache2-foreground"]
