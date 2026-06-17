FROM php:8.2-apache

RUN echo "rebuild-v5-1916"

RUN docker-php-ext-install pdo pdo_mysql mysqli
RUN a2enmod rewrite

# Netralisir file load mpm_event & mpm_worker: isi diganti komentar
# Jadi walau ke-load, ga ada modul MPM kedua yang aktif
RUN echo "# disabled" > /etc/apache2/mods-available/mpm_event.load && \
    echo "# disabled" > /etc/apache2/mods-available/mpm_worker.load && \
    a2dismod mpm_event 2>/dev/null; a2dismod mpm_worker 2>/dev/null; a2enmod mpm_prefork 2>/dev/null; true

RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/ && chmod -R 755 /var/www/html/

EXPOSE 80

CMD ["apache2-foreground"]
