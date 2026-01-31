FROM richarvey/nginx-php-fpm:3.1.6

WORKDIR /var/www/html

COPY . /var/www/html

# Nginx + PHP-FPM config
COPY docker/nginx.conf /etc/nginx/sites-available/default.conf

# Startup script
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
