FROM php:8-cli

COPY . /app/

RUN echo "0 0 * * * root php /app/index.php retrieve > /proc/1/fd/1 2>&1"

RUN apt-get update && apt-get install cron

WORKDIR /app/

ENTRYPOINT ["/usr/sbin/cron", "-f"]