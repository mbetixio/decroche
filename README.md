# Sparring

Le terrain d'entraînement au téléphone des équipes commerciales, comme un jeu, **pour toute société** : elle ouvre son
espace, donne son site, et Sparring en tire la fiche de son offre, ses clients types et les situations d'appel que ses
commerciaux rencontrent. Le commercial se connecte, clique sur **📞 Nouvel appel**, et une situation est tirée au hasard
puis écrite pour lui (une demande de prix, un appel à froid sur un signal, un client mécontent, un suivi, une relance de
devis, un renouvellement avec demande de remise, et les situations propres au métier de la société…). Il lit sa fiche
(la situation, le document qu'il a sous les yeux, ses objectifs, son interlocuteur), lance l'appel, le mène **à la
voix**, comme au téléphone, puis raccroche : l'appel est analysé (objectifs atteints ou non, note sur la grille de la
société, répliques, brief caché dévoilé, chiffres de l'appel) et lui rapporte de l'expérience.

L'interlocuteur a un **brief caché** : la situation de son côté, son budget réel, sa vraie douleur, qui décide, ses
objections, ce qui le ferait dire oui et une information qu'il ne donne pas d'emblée. Il ne lâche chaque rubrique que si
on lui pose la bonne question. Ce brief reste côté serveur jusqu'au débrief.

Tout est calé sur **l'offre de la société** : les personas en connaissent ce qu'un client en saurait et en sortent les
objections classiques, le coach pénalise toute promesse que l'offre ne permet pas de tenir. Etixio a son espace de
démonstration (`App\Game\Etixio`, ses 24 situations dans `App\Game\Situations`, sa bibliothèque dans
`config/scenarios.yaml`).

## L'espace d'une société

- **Inscription** (`/inscription`) : la société, son site, son manager. Le manager arrive sur **son espace** (`/espace`) :
  il décrit sa société en quelques mots et lance l'analyse (`App\Space\Analyst`). Sparring lit la page d'accueil et
  jusqu'à cinq pages sur l'offre (services, tarifs, références…), seulement sur le web public (jamais le réseau
  interne), puis le modèle écrit en deux passes la **fiche d'offre** (qui, les offres, le cadre, les preuves, ce qui n'est
  pas proposé, les objections classiques, les arguments forts ; « à préciser » plutôt qu'une invention) et le
  **catalogue de situations** (le canevas commun adapté à son métier, plus les siennes). Tout se corrige à la main :
  la fiche, les clients types, chaque situation (retirer, modifier, ajouter), et la **grille de notation** (2 à 8
  critères avec ce que le coach attend ; celle de Sparring par défaut).
- **Invitations** (`Équipe`) : un lien personnel par adresse ; la personne crée son compte dans l'espace de la société,
  puis **se présente** (`/moi` : son rôle, son expérience, ce qu'elle vend, à qui, ce qu'elle veut travailler). Les
  situations s'adaptent à son profil, le coach aussi. On ne joue qu'une fois présenté ; un manager peut gérer son
  espace et suivre son équipe avant.
- **Missions** (`Équipe`) : le manager donne à un commercial ou à toute l'équipe tant d'appels (d'une situation, ou au
  choix) notés au moins tant, avant une date ; chacun les retrouve sur sa page d'accueil avec sa progression et un
  bouton pour les jouer. Les reprises d'un moment ne comptent pas.
- **Cloisonnement** : bibliothèque, situations, tableau d'équipe, sessions, outils MCP, tout reste dans l'espace de la
  société ; un manager ne voit que les siens.
- **Paiement** : pas encore (prévu à l'utilisateur) ; le tableau d'équipe compte les membres de l'espace.

## Ce qu'on y fait

- **Jouer** : 24 situations (`App\Game\Situations`, dont le standard qui fait barrage) en trois familles (prospection, relation client, négociation),
  appels sortants ou entrants, habillées au hasard d'un secteur, d'une taille, d'une ville et d'une humeur. La difficulté
  suit le niveau du commercial. On peut tirer « une autre situation » avant de décrocher.
- **L'appel** : un écran de téléphone, pas un chat. L'avatar de l'interlocuteur, le chrono, le bouton vert pour appeler
  (ça sonne d'abord) ou décrocher, le rouge pour raccrocher, et les touches Micro, Son, Sous-titres, Clavier et Ma fiche
  (objectifs, contexte, document, rubriques du brief découvertes, « Abandonner »). Pas de champ de saisie : on parle,
  l'écran montre seulement l'aperçu de ce qui a été compris. Le transcript n'arrive qu'après, sous l'analyse. Si
  l'interlocuteur raccroche ou si le temps est écoulé, la ligne coupe et l'analyse part.
- **La voix** : reconnaissance vocale du navigateur, en français ; la réplique part après une courte pause.
  L'interlocuteur répond avec une **voix neuronale de Microsoft Edge** (Denise et Henri, toujours en français : les voix « Multilingual » lisaient en anglais les répliques courtes et les noms propres ; accent
  québécois pour la situation au Québec), générée par le serveur et un peu accélérée (+12 %) : la même voix sur tous
  les navigateurs, toujours la même pour un persona, du même genre que lui. Si le service de voix ne répond pas, la
  voix du navigateur prend le relais (« Ma fiche » permet aussi d'en choisir une). Le clavier n'est qu'un recours :
  il s'ouvre seul si le micro est bloqué ou si le navigateur ne reconnaît pas la voix (Firefox).
- **Couper la parole** : pendant que l'interlocuteur répond, le micro reste ouvert ; si le commercial parle vraiment
  (quelques mots, pas un simple « oui », et pas l'écho de la voix du persona), le persona se tait, l'écoute et sait
  qu'on l'a coupé. Désactivable dans « Ma fiche » ; plus fiable avec un casque.
- **Rejouer un moment** : depuis le débrief, « Rejouer ce moment » sous chaque réplique à retravailler, ou « Rejouer
  d'ici » sur n'importe quelle réplique du transcript. Un nouvel appel reprend juste avant, avec le temps qu'il restait ;
  l'appel d'origine ne bouge pas, et la reprise ne compte ni dans les moyennes ni dans l'XP.
- **L'analyse** : le résultat de l'appel en une phrase, chaque objectif atteint, partiel ou manqué (avec la preuve), une
  note sur 100 (70 % les critères, 30 % les objectifs), l'XP gagnée, les critères de la grille de la société notés et
  commentés (par défaut : découverte du besoin, questions ouvertes, écoute et reformulation, traitement des objections,
  proposition de valeur, closing), **l'appel
  en chiffres** (temps de parole, questions et questions ouvertes, tics de langage, débit en mots par minute à la voix,
  plus longue tirade, délai de réponse) avec le commentaire du coach, le brief caché dévoilé avec la question qui aurait
  fait sortir chaque manque, les meilleures et pires répliques citées mot pour mot, trois conseils.
- **Progression** : niveau (de Stagiaire à Légende) et XP, série de jours d'affilée, note moyenne et tendance, part
  d'appels à objectif atteint, moyenne par critère, chiffres par type de situation, historique.
- **Équipe** (manager) : les missions, les invitations, et pour chaque membre son niveau, ses appels, sa moyenne, sa
  tendance, ses objectifs atteints, sa série et ce qu'il doit travailler ; sa fiche détaillée et chacun de ses appels ;
  réinitialiser un mot de passe, nommer des managers ; les résultats par type de situation.
- **Bibliothèque** : des rendez-vous écrits à la main par les managers, et « Préparer un rendez-vous » : un scénario
  depuis la fiche d'un vrai prospect, ou depuis **la transcription d'un vrai appel** (Aircall, Ringover, Gong, Teams,
  Zoom…) qui recrée le client tel qu'il était, pour rejouer l'appel et faire mieux.

## Comment c'est fait

- Symfony 8.1, PHP 8.5, Doctrine ORM sur SQLite (`var/data_<env>.db`), Twig, sans JavaScript de build.
- Symfony AI avec la plateforme générique (toute API compatible OpenAI) : `App\Ai\Brain`, repris de Murder Context Protocol.
- Une situation est écrite par `ScenarioWriter::situation()` à partir du tirage et de la fiche de l'offre ; l'XP et les
  niveaux sont dans `App\Game\Level`, les chiffres de l'appel dans `App\Training\Speech`.
- Le persona (`App\Training\Persona`) répond **en une seule passe du modèle, en flux** : son prompt porte le brief et les
  règles du jeu, et il note ce qu'il fait par des repères que le serveur retire du texte avant qu'il parte
  (`[devoiler:budget]` pour une rubrique lâchée, `[fin]` quand il raccroche). Pas d'outil, donc pas de second aller-retour
  au modèle. Sur le site, la réponse arrive en NDJSON : chaque phrase part dès qu'elle est écrite, avec sa voix générée
  pendant que le modèle écrit la suivante (le tout premier morceau part dès une virgule). Chaque persona a un ton (chaleureux, entrepreneur enthousiaste,
  direct, détendu, prudent, pressé, méfiant… ; fâché ou déçu seulement quand la situation s'y prête), qui règle aussi
  le débit et la hauteur de sa voix ; la difficulté joue sur ce qu'il faut pour le convaincre, pas sur sa froideur. Le
  tirage évite les trois dernières situations jouées et les noms déjà vus. Le coach (`App\Training\Coach`) relit toute la conversation, complète les rubriques découvertes que le
  persona aurait oublié de noter, et rend le débrief en JSON ; le serveur recalcule la note et ne garde que les citations
  vraiment dites.
- `App\Training\Trainer` porte le déroulé (lancer, parler, horloge, terminer, abandonner) pour le site comme pour le MCP.
- Le modèle réfléchit en silence s'il en a le droit, ce qui est très lent : tout ce qu'un utilisateur attend part avec
  `thinking: {type: disabled}` et `max_tokens: 32000` (`Brain::thinking()`). Une réplique du persona prend 3 à 7 s, un
  débrief 10 à 20 s, l'écriture d'une situation 15 à 20 s avec `deepseek-v4.1-flash`.

## Installer et lancer

```bash
cp .env .env.local   # puis renseigner AI_BASE_URL, AI_API_KEY, AI_MODEL, AI_HEADERS, APP_SECRET, MCP_STATE_KEY
php bin/console app:install
php -S 127.0.0.1:8300 -t public
```

`app:install` crée ou met à jour la base. Les sociétés s'inscrivent ensuite sur `/inscription`. Les comptes et scénarios
d'avant les espaces rejoignent l'espace **Etixio** (créé à la volée avec son offre, ses situations et sa bibliothèque ;
`--etixio` le crée sur une base neuve), et `--manager=vous@etixio.fr --name="Votre nom" --password=…` y crée ou y
promeut un manager.

Le modèle, avec OpenCode Go :

```dotenv
AI_BASE_URL=https://opencode.ai/zen/go/v1
AI_API_KEY=…
AI_MODEL=deepseek-v4.1-flash
AI_HEADERS={"x-opencode-session": "sparring"}
```

Les dates s'affichent dans le fuseau `APP_TIMEZONE` (Europe/Paris par défaut).

Les voix neuronales d'Edge passent par [edge-tts](https://github.com/rany2/edge-tts), installé dans le projet (Python 3) :

```bash
python3 -m venv var/edge-tts && var/edge-tts/bin/pip install edge-tts
```

Microsoft change de temps en temps le jeton d'accès de ce service (non documenté) : `var/edge-tts/bin/pip install -U
edge-tts` suit. `EDGE_TTS` donne un autre chemin vers l'exécutable. Les répliques générées sont gardées dans
`var/voices`. Sans edge-tts, l'appel utilise la voix du navigateur.

Pour essayer un scénario sans navigateur, ou rejouer une session scriptée :

```bash
php bin/console app:play 1 --seller=vous@etixio.fr
php bin/console app:play 1 --seller=vous@etixio.fr --line="Bonjour, qu'est-ce qui vous a amené à accepter ce rendez-vous ?" --line="…"
```

## Déployer sur un serveur (Docker)

Une seule image (`Dockerfile`) : FrankenPHP (PHP 8.5, réponses en flux) et edge-tts pour les voix. Le conteneur sert
Sparring **en HTTP simple sur le port 7077** ; le domaine et le HTTPS sont gérés devant, par **Nginx Proxy Manager**.
La configuration de production est dans **`.env.prod`, versionné** (le dépôt est privé) : la clé du modèle, des secrets
propres à la production, les chemins du volume.

```bash
git clone https://github.com/mbetixio/sparring.git && cd sparring
# dans .env.prod : SPARRING_DOMAIN=sparring.mondomaine.fr
docker compose up -d --build
```

Dans Nginx Proxy Manager, un **Proxy Host** : le domaine → `http://<adresse du serveur>:7077` (ou `http://sparring:80`
si NPM est sur le même réseau Docker), avec le certificat SSL de NPM. Les réponses du persona arrivent en flux : laissez
la mise en cache de NPM désactivée.

- **`SPARRING_DOMAIN`** (dans `.env.prod`) : le domaine public. Les liens d'invitation et le serveur MCP l'utilisent.
- **`TRUSTED_PROXIES=private_ranges`** : Sparring fait confiance aux en-têtes `X-Forwarded-*` d'un proxy à adresse privée
  (NPM dans Docker sur le même serveur), et sait donc qu'il est servi en HTTPS. Si NPM tourne sur une autre machine,
  mettez son adresse IP, ou `REMOTE_ADDR`.
- **Un autre port** : `SPARRING_PORT=8080 docker compose up -d`.
- **Les données** vivent dans le volume `sparring` (`/var/sparring` : la base SQLite, les sessions, les voix déjà
  générées). Un redéploiement ne perd rien et ne déconnecte personne.
- **Au démarrage**, `app:install` met la base à jour et crée l'espace de démonstration Etixio. Les sociétés s'inscrivent
  ensuite sur `/inscription`.
- **Mettre à jour** : `git pull && docker compose up -d --build`.
- **Un manager dans l'espace Etixio** :
  `docker compose exec sparring php bin/console app:install --etixio --manager=vous@etixio.com --name="Votre nom" --password=…`
- **Reprendre une base existante** (celle de votre poste, par exemple) :
  `docker compose cp var/data_dev.db sparring:/var/sparring/sparring.db && docker compose restart`
- **Sauvegarder** : `docker compose exec sparring sqlite3 /var/sparring/sparring.db ".backup /var/sparring/sauvegarde.db"`,
  puis `docker compose cp sparring:/var/sparring/sauvegarde.db .`
- **Les journaux** : `docker compose logs -f sparring`.

## Le serveur MCP

Chaque commercial crée sa clé dans **Mon compte** et ajoute l'adresse `https://<hôte>/mcp?key=…` comme connecteur dans
Claude ou ChatGPT (ou envoie la clé en `Authorization: Bearer …`). L'hôte doit figurer dans `MCP_ALLOWED_HOSTS`, et
`DEFAULT_URI` doit être l'adresse publique du site (elle entre dans la politique de sécurité de l'app MCP).

| Outil | Ce qu'il fait |
| --- | --- |
| `new_call` | Le jeu : tire une situation surprise, l'écrit et lance l'appel (fiche, document, objectifs, première phrase). |
| `scenarios` | Liste les scénarios de la bibliothèque (jamais leur brief). |
| `start_sparring` | Lance une session : contexte, objectif, durée, première phrase du persona. |
| `reply` | Transmet la réplique du commercial, mot pour mot ; rend la réponse du persona, le temps restant, les rubriques découvertes. |
| `end_sparring` | Termine et rédige le débrief noté (ou abandonne avec `abandon=true`). |
| `debrief` | **MCP App** : le débrief complet affiché dans la conversation. |
| `my_progress` | L'historique et la progression du commercial. |
| `create_scenario` | Fabrique une mise en situation depuis une fiche prospect, pour préparer un vrai rendez-vous. |

Les instructions du serveur demandent à l'IA d'être un simple relais pendant la session : transmettre les mots du
commercial sans les améliorer, ne jamais inventer une réplique du persona, ne pas coacher sauf demande explicite.

## Tests et analyse

```bash
php vendor/bin/phpunit
vendor/bin/phpstan analyse
```

Les tests jouent des appels entiers avec un modèle scripté (`MockPlatformFactory`) et une horloge simulée : le
déroulé et le débrief, l'horloge et l'abandon, la bibliothèque et la génération, le site de l'inscription au tableau
d'équipe (droits compris), le jeu (tirage, fiche sans brief, réplique dite au micro, objectifs, XP, chiffres de l'appel),
la création de comptes par le manager, et le serveur MCP de bout en bout, en vérifiant que le brief ne sort jamais avant le débrief.
