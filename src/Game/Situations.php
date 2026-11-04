<?php

namespace App\Game;

/**
 * Les situations d'appel. Chaque espace a son catalogue, écrit à partir de son offre (ALL est celui d'Etixio, GENERIC
 * le canevas dont part l'analyse d'une nouvelle société). Une session en tire une au hasard, l'habille d'un secteur,
 * d'une entreprise et d'un interlocuteur, et l'IA écrit le reste.
 */
final class Situations
{
    /**
     * Les situations que rencontre presque toute équipe commerciale B2B : l'analyse d'une société les adapte à son offre
     * et y ajoute les siennes.
     *
     * @var array<string, array{label: string, emoji: string, family: string, direction: string, material: string, pitch: string, objectives: list<string>}>
     */
    public const GENERIC = [
        'demande_prix' => ['label' => 'Demande de prix sur le site', 'emoji' => '💶', 'family' => 'prospection', 'direction' => 'sortant', 'material' => 'Le message du formulaire',
            'pitch' => 'Un prospect a demandé un prix via le site, en quelques lignes. Le commercial le rappelle : il veut juste un chiffre, alors que le bon prix dépend de son besoin, qu\'il faut découvrir.',
            'objectives' => ['Comprendre le besoin derrière la demande', 'Ne pas lâcher de prix au hasard', 'Obtenir le rendez-vous suivant']],
        'signal' => ['label' => 'Appel à froid sur un signal', 'emoji' => '📡', 'family' => 'prospection', 'direction' => 'sortant', 'material' => 'Le signal repéré',
            'pitch' => 'Le commercial a repéré un signal d\'achat (une annonce de recrutement, une levée de fonds, un nouveau dirigeant, un article, une ouverture de site…) et appelle à froid. L\'interlocuteur ne l\'attend pas, il a d\'autres priorités.',
            'objectives' => ['Passer le barrage et capter l\'attention', 'Faire émerger un besoin réel', 'Obtenir un rendez-vous']],
        'recommandation' => ['label' => 'Recommandé par un client', 'emoji' => '🤝', 'family' => 'prospection', 'direction' => 'sortant', 'material' => 'Le mot du client qui recommande',
            'pitch' => 'Un client satisfait a recommandé le commercial à une connaissance. L\'appel commence bien, mais la personne a ses propres priorités et un fournisseur en place.',
            'objectives' => ['Utiliser la recommandation sans en abuser', 'Découvrir la situation actuelle', 'Obtenir un rendez-vous']],
        'entrant' => ['label' => 'Un prospect appelle', 'emoji' => '📲', 'family' => 'prospection', 'direction' => 'entrant', 'material' => 'Ce que l\'on sait du prospect',
            'pitch' => 'Un prospect appelle de lui-même : il a un besoin, compare plusieurs offres et veut aller vite. Il pose beaucoup de questions et en dit peu.',
            'objectives' => ['Reprendre la main par les questions', 'Qualifier budget, délai et décideur', 'Fixer la prochaine étape']],
        'concurrent' => ['label' => 'Le concurrent moins cher', 'emoji' => '⚔️', 'family' => 'negociation', 'direction' => 'entrant', 'material' => 'La proposition envoyée',
            'pitch' => 'Le prospect a reçu une offre concurrente moins chère et le fait savoir. Il hésite, mais il a des raisons de préférer la société, qu\'il ne dit pas d\'emblée.',
            'objectives' => ['Comprendre ce que le prix cache', 'Défendre la valeur sans brader', 'Garder l\'affaire en vie']],
        'relance_devis' => ['label' => 'Relance d\'une proposition sans réponse', 'emoji' => '⏳', 'family' => 'negociation', 'direction' => 'sortant', 'material' => 'La proposition envoyée',
            'pitch' => 'Une proposition a été envoyée il y a trois semaines, sans nouvelles depuis. Le silence a une raison cachée : un concurrent, un budget gelé, un décideur qui a changé, une crainte non exprimée.',
            'objectives' => ['Comprendre la raison du silence', 'Traiter l\'objection réelle', 'Obtenir une date de décision']],
        'renouvellement' => ['label' => 'Renouvellement avec demande de remise', 'emoji' => '🔁', 'family' => 'negociation', 'direction' => 'entrant', 'material' => 'Le contrat en cours',
            'pitch' => 'Le contrat d\'un client arrive à échéance. Les achats appellent : ils veulent une remise importante, sinon ils consultent. Ils ont des raisons de rester.',
            'objectives' => ['Défendre la valeur avant de parler prix', 'N\'accorder aucune concession sans contrepartie', 'Sécuriser le renouvellement']],
        'client_mecontent' => ['label' => 'Client mécontent', 'emoji' => '😠', 'family' => 'client', 'direction' => 'entrant', 'material' => 'Le mail du client',
            'pitch' => 'Un client appelle, énervé : un problème de qualité, de délai ou de service. Il menace d\'arrêter. Le vrai problème est parfois ailleurs que ce qu\'il dit.',
            'objectives' => ['Laisser vider le sac et reformuler', 'Trouver la cause réelle', 'Repartir avec un plan d\'action accepté']],
        'suivi' => ['label' => 'Suivi client : tout va bien ?', 'emoji' => '🩺', 'family' => 'client', 'direction' => 'sortant', 'material' => 'La fiche client',
            'pitch' => 'Le commercial appelle un client pour faire le point. Tout « va bien », mais un irritant couve, et le client ne le dira que si on creuse.',
            'objectives' => ['Faire dire ce qui ne va pas', 'Mesurer le risque', 'Convenir d\'une action']],
        'nouveaux_besoins' => ['label' => 'Suivi commercial : de nouveaux besoins ?', 'emoji' => '🌱', 'family' => 'client', 'direction' => 'sortant', 'material' => 'La fiche client',
            'pitch' => 'Le commercial appelle un client satisfait pour détecter de nouveaux besoins. Le client ne pense pas à la société pour ses autres sujets, mais une opportunité existe si on pose les bonnes questions.',
            'objectives' => ['Détecter un nouveau besoin', 'Identifier qui le porte', 'Obtenir un rendez-vous sur ce sujet']],
        'resiliation' => ['label' => 'Le client veut arrêter', 'emoji' => '🚪', 'family' => 'client', 'direction' => 'entrant', 'material' => 'Le mail d\'annonce du client',
            'pitch' => 'Un client annonce qu\'il veut réduire ou arrêter. La vraie raison est parfois différente de celle qu\'il donne, et une alternative acceptable pour les deux existe.',
            'objectives' => ['Comprendre la vraie raison', 'Proposer une alternative', 'Garder la relation et une porte ouverte']],
        'nouveau_decideur' => ['label' => 'Un nouveau décideur chez le client', 'emoji' => '🆕', 'family' => 'client', 'direction' => 'sortant', 'material' => 'L\'annonce de sa nomination',
            'pitch' => 'Un nouveau dirigeant arrive chez un client et remet tous les fournisseurs en question. Il ne connaît pas l\'historique et veut faire ses preuves.',
            'objectives' => ['Se présenter et comprendre ses priorités', 'Montrer la valeur apportée jusqu\'ici', 'Obtenir un rendez-vous de revue']],
    ];

    /**
     * @var array<string, array{label: string, emoji: string, family: string, direction: string, material: string, pitch: string, objectives: list<string>}>
     */
    public const ALL = [
        'annonce' => [
            'label' => 'Annonce de recrutement repérée', 'emoji' => '📰', 'family' => 'prospection', 'direction' => 'sortant',
            'material' => 'L\'annonce',
            'pitch' => 'Le commercial a repéré une offre d\'emploi (développeur senior, lead dev, DevOps, data…) publiée depuis des semaines par une entreprise, et l\'appelle à froid pour proposer un renfort offshore (staffing ou équipe dédiée à Madagascar ou Maurice). Le matériel est le texte de l\'annonce. Le persona décroche sans attendre l\'appel : recruteur, CTO, ou standard qui fait barrage. Il veut un CDI en interne, se méfie de l\'offshore, mais le poste est impossible à pourvoir et un projet prend du retard.',
            'objectives' => ['Atteindre la personne qui porte le besoin', 'Faire émerger le besoin derrière l\'annonce', 'Obtenir la visio de 30 minutes avec le CTO d\'Etixio'],
        ],
        'formulaire_prix' => [
            'label' => 'Demande de tarifs sur le site', 'emoji' => '💬', 'family' => 'prospection', 'direction' => 'sortant',
            'material' => 'Le message reçu via le formulaire',
            'pitch' => 'Un prospect a demandé le catalogue de prix sur le site d\'Etixio (souvent en quelques lignes : « combien pour deux développeurs React ? »). Le catalogue lui a été envoyé par e-mail, et le commercial l\'appelle comme prévu. Le persona compare plusieurs prestataires (ESN française, freelances, nearshore), veut un chiffre et un délai, et son vrai besoin est différent de ce qu\'il a écrit.',
            'objectives' => ['Qualifier le besoin avant de parler prix', 'Identifier le budget, le décideur et le calendrier', 'Obtenir la visio de 30 minutes avec le CTO'],
        ],
        'vibe_coding' => [
            'label' => 'Une appli « vibe codée » qui craque', 'emoji' => '🧩', 'family' => 'prospection', 'direction' => 'entrant',
            'material' => 'Le message laissé via le site',
            'pitch' => 'Le fondateur d\'une jeune entreprise a construit son produit lui-même avec Lovable ou Bolt (ou un assistant de code). Il a des clients, mais l\'appli plante, la sécurité l\'inquiète, il ne sait pas la faire évoluer. Il appelle Etixio après avoir lu la page sur la reprise d\'applications « vibe codées ». Il a peu de budget et un ego en jeu.',
            'objectives' => ['Comprendre l\'état réel de l\'appli et les risques', 'Qualifier l\'urgence et les moyens', 'Proposer une première étape (audit flash ou reprise) et la caler'],
        ],
        'poc_ia' => [
            'label' => 'Un POC IA bloqué avant la production', 'emoji' => '🤖', 'family' => 'prospection', 'direction' => 'sortant',
            'material' => 'La fiche d\'inscription au diagnostic IA',
            'pitch' => 'Une entreprise a demandé le diagnostic IA gratuit d\'Etixio : elle a un prototype (un assistant RAG sur ses documents, un agent, de l\'extraction de documents) qui marche en démo mais qu\'elle n\'ose pas mettre en production (droits d\'accès, coûts des modèles, qualité des réponses, données sensibles). Le commercial rappelle pour préparer le diagnostic.',
            'objectives' => ['Faire émerger l\'enjeu métier derrière le POC', 'Identifier les freins (données, sécurité, coûts, gouvernance)', 'Caler le diagnostic avec les bonnes personnes'],
        ],
        'levee_fonds' => [
            'label' => 'Une start-up vient de lever des fonds', 'emoji' => '🚀', 'family' => 'prospection', 'direction' => 'sortant',
            'material' => 'L\'article de presse',
            'pitch' => 'Le commercial a lu qu\'une start-up vient de lever des fonds et doit doubler son équipe produit. Il appelle son fondateur ou son CTO à froid pour proposer une équipe dédiée offshore, voire un CTO as a Service. Le persona est sollicité par vingt prestataires depuis l\'annonce et a des a priori sur l\'offshore.',
            'objectives' => ['Se différencier en moins d\'une minute', 'Faire émerger un enjeu de recrutement ou de vitesse', 'Obtenir la visio de 30 minutes avec le CTO'],
        ],
        'nouveau_dsi' => [
            'label' => 'Un nouveau DSI vient d\'arriver', 'emoji' => '🆕', 'family' => 'prospection', 'direction' => 'sortant',
            'material' => 'L\'annonce de nomination',
            'pitch' => 'Un DSI ou CTO vient d\'être nommé (annonce LinkedIn). Le commercial l\'appelle. Le persona fait son état des lieux, hérite d\'applications mal maintenues et d\'une dette technique, veut montrer vite des résultats sans se faire imposer de prestataire. Un audit technique ou une équipe dédiée peuvent l\'aider.',
            'objectives' => ['Comprendre ses 100 premiers jours', 'Identifier un chantier prioritaire', 'Obtenir un rendez-vous'],
        ],
        'barrage' => [
            'label' => 'Le standard fait barrage', 'emoji' => '🚧', 'family' => 'prospection', 'direction' => 'sortant',
            'material' => 'La fiche du prospect visé',
            'pitch' => 'Le commercial appelle pour joindre le DSI, le CTO ou le dirigeant d\'une entreprise ciblée (un signal repéré : recrutement, croissance, projet annoncé). Il tombe sur l\'accueil ou l\'assistante de direction, dont le métier est de filtrer les démarchages : elle est aimable mais ferme, connaît l\'agenda et les sujets du moment, et ne passe personne sans une vraie raison. Ruser ou mentir la braque ; la respecter et en faire une alliée ouvre la porte. Le persona est cette personne au standard, pas le décideur.',
            'objectives' => ['Faire de la personne au standard une alliée', 'Obtenir le décideur, ou un créneau précis pour le rappeler', 'Repartir avec une information utile (projet, timing, e-mail direct)'],
        ],
        'recommandation' => [
            'label' => 'Appel sur recommandation', 'emoji' => '🤝', 'family' => 'prospection', 'direction' => 'sortant',
            'material' => 'Le message du client qui recommande',
            'pitch' => 'Un client satisfait d\'Etixio (il a une équipe dédiée à Madagascar) a recommandé le commercial à une connaissance, dirigeant ou CTO d\'une autre entreprise. Le commercial l\'appelle. Le persona est bienveillant mais n\'a pas de besoin immédiat, ou le croit, et n\'a jamais travaillé avec de l\'offshore.',
            'objectives' => ['Utiliser la recommandation sans en abuser', 'Faire émerger un besoin', 'Obtenir une suite concrète'],
        ],
        'salon' => [
            'label' => 'Rappel d\'un contact de salon', 'emoji' => '🎪', 'family' => 'prospection', 'direction' => 'sortant',
            'material' => 'La carte de visite et la note prise au salon',
            'pitch' => 'Le commercial a échangé deux minutes avec ce contact sur un salon (VivaTech, un salon IA, un salon de l\'e-commerce…) et a pris une note. Il le rappelle. Le persona se souvient vaguement de lui.',
            'objectives' => ['Raviver le contact', 'Qualifier le besoin évoqué au salon', 'Obtenir la visio de 30 minutes avec le CTO'],
        ],
        'quebec' => [
            'label' => 'Une PME québécoise en pénurie de développeurs', 'emoji' => '🍁', 'family' => 'prospection', 'direction' => 'sortant',
            'material' => 'La fiche du prospect',
            'pitch' => 'Une entreprise du Québec (ou de Belgique, de Suisse) peine à recruter des développeurs. Le commercial l\'appelle pour proposer une équipe francophone et bilingue depuis l\'île Maurice. Le décalage horaire est plus grand qu\'avec la France, le persona s\'en inquiète, ainsi que de la facturation et du droit applicable.',
            'objectives' => ['Faire émerger le coût de la pénurie', 'Traiter la question du décalage horaire', 'Obtenir un rendez-vous'],
        ],
        'tma' => [
            'label' => 'Une application métier sans mainteneur', 'emoji' => '🛠️', 'family' => 'prospection', 'direction' => 'entrant',
            'material' => 'Le mail reçu',
            'pitch' => 'Une entreprise appelle Etixio : son application métier (Symfony, .NET, Odoo…) n\'a plus de mainteneur (le prestataire a fermé, le freelance est parti), les correctifs s\'accumulent. Le besoin évident est une TMA ; le persona veut d\'abord un prix au forfait pour « juste corriger les bugs ».',
            'objectives' => ['Comprendre l\'état de l\'application et les risques', 'Expliquer le choix entre forfait de maintenance et régie', 'Obtenir l\'accord pour un audit flash ou une proposition'],
        ],
        'depart_cto' => [
            'label' => 'PME sans direction technique', 'emoji' => '🧭', 'family' => 'prospection', 'direction' => 'entrant',
            'material' => 'Le message laissé au standard',
            'pitch' => 'Le dirigeant d\'une PME rappelle Etixio : son CTO ou son unique développeur vient de partir, ou son agence ne répond plus, et il n\'a plus personne de technique. Il est inquiet et peu technique. L\'offre pertinente est un CTO as a Service (1 à 4 jours par semaine, ou quelques jours par mois), éventuellement suivi d\'une équipe.',
            'objectives' => ['Rassurer et comprendre l\'urgence', 'Qualifier les moyens et l\'enjeu', 'Proposer une première mission concrète'],
        ],
        'due_diligence' => [
            'label' => 'Un fonds demande un audit technique', 'emoji' => '🔍', 'family' => 'prospection', 'direction' => 'entrant',
            'material' => 'Le mail du fonds d\'investissement',
            'pitch' => 'Un associé d\'un fonds d\'investissement contacte Etixio : il veut un audit technique de la société qu\'il s\'apprête à racheter, avec un calendrier serré. Il veut un prix et une date ; les enjeux réels (ce qu\'il craint, la confidentialité, l\'après-rachat où un CTO as a Service servirait) sont à découvrir. L\'audit Etixio n\'inclut pas de test d\'intrusion.',
            'objectives' => ['Cadrer le périmètre et le délai', 'Comprendre ce que le fonds craint', 'Obtenir l\'accord pour une proposition'],
        ],
        'echaude_offshore' => [
            'label' => 'Un prospect échaudé par l\'offshore', 'emoji' => '🔥', 'family' => 'negociation', 'direction' => 'sortant',
            'material' => 'Le compte rendu du premier échange',
            'pitch' => 'Lors d\'un premier contact, le prospect a dit qu\'il avait déjà essayé l\'offshore (en Inde, ou une plateforme low-cost) et que ça avait été un désastre : qualité, communication, turnover, juniors facturés en seniors. Le commercial le rappelle comme convenu. Une partie de l\'échec venait de son propre cadrage.',
            'objectives' => ['Faire raconter ce qui a échoué', 'Répondre aux craintes avec les vrais engagements d\'Etixio', 'Obtenir la visio de 30 minutes avec le CTO'],
        ],
        'concurrent' => [
            'label' => 'Le prospect compare avec moins cher ou plus proche', 'emoji' => '⚖️', 'family' => 'negociation', 'direction' => 'sortant',
            'material' => 'Le compte rendu du dernier échange',
            'pitch' => 'Après un premier rendez-vous, le prospect annonce qu\'il a une offre moins chère ou plus proche : freelances, nearshore au Maroc ou en Tunisie, ou ESN française avec des développeurs sur place. Le commercial le rappelle. Le prospect a des doutes sur l\'autre offre qu\'il ne dit pas.',
            'objectives' => ['Comprendre les critères de choix', 'Faire émerger les risques de l\'autre offre sans la dénigrer', 'Rester dans la course avec une prochaine étape'],
        ],
        'forfait_regie' => [
            'label' => 'Il veut un prix ferme pour un projet flou', 'emoji' => '📐', 'family' => 'negociation', 'direction' => 'sortant',
            'material' => 'Le cahier des charges reçu',
            'pitch' => 'Le prospect a envoyé un cahier des charges flou pour une plateforme qui prendra un an, et exige un prix ferme au forfait. Le bon montage chez Etixio est un forfait de cadrage ou de prototype, puis un projet en régie. Le persona craint de signer un chèque en blanc.',
            'objectives' => ['Faire émerger ce qui est flou dans le besoin', 'Faire accepter un cadrage avant le chiffrage', 'Obtenir l\'accord sur la suite'],
        ],
        'urgence' => [
            'label' => 'Il lui faut des développeurs la semaine prochaine', 'emoji' => '⏱️', 'family' => 'negociation', 'direction' => 'entrant',
            'material' => 'Le message reçu',
            'pitch' => 'Un prospect appelle en urgence : il lui faut trois développeurs « lundi » pour tenir une échéance. Etixio démarre généralement en 4 à 8 semaines (le client voit et valide les profils). Le commercial doit être honnête sur le délai sans perdre l\'affaire, et trouver ce qui peut se faire tout de suite.',
            'objectives' => ['Comprendre l\'échéance réelle et ce qui en dépend', 'Être honnête sur le délai de démarrage', 'Trouver une prochaine étape qui sert le prospect'],
        ],
        'client_mecontent' => [
            'label' => 'Client mécontent', 'emoji' => '😤', 'family' => 'client', 'direction' => 'entrant',
            'material' => 'Le mail du client et l\'état du projet',
            'pitch' => 'Un client d\'Etixio (une équipe dédiée travaille pour lui à Madagascar ou à Maurice depuis des mois) appelle, énervé : retard de livraison, bug en production, développeur qui part, réponses lentes à cause du décalage horaire, facture contestée… Le matériel est son mail et une note sur l\'état du projet. Une partie du problème vient peut-être de son côté.',
            'objectives' => ['Désamorcer la colère sans se justifier', 'Comprendre le vrai problème', 'Faire accepter un plan d\'action concret'],
        ],
        'depart_dev' => [
            'label' => 'Le développeur clé de l\'équipe s\'en va', 'emoji' => '👋', 'family' => 'client', 'direction' => 'entrant',
            'material' => 'Le mail du client',
            'pitch' => 'Un client apprend que le développeur le plus impliqué de son équipe dédiée Etixio quitte l\'entreprise. Il appelle, inquiet pour la continuité et agacé (« c\'est exactement ce qu\'on craignait avec l\'offshore »). Etixio répond par la documentation, une passation encadrée et un remplacement organisé, sans remplacement gratuit garanti.',
            'objectives' => ['Rassurer avec un plan de passation concret', 'Comprendre ce qui inquiète vraiment le client', 'Garder sa confiance et caler la suite'],
        ],
        'suivi_projet' => [
            'label' => 'Appel de suivi de projet', 'emoji' => '📋', 'family' => 'client', 'direction' => 'sortant',
            'material' => 'La fiche projet',
            'pitch' => 'Le commercial appelle un client pour un point de suivi : « est-ce que tout va bien ? ». Le client répond d\'abord que tout va bien, mais il y a un irritant non dit (communication, décalage horaire, qualité, un développeur, la vitesse, la facturation) qui pourrait le faire partir.',
            'objectives' => ['Faire émerger les irritants non dits', 'Mesurer la satisfaction réelle', 'Convenir d\'une action corrective'],
        ],
        'suivi_commercial' => [
            'label' => 'Suivi commercial : de nouveaux besoins ?', 'emoji' => '🌱', 'family' => 'client', 'direction' => 'sortant',
            'material' => 'La fiche client',
            'pitch' => 'Le commercial appelle un client satisfait (équipe dédiée, TMA ou forfait terminé) pour prendre des nouvelles et détecter de nouveaux besoins. Le client ne pense pas à Etixio pour ses autres sujets (un agent IA, un POC à industrialiser, un audit, un CTO qui part, une autre équipe produit…), mais une opportunité existe si on pose les bonnes questions.',
            'objectives' => ['Détecter un nouveau besoin', 'Identifier qui porte ce besoin', 'Obtenir un rendez-vous sur ce sujet'],
        ],
        'resiliation' => [
            'label' => 'Le client veut réduire ou arrêter', 'emoji' => '🚪', 'family' => 'client', 'direction' => 'entrant',
            'material' => 'Le mail d\'annonce du client',
            'pitch' => 'Un client annonce qu\'il veut réduire son équipe dédiée ou arrêter (budget, internalisation, changement de stratégie, l\'IA qui « remplacerait des développeurs »). La vraie raison est parfois différente, et il existe une alternative acceptable pour les deux (équipe réduite, TMA, CTO as a Service).',
            'objectives' => ['Comprendre la vraie raison', 'Proposer une alternative', 'Garder la relation et une porte ouverte'],
        ],
        'relance_devis' => [
            'label' => 'Relance d\'une proposition sans réponse', 'emoji' => '⏳', 'family' => 'negociation', 'direction' => 'sortant',
            'material' => 'La proposition envoyée',
            'pitch' => 'Une proposition (équipe dédiée, projet en régie, CTO as a Service) a été envoyée il y a trois semaines après la visio avec le CTO, sans nouvelles depuis. Le commercial relance. Le silence a une raison cachée : un concurrent, un budget gelé, un décideur qui a changé, une crainte sur l\'offshore non exprimée.',
            'objectives' => ['Comprendre la raison du silence', 'Traiter l\'objection réelle', 'Obtenir une date de décision'],
        ],
        'renouvellement' => [
            'label' => 'Renouvellement avec demande de remise', 'emoji' => '🔁', 'family' => 'negociation', 'direction' => 'entrant',
            'material' => 'Le contrat en cours',
            'pitch' => 'L\'équipe dédiée d\'un client arrive au terme de sa période. Les achats ou la direction financière appellent : ils veulent une remise importante, sinon ils consultent (nearshore, autre offshore). Ils ont une vraie marge de manœuvre et des raisons de rester (l\'équipe connaît le produit).',
            'objectives' => ['Défendre la valeur avant de parler prix', 'N\'accorder aucune concession sans contrepartie', 'Sécuriser le renouvellement'],
        ],
    ];

    public const FAMILIES = ['prospection' => 'Prospection', 'client' => 'Relation client', 'negociation' => 'Négociation'];

    public const SECTORS = [
        'logistique et transport', 'e-commerce', 'assurance', 'banque et fintech', 'santé et e-santé', 'industrie', 'éditeur de logiciel SaaS',
        'immobilier', 'énergie', 'agroalimentaire', 'tourisme et voyage', 'formation et edtech', 'collectivité locale', 'retail', 'médias',
        'ressources humaines et paie', 'BTP', 'automobile', 'luxe', 'association et mutuelle',
    ];

    private const SIZES = ['une start-up de 15 personnes', 'une PME de 40 salariés', 'une PME de 120 salariés', 'une ETI de 400 salariés', 'un groupe de 2 000 salariés', 'une scale-up de 80 personnes'];

    private const CITIES = ['Lyon', 'Nantes', 'Lille', 'Bordeaux', 'Toulouse', 'Marseille', 'Rennes', 'Strasbourg', 'Grenoble', 'Paris', 'Montpellier', 'Annecy', 'Nice', 'Clermont-Ferrand', 'Rouen', 'Dijon'];

    /**
     * Les tons d'un interlocuteur : son caractère au téléphone, et la façon dont sa voix le porte (débit, hauteur).
     *
     * @var array<string, array{label: string, rate: string, pitch: string}>
     */
    public const TONES = [
        'chaleureux' => ['label' => 'chaleureux et bavard, il aime raconter et met vite à l\'aise', 'rate' => '+8%', 'pitch' => '+1Hz'],
        'enthousiaste' => ['label' => 'entrepreneur enthousiaste, plein d\'idées, il parle vite de sa vision', 'rate' => '+18%', 'pitch' => '+4Hz'],
        'direct' => ['label' => 'direct et carré, il va droit au but mais reste correct', 'rate' => '+14%', 'pitch' => '-1Hz'],
        'detendu' => ['label' => 'sympa et détendu, il plaisante volontiers', 'rate' => '+10%', 'pitch' => '+2Hz'],
        'prudent' => ['label' => 'posé et prudent, il réfléchit avant de répondre et pose des questions', 'rate' => '+4%', 'pitch' => '-2Hz'],
        'presse' => ['label' => 'pressé mais courtois, il est entre deux réunions', 'rate' => '+20%', 'pitch' => '+1Hz'],
        'curieux' => ['label' => 'curieux et technique, il aime comprendre comment ça marche', 'rate' => '+10%', 'pitch' => '+0Hz'],
        'mefiant' => ['label' => 'méfiant mais ouvert si on l\'écoute vraiment', 'rate' => '+8%', 'pitch' => '-2Hz'],
        'stresse' => ['label' => 'stressé par une échéance, un peu tendu', 'rate' => '+18%', 'pitch' => '+3Hz'],
        'inquiet' => ['label' => 'inquiet, il cherche à être rassuré', 'rate' => '+10%', 'pitch' => '+2Hz'],
        'fache' => ['label' => 'fâché, il ne décolère pas et le fait entendre, sans être grossier', 'rate' => '+20%', 'pitch' => '+4Hz'],
        'decu' => ['label' => 'déçu et un peu las, il y avait cru', 'rate' => '+2%', 'pitch' => '-4Hz'],
    ];

    /** Les tons du quotidien : la plupart des appels se passent avec des gens normaux, plutôt aimables. */
    private const EVERYDAY = ['chaleureux', 'chaleureux', 'enthousiaste', 'direct', 'direct', 'detendu', 'detendu', 'prudent', 'presse', 'curieux', 'mefiant'];

    /** Les situations qui appellent un autre ton (un client mécontent n'est pas détendu). */
    private const TONES_BY_SITUATION = [
        'client_mecontent' => ['fache', 'fache', 'decu', 'stresse'],
        'depart_dev' => ['inquiet', 'stresse', 'fache'],
        'resiliation' => ['decu', 'direct', 'prudent', 'fache'],
        'echaude_offshore' => ['mefiant', 'decu', 'prudent'],
        'urgence' => ['stresse', 'presse'],
        'barrage' => ['chaleureux', 'presse', 'prudent', 'direct'],
        'depart_cto' => ['inquiet', 'stresse', 'direct'],
        'suivi_projet' => ['chaleureux', 'direct', 'decu', 'detendu'],
        'renouvellement' => ['direct', 'prudent', 'presse'],
    ];

    /**
     * Une situation tirée au hasard, habillée : le type, le secteur, la taille, la ville, l'humeur, la difficulté.
     *
     * @param int|null $level le niveau du commercial : la difficulté monte avec lui
     *
     * @param list<string> $avoid les situations jouées récemment, à ne pas retirer tout de suite
     *
     * @return array{key: string, sector: string, size: string, city: string, mood: string, tone: string, difficulty: int}
     */
    public static function draw(\App\Entity\Workspace $space, ?int $level = 1, ?string $key = null, array $avoid = []): array
    {
        $catalogue = [] !== $space->situations ? $space->situations : self::ALL;
        $sectors = [] !== $space->sectors ? $space->sectors : self::SECTORS;
        $level ??= 1;
        $difficulties = match (true) {
            $level <= 1 => [1, 1, 2],
            $level <= 3 => [1, 2, 2, 3],
            default => [2, 3, 3],
        };

        $pool = array_diff(array_keys($catalogue), $avoid) ?: array_keys($catalogue);
        $key = null !== $key && isset($catalogue[$key]) ? $key : $pool[array_rand($pool)];
        // Le ton suit la situation : un client mécontent n'est pas détendu, même dans un catalogue écrit par l'analyse.
        $tones = self::TONES_BY_SITUATION[$key]
            ?? (preg_match('/mecontent|resili|plainte|litige|insatisf|colere|reclam|retard|incident/', $key) ? ['fache', 'decu', 'stresse', 'inquiet'] : self::EVERYDAY);
        $tone = $tones[array_rand($tones)];

        return [
            'key' => $key,
            'sector' => $sectors[array_rand($sectors)],
            'size' => self::SIZES[array_rand(self::SIZES)],
            'city' => self::CITIES[array_rand(self::CITIES)],
            'mood' => self::TONES[$tone]['label'],
            'tone' => $tone,
            'difficulty' => $difficulties[array_rand($difficulties)],
        ];
    }

    /**
     * @return array{label: string, emoji: string, family: string, direction: string, material: string, pitch: string, objectives: list<string>}|null
     */
    public static function get(?string $key): ?array
    {
        return null === $key ? null : (self::ALL[$key] ?? null);
    }
}
