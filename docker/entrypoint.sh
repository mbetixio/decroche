#!/bin/sh
# Au démarrage : les dossiers du volume, la base à jour (app:install est sans danger à chaque redémarrage), puis le
# serveur. Le conteneur reste en HTTP simple sur le port 80 ; le domaine et le HTTPS sont gérés devant (Nginx Proxy
# Manager).
set -e

export SERVER_NAME=":80"
# Le domaine public : les liens (invitations) et le MCP l'utilisent.
if [ -n "${SPARRING_DOMAIN:-}" ]; then
    export DEFAULT_URI="https://$SPARRING_DOMAIN"
    export MCP_ALLOWED_HOSTS="$SPARRING_DOMAIN,localhost,127.0.0.1"
fi

mkdir -p /var/sparring/sessions /var/sparring/voices
# --etixio : l'espace de démonstration Etixio (son offre, ses situations, sa bibliothèque).
php bin/console app:install ${SPARRING_INSTALL_ARGS:---etixio} --no-interaction

exec docker-php-entrypoint "$@"
