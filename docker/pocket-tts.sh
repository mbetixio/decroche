#!/bin/sh
# Keeps Kyutai's Pocket TTS server running, for the instance's language only (one model in memory, about 700 MB): it
# starts once the onboarding has set the language, and again if it stops. Until it answers, edge-tts speaks. The
# models are downloaded on first use into the volume (HF_HOME), without any token.
while :; do
    language=$(php /app/bin/console app:language --no-interaction 2>/dev/null) || language=""
    case "$language" in
        fr) model=french ;;
        en) model=english ;;
        *) sleep 5; continue ;;
    esac
    "$POCKET_TTS" serve --host 127.0.0.1 --port 8100 --language "$model" || true
    sleep 5
done
