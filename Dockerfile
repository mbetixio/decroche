# Practicall in a container: FrankenPHP (PHP 8.5 behind Caddy, which streams the persona's replies as they are written)
# and edge-tts, which synthesizes Microsoft Edge's neural voices.
FROM dunglas/frankenphp:1.13.5-php8.5-trixie

# PHP extensions (zip for Composer), and Python for edge-tts.
RUN install-php-extensions pdo_sqlite intl opcache zip \
    && apt-get update \
    && apt-get install -y --no-install-recommends python3 python3-venv \
    && python3 -m venv /opt/edge-tts \
    && /opt/edge-tts/bin/pip install --no-cache-dir edge-tts==7.3.1 \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-practicall.ini

WORKDIR /app
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    EDGE_TTS=/opt/edge-tts/bin/edge-tts \
    DATABASE_URL=sqlite:////var/practicall/data.db \
    SESSION_SAVE_PATH=/var/practicall/sessions \
    VOICES_DIR=/var/practicall/voices \
    SERVER_NAME=:80

# The dependencies first (a layer cached as long as composer.lock does not change), then the code.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && rm -rf var/* \
    && php bin/console importmap:install \
    && php bin/console asset-map:compile \
    && php bin/console cache:warmup \
    && chmod +x docker/entrypoint.sh

# The database, the sessions, the voices already synthesized, and the secrets generated on first boot.
VOLUME /var/practicall
EXPOSE 80
ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
