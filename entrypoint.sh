#!/bin/bash
mkdir -p /var/www/html/uploads/covers /var/www/html/uploads/foto_profil /var/www/html/ebooks
chown -R www-data:www-data /var/www/html/uploads /var/www/html/ebooks
chmod -R 777 /var/www/html/uploads /var/www/html/ebooks
exec apache2-foreground
