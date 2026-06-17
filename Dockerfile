FROM php:8.2-apache

RUN echo "rebuild-v6-1920"

RUN docker-php-ext-install pdo pdo_mysql mysqli
RUN a2enmod rewrite

# Netralisir mpm_event & mpm_worker: kosongin BAIK .load MAUPUN .conf
RUN echo "# disabled" > /etc/apache2/mods-available/mpm_event.load && \
    echo "# disabled" > /etc/apache2/mods-available/mpm_event.conf && \
    echo "# disabled" > /etc/apache2/mods-available/mpm_worker.load && \
    echo "# disabled" > /etc/apache2/mods-available/mpm_worker.conf && \
    a2dismod mpm_event 2>/dev/null; a2dismod mpm_worker 2>/dev/null; a2enmod mpm_prefork 2>/dev/null; true

RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/ && chmod -R 755 /var/www/html/

RUN mkdir -p /var/www/html/uploads/covers \
    /var/www/html/uploads/foto_profil \
    /var/www/html/ebooks \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 777 /var/www/html/uploads

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
EXPOSE 80
CMD ["/entrypoint.sh"]
