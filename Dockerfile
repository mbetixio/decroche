# Sparring : FrankenPHP (PHP 8.5 + Caddy, HTTPS automatique, réponses en flux) et edge-tts pour les voix.
FROM dunglas/frankenphp:1.13.5-php8.5-trixie

# Les extensions PHP, Python pour edge-tts (les voix neuronales d'Edge), sqlite3 pour inspecter la base.
RUN install-php-extensions pdo_sqlite intl opcache zip gd \
    && apt-get update \
    && apt-get install -y --no-install-recommends python3 python3-venv sqlite3 \
    && python3 -m venv /opt/edge-tts \
    && /opt/edge-tts/bin/pip install --no-cache-dir edge-tts==7.3.1 \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-sparring.ini

WORKDIR /app
ENV APP_ENV=prod APP_DEBUG=0 COMPOSER_ALLOW_SUPERUSER=1 EDGE_TTS=/opt/edge-tts/bin/edge-tts

# Les dépendances d'abord (couche mise en cache tant que composer.lock ne change pas), puis le code.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && rm -rf var/* \
    && php bin/console cache:warmup \
    && chmod +x docker/entrypoint.sh

VOLUME /var/sparring
ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
