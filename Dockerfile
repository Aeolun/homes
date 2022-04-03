FROM php:8-cli

COPY . /app/
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get -y install cron zip

RUN echo "0 0 * * * root php /app/index.php retrieve > /proc/1/fd/1 2>&1" > /etc/crontab

WORKDIR /app/

RUN composer install

ENTRYPOINT ["/usr/sbin/cron", "-f"]