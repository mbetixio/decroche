# Décroche in a container: FrankenPHP (PHP 8.5 behind Caddy, which streams the persona's replies as they are written),
# Kyutai's Pocket TTS, which voices the personas offline, and edge-tts (Microsoft Edge's neural voices) as a fallback.
FROM dunglas/frankenphp:1.13.5-php8.5-trixie

# PHP extensions (zip for Composer), and Python for edge-tts.
RUN install-php-extensions pdo_sqlite intl opcache zip \
    && apt-get update \
    && apt-get install -y --no-install-recommends python3 python3-venv \
    && python3 -m venv /opt/edge-tts \
    && /opt/edge-tts/bin/pip install --no-cache-dir edge-tts==7.3.1 \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Pocket TTS's standalone binary (no Python, no PyTorch), checked against the checksums of its release.
ARG TARGETARCH
RUN case "$TARGETARCH" in \
        arm64) arch=aarch64; sum=2b9a727bffd86ea6d73370d675cbd91a7db118f11b7f5a60343c2d5da12b187a ;; \
        *) arch=x86_64; sum=9abff0aa138a804e44052952d8c3bdaae247fb267ca8f4b83fe178897d1a0559 ;; \
    esac \
    && file="pocket-tts-3.3.0-$arch-unknown-linux-gnu.tar.gz" \
    && curl -fsSL -o "/tmp/$file" "https://github.com/kyutai-labs/pocket-tts/releases/download/v4.0.1/$file" \
    && echo "$sum  /tmp/$file" | sha256sum -c - \
    && mkdir -p /opt/pocket-tts && tar xzf "/tmp/$file" -C /opt/pocket-tts --strip-components=1 \
    && rm -f "/tmp/$file"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-decroche.ini

WORKDIR /app
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    EDGE_TTS=/opt/edge-tts/bin/edge-tts \
    POCKET_TTS=/opt/pocket-tts/pocket-tts \
    POCKET_TTS_URL=http://127.0.0.1:8100 \
    POCKET_VOICES_URL=http://127.0.0.1/voices \
    HF_HOME=/var/decroche/hf \
    DATABASE_URL=sqlite:////var/decroche/data.db \
    SESSION_SAVE_PATH=/var/decroche/sessions \
    VOICES_DIR=/var/decroche/voices \
    SERVER_NAME=:80 \
    DEFAULT_URI=http://localhost:7077 \
    MCP_ALLOWED_HOSTS=localhost,127.0.0.1 \
    TRUSTED_PROXIES=

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

# The database, the sessions, the voices already synthesized, Pocket TTS's models, and the secrets generated on
# first boot.
VOLUME /var/decroche
EXPOSE 80
ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
