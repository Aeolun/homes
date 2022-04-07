FROM php:8-cli

COPY . /app/
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get -y install cron zip
RUN curl -sSLf \
        -o /usr/local/bin/install-php-extensions \
        https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo pdo_mysql

RUN echo "30 10 * * * root /usr/local/bin/php /app/index.php retrieve --refresh > /proc/1/fd/1 2>&1" > /etc/crontab

WORKDIR /app/

RUN composer install

CMD ["/app/start.sh"]