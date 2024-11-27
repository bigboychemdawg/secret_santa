FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    pkg-config \
    && docker-php-ext-install pdo_sqlite

WORKDIR /app

COPY . /app

RUN chmod -R 777 /app

CMD ["php", "-S", "0.0.0.0:80", "bot.php"]
