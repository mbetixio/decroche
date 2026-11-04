<?php

namespace App\Game;

/**
 * Ce qu'Etixio vend vraiment (d'après etixio.com, novembre 2026, complété par l'équipe : le Vietnam et les effectifs) : la même fiche sert au persona, au générateur de
 * situations, au coach, et aux commerciaux (page « L'offre »). Ce qui n'y est pas ne doit pas être promis.
 */
final class Etixio
{
    public const OFFER = <<<'TXT'
        Etixio (site etixio.com, adresses en @etixio.com) : société française (siège en région parisienne, à Meudon) de
        développement logiciel et d'intégration d'IA, en offshore. Environ 170 développeurs dans trois pays, encadrés par des
        leads techniques et un CTO : une centaine à Madagascar (Antananarivo, UTC+3 : +2 h sur Paris en hiver, +1 h en été ;
        francophones), une quarantaine à l'île Maurice (UTC+4 : +3 h en hiver, +2 h en été ; français et anglais courants,
        utile pour les projets bilingues, la Belgique, la Suisse, le Canada) et une trentaine au Vietnam (bureau à
        Hô-Chi-Minh-Ville, UTC+7 : +6 h en hiver, +5 h en été ; une partie de l'équipe commerciale y travaille aussi). Une
        même équipe peut être répartie sur plusieurs sites. Fondateurs : Julien Goumet (CEO) et Mathieu Bernard (CTO), qui ont
        auparavant fait grandir Bocasay jusqu'à plus de 170 personnes ; 15 ans d'externalisation, plus de 50 équipes montées.
        Directeur commercial : Marc Giraud Sauveur (France et Canada).

        Les offres :
        - Équipe dédiée (régie, assistance technique) : des développeurs qui rejoignent l'équipe du client à distance, sur la
          durée ; le client voit les profils, mène les entretiens et valide chaque arrivée ; un coût mensuel par personne ;
          équipe ajustable selon les phases. Équipes typiques de 5 à 11 personnes, stables.
        - Projet en régie : un objectif, un périmètre de départ, un responsable Etixio (chef de projet ou lead technique),
          facturation au temps passé ; pour les projets de plus de trois mois dont le périmètre évolue.
        - Forfait : périmètre court et stable, prix ferme, jalons et critères de recette, de quelques semaines à environ trois
          mois ; tout changement passe par une analyse d'impact. Un forfait de cadrage ou de prototype peut précéder une régie.
        - Staffing IT offshore : un renfort ciblé (développeur, lead technique, QA, DevOps, Product Owner, Scrum Master,
          UX/UI) pour une durée définie.
        - TMA et maintenance applicative, en régie ou en forfait de maintenance.
        - Audit technique : flash, complet, ou audit et mise en œuvre ; de quelques jours à quelques semaines ; cartographie,
          diagnostic argumenté, plan de remédiation priorisé (ce n'est pas un test d'intrusion ni un test de charge).
        - CTO as a Service (CTO à temps partagé) : 1 à 4 jours par semaine, en journées ou demi-journées (quelques jours par
          mois pour une équipe déjà autonome) ; roadmap technique à 6-12 mois, décisions d'architecture, plan de réduction de
          la dette, standards et tests ; utile pendant un recrutement, une reprise, une levée de fonds ou une due diligence.
        - IA : assistants et agents métier, recherche documentaire (RAG), extraction de documents, fonctions IA dans un
          produit, intégration à l'ERP, au CRM ou à Microsoft 365 ; reprise et mise en production d'un POC IA
          (authentification, droits, supervision, coûts) ; évaluation et suivi des coûts des modèles ; diagnostic IA gratuit.
        - Reprise d'applications existantes, y compris celles faites en « vibe coding » avec Lovable ou Bolt.
        Technologies : PHP/Symfony/Laravel, Java/Spring Boot, .NET/C#, Python, Node.js/NestJS, React/Next.js, Angular,
        Vue.js, Flutter, React Native, Odoo, PostgreSQL, MySQL, Docker, Kubernetes ; OpenAI, Claude, Gemini, Mistral,
        LangChain.

        Le cadre : démarrage généralement en 4 à 8 semaines ; aucun tarif public (le prix dépend du niveau des profils, des
        technologies, du format et de la durée ; un catalogue de prix est envoyé sous 24 h ouvrées sur demande, suivi d'un
        appel) ; première étape : une visio de 30 minutes avec le CTO, gratuite et sans engagement, suivie d'un récapitulatif
        écrit avec un ordre de grandeur. Le code, les dépôts, les accès et la documentation appartiennent au client, qui peut
        reprendre la main à tout moment ; NDA possible ; RGPD encadré par contrat (clauses contractuelles types pour les
        transferts hors UE), accès nominatifs par rôle, environnements séparés, données anonymisées autant que possible ;
        revue de code systématique, tests automatisés, intégration continue, documentation vivante ; travail visible
        (démonstrations régulières, alertes écrites sur les risques de délai) ; continuité assurée (cloud, connexions et
        postes redondés, alimentation de secours, relais entre les sites) ; en cas de départ d'un développeur :
        documentation, passation encadrée, remplacement organisé. Langue de travail : le français (l'anglais depuis Maurice et le Vietnam).
        Références : 7 secteurs (santé, finance, industrie, formation, télécoms, immobilier, services), par exemple Gorilla
        eSIM (web, mobile et plateforme d'affiliation, équipe de cinq), un agent IA de synthèse de dossier patient pour une
        équipe d'anesthésie, une grande banque malgache, un éditeur SaaS de conformité, une plateforme e-learning en Symfony.

        Ce qu'Etixio ne propose pas, ou que rien ne garantit : pas de nearshore (Maroc, Tunisie…), pas de développeurs en
        France, pas de démarrage en quelques jours, pas de période d'essai gratuite ni de remplacement gratuit garanti, pas de
        tarif journalier public, pas de SLA chiffré ni de certification ISO 27001 annoncés, pas de test d'intrusion dans
        l'audit.

        Les objections classiques à l'offshore : « la qualité n'est pas au rendez-vous », « on a déjà été brûlé par l'Inde
        ou un offshore low-cost », « la communication et le décalage horaire », « le turnover des développeurs »,
        « la sécurité et le RGPD hors de l'UE », « la propriété du code », « des juniors facturés comme des seniors »,
        « je veux pouvoir leur parler directement », « mes équipes internes vont le vivre comme une menace », « un freelance
        ou le nearshore au Maroc coûte moins cher ou est plus proche », « la stabilité du pays (Madagascar) »,
        « je ne peux pas attendre 4 à 8 semaines ».
        TXT;
}
