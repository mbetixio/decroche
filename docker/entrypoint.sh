#!/bin/sh
# On start: the volume's folders, the instance's own secrets (generated once, kept in the volume), the database
# migrated, then the server. The first visit opens the onboarding; DECROCHE_DEMO=1 loads the example company instead.
set -e

mkdir -p /var/decroche/sessions /var/decroche/voices

if [ ! -f /var/decroche/secrets.env ]; then
    printf 'APP_SECRET=%s\nMCP_STATE_KEY=%s\n' "$(php -r 'echo bin2hex(random_bytes(16));')" "$(php -r 'echo bin2hex(random_bytes(24));')" > /var/decroche/secrets.env
    chmod 600 /var/decroche/secrets.env
fi
set -a
. /var/decroche/secrets.env
set +a

# Without a model, nothing works: say so plainly and stop, instead of an instance whose every call fails.
if [ -z "${AI_API_KEY:-}" ]; then
    echo "AI_API_KEY is empty: put your Hugging Face token (hf_…) in .env.docker, then run docker compose up -d again." >&2
    exit 1
fi

if [ "${DECROCHE_DEMO:-0}" = "1" ]; then
    php bin/console app:install --demo --language="${DECROCHE_LANGUAGE:-en}" --no-interaction
else
    php bin/console app:install --no-interaction
fi

# The cached voices not heard for 30 days go (they are also pruned now and then while the app runs).
php bin/console app:prune-voices --no-interaction || true

exec docker-php-entrypoint "$@"
