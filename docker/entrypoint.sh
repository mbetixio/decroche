#!/bin/sh
# On start: the volume's folders, the instance's own secrets (generated once, kept in the volume), the database
# migrated, then the server. The first visit opens the onboarding; PRACTICALL_DEMO=1 loads the example company instead.
set -e

mkdir -p /var/practicall/sessions /var/practicall/voices

if [ ! -f /var/practicall/secrets.env ]; then
    printf 'APP_SECRET=%s\nMCP_STATE_KEY=%s\n' "$(php -r 'echo bin2hex(random_bytes(16));')" "$(php -r 'echo bin2hex(random_bytes(24));')" > /var/practicall/secrets.env
    chmod 600 /var/practicall/secrets.env
fi
set -a
. /var/practicall/secrets.env
set +a

if [ -z "${AI_API_KEY:-}" ]; then
    echo "Warning: AI_API_KEY is empty, the personas cannot answer. Set it in your environment or .env file." >&2
fi

if [ "${PRACTICALL_DEMO:-0}" = "1" ]; then
    php bin/console app:install --demo --language="${PRACTICALL_LANGUAGE:-en}" --no-interaction
else
    php bin/console app:install --no-interaction
fi

# The cached voices not heard for 30 days go (they are also pruned now and then while the app runs).
php bin/console app:prune-voices --no-interaction || true

exec docker-php-entrypoint "$@"
