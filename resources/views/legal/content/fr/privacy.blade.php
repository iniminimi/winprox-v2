<h2>1. Qui sommes-nous</h2>
<p>
    WinProx (« Work in Proximity ») est une plateforme SaaS de gestion technique et opérationnelle de sites :
    signalements par QR, suivi des issues et des tâches pour les équipes opérationnelles internes, et enregistrement
    ESG/conformité en option ainsi qu’IoT Connect (événements capteurs vers le workflow).
</p>
<p>
    La plateforme est exploitée par :
</p>

@include('partials.wp-legal-operator')

<h2>2. Rôles au titre du RGPD (UE et Belgique)</h2>
<p>Les rôles suivants s’appliquent au sein de la plateforme :</p>
<ul>
    <li>Le client / administrateur est responsable du traitement.</li>
    <li>WinProx est sous-traitant.</li>
</ul>
<p>Cela signifie :</p>
<ul>
    <li>Le client détermine quelles données personnelles sont traitées et dans quel but.</li>
    <li>WinProx traite les données personnelles uniquement sur instruction du client.</li>
</ul>

<h2>3. Données que nous traitons</h2>

<p><strong>Utilisateurs</strong></p>
<ul>
    <li>nom.</li>
    <li>adresse e-mail.</li>
    <li>rôle au sein de l’organisation.</li>
    <li>langue préférée le cas échéant.</li>
</ul>

<p><strong>Connexion avec Microsoft (facultatif)</strong></p>
<p>
    Les administrateurs et collaborateurs peuvent se connecter sur l’écran de connexion ordinateur via Microsoft
    (Microsoft Entra ID), en plus de l’e-mail et du mot de passe. WinProx ne crée pas de nouveaux comptes :
    l’adresse e-mail du compte Microsoft doit correspondre à un utilisateur WinProx existant et actif
    (administrateur ou collaborateur). Les exécutants et les invités n’utilisent pas cette connexion.
</p>
<ul>
    <li>l’utilisateur est redirigé vers Microsoft pour s’identifier ;</li>
    <li>WinProx reçoit des données d’identification de Microsoft (généralement e-mail et nom) pour associer le compte existant ;</li>
    <li>les mots de passe des comptes Microsoft ne sont pas stockés dans WinProx via ce flux ;</li>
    <li>le mot de passe WinProx existant reste en place (notamment pour la récupération et la suppression de l’organisation).</li>
</ul>

<p><strong>Abonnement et facturation</strong></p>
<ul>
    <li>formule d’abonnement choisie (le cas échéant).</li>
    <li>date de fin de la période d’essai et de l’abonnement payant.</li>
    <li>données de facturation et de paiement saisies par vous ou votre organisation, ou traitées via un prestataire de paiement.</li>
</ul>

<p><strong>Sites et unités</strong></p>
<ul>
    <li>sites et unités au sein de votre organisation.</li>
    <li>adresses et données de localisation que vous saisissez.</li>
</ul>

<p><strong>Signalements et tâches</strong></p>
<ul>
    <li>issues et tâches.</li>
    <li>descriptions, statuts et suivi.</li>
    <li>communication et historique au sein de la plateforme.</li>
    <li>photos et pièces jointes ajoutées aux signalements ou tâches.</li>
    <li>contrôles d'unité (OK/Non OK via QR unité par exécutants), si activés sur catégorie et unité.</li>
</ul>

<p><strong>Exécutants (sans connexion)</strong></p>
<ul>
    <li>nom ou nom d’affichage.</li>
    <li>coordonnées (par ex. adresse e-mail), si saisies par le client.</li>
    <li>affectation aux tâches au sein des équipes internes.</li>
</ul>
<p>
    Ces données sont gérées par le client / administrateur. WinProx n’exerce aucun contrôle de fond sur ce que le client saisit.
</p>

<p><strong>Signalements QR</strong></p>
<ul>
    <li>données fournies volontairement via un portail QR public (nom, adresse e-mail ou description).</li>
    <li>métadonnées techniques nécessaires à la sécurité et à la prévention des abus.</li>
</ul>
<p><strong>Contrôles d'unité</strong></p>
<p>
    Si le client active les contrôles d'unité sur catégorie et unité, les exécutants peuvent enregistrer un résultat OK ou Non OK via le QR unité (après Clock Point), éventuellement avec checklist et GPS. Ce n'est pas un signalement : OK ne crée pas d'issue. WinProx conserve le résultat, l'horodatage, l'unité, les coordonnées GPS optionnelles et l'exécutant. Conservation identique aux signalements et tâches, sauf règle interne différente.
</p>


<p><strong>ESG & Conformité (module optionnel)</strong></p>
<p>
    Si le client active le module ESG optionnel, des valeurs de mesure et des données de conformité peuvent être enregistrées,
    par exemple lors d’inspections récurrentes, lors de l’exécution de tâches sur le portail QR, via l’API ou — si
    IoT Connect est activé — à partir d’événements capteurs.
</p>
<ul>
    <li>définitions d’indicateurs (nom, type, unité, seuils, options), y compris d’éventuelles traductions des textes d’indicateurs.</li>
    <li>valeurs de mesure (nombre, oui/non, choix ou texte) avec horodatage.</li>
    <li>lien vers le signalement, la tâche, le site, l’unité et éventuellement l’exécutant ; sur le chemin capteur, une tâche peut être absente.</li>
    <li>corrections sous forme de nouvelles lignes (append-only) ; les valeurs antérieures sont conservées.</li>
    <li>alarmes de seuil et tâches de suivi qui en résultent lorsqu’une mesure sort des limites configurées.</li>
    <li>création de mesures via API et webhooks optionnels (p. ex. lors d’une nouvelle ligne de mesure), si le client les connecte.</li>
</ul>
<p>
    Le module est optionnel et visible uniquement par les administrateurs lorsqu’il est activé. Le client est responsable
    du contenu et de l’usage des données ESG au sein de son organisation.
</p>

<p><strong>IoT Connect (module optionnel)</strong></p>
<p>
    Si le client active IoT Connect, des gateways peuvent envoyer des événements à WinProx. WinProx n’est pas un cloud IoT
    ni une plateforme de séries temporelles : le client (ou son partenaire matériel) gère gateways et capteurs ; WinProx
    transforme les événements entrants en workflow au sein du tenant.
</p>
<ul>
    <li>configuration des gateways et jetons d’authentification (stockés de façon sécurisée ; un nouveau jeton est généralement affiché une seule fois).</li>
    <li>associations de capteurs (id externe → site/unité, éventuellement un indicateur ESG).</li>
    <li>règles d’alarme (seuils, opérateur, équipe assignée, priorité, texte).</li>
    <li>enregistrements d’événements (traité / ignoré / dédupliqué / échoué) — pas de stockage continu en séries temporelles.</li>
    <li>en cas d’alarme : un signalement et une tâche approuvés dans l’organisation (avec déduplication tant qu’une tâche ouverte existe pour la même règle).</li>
    <li>en cas de mesure (Corporate, avec module ESG) : une ligne de mesure ESG basée sur l’événement capteur.</li>
</ul>
<p>
    Les données personnelles dans les flux IoT se limitent à ce que le client configure (p. ex. affectation aux équipes/exécutants
    via signalements et tâches). Le client reste responsable des sources capteurs et du contenu des événements.
</p>

<h2>4. Traductions IA</h2>
<p>La plateforme utilise des traductions IA pour l’affichage multilingue :</p>
<ul>
    <li>traduction des textes affichés en plusieurs langues dans la plateforme ou le portail QR (notamment signalements, tâches, unités, annonces, descriptions de documents, sites, catégories, noms d’équipes et textes d’indicateurs ESG) ; les textes sont mis en file d’attente après approbation.</li>
    <li>utilisation d’une instance Ollama locale (aucun service / cloud IA externe).</li>
    <li>WinProx exécute ces traductions périodiquement (en général quotidiennement), sans délai garanti.</li>
    <li>les traductions sont stockées et conservées conformément à la politique de conservation ; les administrateurs de l’organisation peuvent les corriger ou les compléter manuellement dans la plateforme.</li>
    <li>il n’existe pas d’interrupteur on/off par organisation ; WinProx peut suspendre le pipeline de traduction au niveau de la plateforme.</li>
</ul>

<h2>5. Finalités du traitement</h2>
<p>Les données sont traitées pour :</p>
<ul>
    <li>le fonctionnement de la plateforme, y compris la connexion des administrateurs et collaborateurs (e-mail + mot de passe et, le cas échéant, Microsoft Entra ID).</li>
    <li>l’enregistrement et le suivi des issues et tâches.</li>
    <li>l’affectation aux équipes internes et aux exécutants.</li>
    <li>les signalements QR et la communication entre utilisateurs au sein de votre organisation.</li>
    <li>l’envoi de notifications par e-mail sur instruction du client.</li>
    <li>l’amélioration du produit via des statistiques d'onboarding (agrégées lorsque possible).</li>
    <li>la sécurité et la journalisation.</li>
    <li>la prise en charge multilingue via les traductions IA (exécutées périodiquement par WinProx, sans délai garanti).</li>
    <li>l’enregistrement et le suivi des mesures ESG/conformité (si le module est activé).</li>
    <li>le traitement des événements IoT en signalements, tâches et/ou mesures ESG (si IoT Connect est activé).</li>
</ul>

<h2>6. Signalements QR et accès des équipes</h2>
<p>
    Les codes QR permettent de soumettre des signalements sans compte. Le client / administrateur détermine quels sites
    et unités sont disponibles et quelles données sont demandées.
</p>
<p>
    Les utilisateurs connectés et les équipes internes y accèdent selon les droits définis par le client. WinProx traite
    les données personnelles dans ce cadre uniquement en tant qu’exécutant technique sur instruction du client.
</p>

<h2>7. Support et accès</h2>
<p>
    Pour le support technique, WinProx peut, dans des cas exceptionnels, accéder aux données via un mode support pour superuser ou personnel de support :
</p>
<ul>
    <li>uniquement pour le support technique et le dépannage.</li>
    <li>en lecture seule par défaut.</li>
    <li>sans modification active des données client, sauf demande expresse de votre part.</li>
</ul>

<h2>8. Durées de conservation</h2>
<p>WinProx applique les durées de conservation suivantes :</p>
<ul>
    <li>comptes utilisateurs : actif + 24 mois.</li>
    <li>issues et tâches : durée du contrat + 36 mois.</li>
    <li>contrôles d’unité : même durée de conservation que les signalements et tâches (durée du contrat + 36 mois).</li>
    <li>journaux : 6 mois.</li>
    <li>événements d’onboarding par utilisateur (statistiques d’onboarding) : 6 mois ; les chiffres agrégés sans données personnelles peuvent être conservés plus longtemps.</li>
    <li>médias (photos) : 24 mois après clôture du signalement ou de la tâche concerné(e).</li>
    <li>mesures ESG : même durée de conservation que les signalements et tâches (durée du contrat + 36 mois).</li>
    <li>événements IoT, métadonnées de gateway et de capteur : durée du contrat + 36 mois (ou plus court si le signalement/la tâche sous-jacent est supprimé plus tôt lors d’une suppression d’organisation).</li>
    <li>sauvegardes opérationnelles d’infrastructure (hébergement/Cloud86) : 7 jours.</li>
    <li>instantané SQL technique après suppression complète de l’organisation (sans fichiers médias) : maximum 30 jours, puis destruction.</li>
</ul>
<p>
    Après une suppression complète de l’organisation (voir ci-dessous), les données actives du tenant sont définitivement effacées ;
    les fichiers médias (photos, documents) ne font pas partie de l’instantané de récupération.
</p>

<h2>9. Partage des données</h2>
<p>Les données personnelles ne sont ni vendues ni partagées avec des tiers, sauf :</p>
<ul>
    <li>sur instruction du client.</li>
    <li>pour l’hébergement et l’infrastructure technique.</li>
    <li>pour le traitement des paiements, si vous choisissez cette option (via un partenaire de paiement reconnu).</li>
    <li>pour la connexion via Microsoft Entra ID, lorsque l’utilisateur choisit Se connecter avec Microsoft.</li>
    <li>si la loi l’exige.</li>
</ul>
<p>
    Un aperçu des catégories de sous-traitants est disponible sur la page
    <a href="{{ route('legal.subprocessors') }}">{{ __('legal.documents.subprocessors') }}</a>.
</p>

<h2>10. Disponibilité internationale</h2>
<p>
    WinProx est une plateforme internationale utilisable dans plusieurs pays.
</p>
<p>
    La plateforme peut être disponible en plusieurs langues, dont le néerlandais, l’anglais, le français, l’allemand, l’espagnol et l’italien.
</p>
<p>
    Quelle que soit la version linguistique, la présente politique de confidentialité s’applique au traitement des données personnelles.
</p>

<h2>11. Droits des personnes concernées</h2>
<p>Les personnes concernées ont le droit de :</p>
<ul>
    <li>consulter leurs données.</li>
    <li>rectifier leurs données.</li>
    <li>demander l’effacement de leurs données.</li>
    <li>s’opposer au traitement.</li>
</ul>

<p><strong>Comment la plateforme le permet</strong></p>
<ul>
    <li>
        <strong>Accès / export :</strong> un administrateur peut télécharger un export lisible par machine (JSON dans un ZIP)
        sous <em>Paramètres → Confidentialité &amp; export des données</em> pour son compte et les données pertinentes de l’organisation.
        Les téléchargements sont journalisés.
    </li>
    <li>
        <strong>Rectification :</strong> les utilisateurs autorisés peuvent mettre à jour leur profil (nom, e-mail, langue) ;
        les administrateurs peuvent mettre à jour les données de l’organisation.
    </li>
    <li>
        <strong>Désactiver un utilisateur :</strong> un administrateur peut désactiver ou suspendre des comptes collègues
        (connexion bloquée ; sessions révoquées). Ce n’est pas une suppression complète de l’organisation.
    </li>
    <li>
        <strong>Supprimer les données de l’organisation (self-service) :</strong> administrateurs uniquement, via
        <em>Abonnement → Supprimer les données de l’organisation</em>. Un export est d’abord proposé ; puis confirmation
        par mot de passe et e-mail à tous les administrateurs.
        <ul>
            <li><strong>Période d’essai :</strong> après confirmation par e-mail, l’administrateur peut effacer définitivement
                (instantané SQL technique sans médias, conservé max. 30 jours).</li>
            <li><strong>Abonnement payant / grâce :</strong> après confirmation, un délai de 7 jours s’applique
                (bannière dans l’app, e-mail de rappel environ 2 jours avant) ; l’administration WinProx (superuser)
                exécute la suppression. Annulation possible jusque-là via Abonnement.</li>
        </ul>
    </li>
    <li>
        <strong>Essai expiré sans abonnement :</strong> après la fin de l’essai, la connexion peut être limitée aux pages
        d’abonnement/facturation. Sans abonnement, WinProx envoie des e-mails d’avertissement et peut supprimer
        l’organisation automatiquement (par défaut : avertissement vers le jour 7, suppression vers le jour 14 après la fin de l’essai).
        L’activation d’un abonnement annule une suppression automatique en cours.
    </li>
</ul>

<p>Les autres demandes ou demandes exceptionnelles (p. ex. litigation hold) peuvent être adressées à :</p>
@include('partials.wp-legal-operator')

<p>
    Lorsque les données sont traitées sur instruction d’un client, il peut être nécessaire de traiter la demande via ce client.
</p>

<h2>12. Sécurité</h2>
<p>WinProx met en œuvre des mesures techniques et organisationnelles appropriées, notamment :</p>
<ul>
    <li>isolation des tenants.</li>
    <li>contrôle d’accès.</li>
    <li>journalisation.</li>
    <li>sauvegardes quotidiennes automatiques via l’hébergeur (Cloud86), conservées 7 jours.</li>
    <li>objectifs de reprise : RPO ≈ 24 heures (perte de données max. depuis la dernière sauvegarde nocturne) ; RTO best effort, en principe sous 1 jour ouvrable.</li>
</ul>
<p>
    Voir aussi la <a href="{{ route('legal.cookies') }}">{{ __('legal.documents.cookies') }}</a> pour les cookies strictement nécessaires.
</p>

<h2>13. Transferts internationaux</h2>
<p>
    Les données sont en principe traitées au sein de l’Union européenne.
</p>
<p>
    Lorsque des prestataires externes sont utilisés, des garanties appropriées sont mises en place.
</p>

<h2>14. Autorité de contrôle</h2>
<p>
    Vous avez le droit d’introduire une réclamation auprès d’une autorité de contrôle. En Belgique, il s’agit de l’Autorité de protection des données
    (<a href="https://www.gegevensbeschermingsautoriteit.be" rel="noopener noreferrer" target="_blank">www.gegevensbeschermingsautoriteit.be</a>).
</p>

<h2>15. Modifications</h2>
<p>
    La présente politique de confidentialité peut être modifiée.
</p>
<p>
    La version la plus récente est toujours disponible via la plateforme.
</p>
