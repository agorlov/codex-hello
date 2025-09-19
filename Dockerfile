# Dockerfile описывает контейнер для запуска Symfony-приложения Codex Hello.
FROM composer:2 AS vendor

WORKDIR /app

ARG INSTALL_DEV=true

COPY composer.json composer.lock symfony.lock ./
RUN if [ "$INSTALL_DEV" = "true" ]; then \
        composer install --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader; \
    else \
        composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader; \
    fi

COPY . ./
RUN if [ "$INSTALL_DEV" = "true" ]; then \
        composer install --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader; \
    else \
        composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader; \
    fi

FROM php:8.4-cli AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

ARG APP_ENV=dev
ARG APP_DEBUG=1

ENV APP_ENV=${APP_ENV} \
    APP_DEBUG=${APP_DEBUG}

COPY --from=vendor /app /app

EXPOSE 8000

CMD ["sh", "-c", "php bin/console app:sqlite:migrate && php -S 0.0.0.0:8000 -t public public/index.php"]
