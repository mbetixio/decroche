#!/bin/sh
# Keeps Kyutai's Pocket TTS server running for the instance's language only (one model in memory, about 700 MB). It
# starts as soon as the onboarding's first step has set the language, so it is ready long before the first call; it
# starts again if it stops, or if the language is changed. Until it answers, edge-tts speaks. The model is downloaded
# on first use into the volume (HF_HOME), without any token.
pid=""
running=""
while :; do
    language=$(php /app/bin/console app:language --no-interaction 2>/dev/null) || language=""
    case "$language" in
        fr) model=french ;;
        en) model=english ;;
        *) model="" ;;
    esac
    # A server for another language, or one that stopped: let it go.
    if [ -n "$pid" ] && { [ "$model" != "$running" ] || ! kill -0 "$pid" 2>/dev/null; }; then
        kill "$pid" 2>/dev/null
        wait "$pid" 2>/dev/null
        pid=""
    fi
    if [ -z "$pid" ] && [ -n "$model" ]; then
        "$POCKET_TTS" serve --host 127.0.0.1 --port 8100 --language "$model" &
        pid=$!
        running=$model
    fi
    # Every 2 seconds until it runs, then every 10: the language rarely changes once chosen.
    if [ -n "$pid" ]; then sleep 10; else sleep 2; fi
done
