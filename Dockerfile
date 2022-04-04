FROM php:8-cli

COPY . /app/
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get -y install cron zip

RUN echo "30 10 * * * root /usr/local/bin/php /app/index.php retrieve --refresh > /proc/1/fd/1 2>&1" > /etc/crontab

WORKDIR /app/

RUN composer install

CMD ["/app/start.sh"]