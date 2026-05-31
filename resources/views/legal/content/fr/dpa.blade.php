<h2>1. Parties</h2>

<p>Le présent accord de sous-traitance est conclu entre :</p>

<p><strong>Client / administrateur (tenant)</strong><br>
(responsable du traitement)</p>

<p>et</p>

@include('partials.wp-legal-operator')

<p>(ci-après « WinProx », le sous-traitant)</p>

<p>
    Le présent accord est régi par le règlement (UE) 2016/679 (RGPD) et la loi belge fédérale relative à la protection des données (loi du 30 juillet 2018) ; il met en œuvre l’article 28 RGPD pour le traitement sur instruction du client.
</p>

<h2>2. Objet</h2>

<p>
    WinProx traite des données personnelles sur instruction du client dans le cadre de l’utilisation de la plateforme pour
    la gestion de sites, les signalements QR et le suivi des issues et tâches.
</p>

<h2>3. Finalité du traitement</h2>

<p>Le traitement comprend :</p>

<ul>
    <li>gestion des issues et tâches</li>
    <li>gestion des utilisateurs et équipes internes</li>
    <li>gestion des exécutants (sans connexion) et affectation aux tâches</li>
    <li>gestion des sites et unités</li>
    <li>envoi de notifications par e-mail sur instruction du client</li>
    <li>journalisation et sécurité</li>
</ul>

<h2>4. Types de données</h2>

<ul>
    <li>données d’identification (nom, adresse e-mail, numéro de téléphone le cas échéant)</li>
    <li>données de site et d’unité (adresses, détails de localisation)</li>
    <li>données d’issues et de tâches (y compris photos et descriptions)</li>
    <li>données des exécutants et des personnes signalant via QR, dans la mesure collectées par le client</li>
    <li>données d’accès et de session</li>
    <li>métadonnées d’abonnement et d’accès</li>
</ul>

<h2>5. Obligations de WinProx</h2>

<p>WinProx s’engage à :</p>

<ul>
    <li>traiter les données uniquement sur instruction du client</li>
    <li>mettre en œuvre des mesures de sécurité appropriées</li>
    <li>restreindre l’accès aux personnes autorisées</li>
    <li>garantir la confidentialité</li>
</ul>

<h2>6. Sécurité</h2>

<p>WinProx met notamment en place :</p>

<ul>
    <li>isolation des tenants</li>
    <li>contrôle d’accès</li>
    <li>journalisation</li>
</ul>

<h2>7. {{ __('legal.documents.subprocessors') }}</h2>

<p>
    WinProx peut recourir à des tiers pour l’hébergement, l’infrastructure, l’e-mail et (le cas échéant) les paiements.
</p>

<p>
    Ces parties sont soigneusement sélectionnées et soumises à des garanties contractuelles appropriées. Un aperçu à jour est disponible sur
    <a href="{{ route('legal.subprocessors') }}">{{ __('legal.documents.subprocessors') }}</a>.
</p>

<h2>8. Violations de données</h2>

<p>
    WinProx informera le client sans retard injustifié en cas de violation de données personnelles.
</p>

<h2>9. Droits des personnes concernées</h2>

<p>
    WinProx assiste le client dans le traitement des demandes des personnes concernées.
</p>

<h2>10. Durées de conservation</h2>

<p>
    Les données sont conservées conformément à la politique de conservation décrite dans la
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>, notamment :
</p>
<ul>
    <li>comptes utilisateurs : actif + 24 mois</li>
    <li>issues et tâches : durée du contrat + 36 mois</li>
    <li>journaux : 6 mois</li>
    <li>photos : 24 mois après clôture</li>
</ul>

<h2>11. Fin de l’accord</h2>

<p>
    À la fin de l’utilisation de la plateforme :
</p>

<ul>
    <li>le client peut exporter les données</li>
    <li>les données seront supprimées conformément à la politique de conservation</li>
</ul>

<h2>12. Responsabilité</h2>

<p>
    La responsabilité de WinProx est limitée comme prévu dans les
    <a href="{{ route('legal.terms') }}">{{ __('legal.documents.terms') }}</a>.
</p>

<h2>13. Droit applicable</h2>

<p>
    Le présent accord est régi par le droit belge.
</p>
