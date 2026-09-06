<h2>1. Algemeen</h2>
<p>
    Deze gebruiksvoorwaarden regelen het gebruik van het WinProx-platform.
</p>
<p>
    Door gebruik te maken van WinProx gaat de gebruiker akkoord met deze voorwaarden.
</p>
<p>
    WinProx (“Work in Proximity”) is een SaaS-platform voor technisch en operationeel locatiebeheer:
    QR-meldingen, opvolging van issues en taken voor interne operationele teams, en optioneel
    Time (prikklok), unitmetingen, optionele ESG-/compliance-registratie en IoT Connect (sensor-events naar workflow).
</p>

<h2>2. Identiteit van de dienstverlener</h2>
<p>WinProx wordt geëxploiteerd door:</p>

@include('partials.wp-legal-operator')

<h2>3. Beschrijving van de dienst</h2>
<p>
    WinProx biedt een digitaal platform aan waarmee klanten / beheerders:
</p>
<ul>
    <li>meldingen (issues) kunnen registreren, onder meer via QR-portalen.</li>
    <li>taken kunnen beheren en opvolgen.</li>
    <li>werk kunnen toewijzen aan interne teams en uitvoerders.</li>
    <li>optioneel Time (prikklok) gebruiken: in-/uitklokken via Clock Point-QR, met koppeling van één toestel per uitvoerder.</li>
    <li>optioneel ESG-/compliance-metingen kunnen registreren en opvolgen (indien de module is geactiveerd).</li>
    <li>unitmetingen (meetwaarden via unit-QR) kunnen registreren indien ingeschakeld op categorie en unit.</li>
    <li>optioneel IoT Connect gebruiken: gateways/sensoren koppelen zodat alarms en (waar van toepassing) metingen workflow in WinProx starten.</li>
</ul>
<p>
    WinProx is uitsluitend een technisch platform en voert zelf geen werken uit op locatie.
</p>

<h2>4. Geen uitvoering van werken</h2>
<p>WinProx:</p>
<ul>
    <li>voert geen technische of operationele werken uit.</li>
    <li>treedt niet op als opdrachtnemer, bemiddelaar of contractpartij voor werkzaamheden op locatie.</li>
    <li>garandeert geen resultaat of kwaliteit van werkzaamheden die door uw organisatie worden uitgevoerd.</li>
</ul>
<p>
    Alle operationele beslissingen en uitvoering blijven de verantwoordelijkheid van de klant / beheerder en diens interne teams.
</p>

<h2>5. Verantwoordelijkheid van de klant</h2>
<p>De klant / beheerder is verantwoordelijk voor:</p>
<ul>
    <li>de juistheid van ingevoerde gegevens.</li>
    <li>het gebruik van het platform binnen de organisatie.</li>
    <li>de toewijzing en opvolging van taken aan interne teams en uitvoerders.</li>
    <li>de naleving van de toepasselijke wetgeving.</li>
</ul>

<p>
    De klant blijft steeds verwerkingsverantwoordelijke voor persoonsgegevens die binnen het eigen gebruik van het platform worden verwerkt.
</p>

<h2>6. Gebruik van het platform</h2>
<p>Het is niet toegestaan om:</p>
<ul>
    <li>het platform te gebruiken voor illegale activiteiten.</li>
    <li>foutieve of misleidende informatie in te voeren.</li>
    <li>misbruik te maken van communicatie- of notificatiefunctionaliteiten.</li>
</ul>

<p>
    WinProx behoudt zich het recht voor om accounts te beperken of te blokkeren bij misbruik.
</p>
<p>
    Beheerders en medewerkers kunnen zich aanmelden met e-mail en wachtwoord, of via Microsoft
    (Inloggen met Microsoft). Aanmelding via Microsoft koppelt alleen een bestaand WinProx-account
    (e-mailadres moet overeenkomen). Uitvoerders gebruiken geen Microsoft-aanmelding.
</p>

<h2>7. Beschikbaarheid van de dienst</h2>
<p>
    WinProx streeft naar een goede werking van het platform, maar geeft geen garantie op ononderbroken beschikbaarheid.
</p>
<p>
    WinProx kan onderhoud, updates of technische wijzigingen uitvoeren.
</p>
<p>
    WinProx is niet aansprakelijk voor tijdelijke onderbrekingen.
</p>
<p>
    WinProx maakt gebruik van automatische dagelijkse backups via de hostingprovider (Cloud86), bewaard gedurende 7 dagen.
    Richtwaarde: RPO ≈ 24 uur (maximaal dataverlies sinds de laatste nachtelijke backup) en RTO best effort, doorgaans binnen 1 werkdag.
    Dit is geen uptime-garantie met boetebeding. Een technische SQL-snapshot na volledige organisatieverwijdering (zonder media, max. 30 dagen) is iets anders dan deze operationele backups.
</p>

<h2>8. Abonnement, proefperiode en betaling</h2>
<p>
    WinProx kan een beperkte proefperiode aanbieden. De duur wordt bij registratie of op het platform meegedeeld.
</p>
<p>
    Na de proefperiode is voor voortgezet gebruik een passend abonnement vereist, zoals op het platform beschreven (o.a. op basis van het aantal units en eventuele modules).
</p>
<p>
    Het abonnement betreft de toegang tot en het gebruik van het platform voor uw organisatie (tenant). Betaling, facturatie en verlenging verlopen volgens de op het platform of in offertes/facturen vermelde modaliteiten.
</p>
<p>
    Bij uitblijven van tijdige betaling of bij verlopen abonnement kan WinProx de toegang tot het platform beperken of opschorten, voor zover technisch voorzien en rekening houdend met redelijke termijnen waar van toepassing.
</p>
<p>
    Na het einde van een proefperiode zonder actief abonnement kan de toegang beperkt blijven tot abonnements- en facturatiepagina’s.
    Zonder tijdig abonnement kan WinProx de organisatie automatisch verwijderen na voorafgaande waarschuwing per e-mail
    (standaard: waarschuwing rond 7 dagen en verwijdering rond 14 dagen na einde proef). Activeren van een abonnement
    stopt een geplande automatische verwijdering.
</p>
<p>
    WinProx kan tarieven en formules aanpassen. Relevante wijzigingen worden meegedeeld via het platform en/of per e-mail met redelijke voorschottermijn.
</p>
<p>
    Voor de verwerking van persoonsgegevens in dit kader verwijzen wij naar de
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a> en, tussen onderneming en WinProx, de
    <a href="{{ route('legal.dpa') }}">{{ __('legal.documents.dpa') }}</a> waar van toepassing.
</p>

<h2>9. Aansprakelijkheid</h2>
<p>WinProx is niet aansprakelijk voor:</p>
<ul>
    <li>schade die voortvloeit uit werkzaamheden uitgevoerd door de klant of diens interne teams.</li>
    <li>fouten in operationele beslissingen of uitvoering op locatie.</li>
    <li>indirecte schade, waaronder winstverlies, gevolgschade of reputatieschade.</li>
</ul>

<p>
    Voor zover wettelijk toegestaan is de aansprakelijkheid van WinProx in alle gevallen beperkt tot het bedrag dat de klant heeft betaald voor het gebruik van het platform in de twaalf maanden voorafgaand aan het schadegeval.
</p>

<h2>10. Gegevens en privacy</h2>
<p>
    Het gebruik van persoonsgegevens wordt geregeld in de
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>.
</p>
<p>
    WinProx verwerkt gegevens in opdracht van de klant / beheerder.
</p>

<h2>11. Intellectuele eigendom</h2>
<p>
    Alle rechten met betrekking tot het platform blijven eigendom van WinProx.
</p>
<p>Het is niet toegestaan om:</p>
<ul>
    <li>software te kopiëren.</li>
    <li>delen van het platform te hergebruiken zonder voorafgaande schriftelijke toestemming.</li>
</ul>

<h2>12. Beëindiging</h2>
<p>
    WinProx kan het gebruik van het platform beëindigen of opschorten:
</p>
<ul>
    <li>bij overtreding van deze voorwaarden.</li>
    <li>bij misbruik van het platform.</li>
</ul>

<p>
    Individuele gebruikersaccounts kunnen door de beheerder van de organisatie worden gedeactiveerd of gepauzeerd
    volgens de rechten in het platform.
</p>
<p>
    Een beheerder kan de volledige organisatiegegevens self-service laten verwijderen via
    <em>Abonnement → Organisatiegegevens verwijderen</em>, na exportaanbod, wachtwoordbevestiging en e-mailbevestiging
    naar alle beheerders:
</p>
<ul>
    <li><strong>Proefperiode:</strong> na bevestiging kan de beheerder zelf definitief wissen.</li>
    <li><strong>Betaald abonnement:</strong> wachttijd van 7 dagen; uitvoering door WinProx-administratie; annuleren kan tot die tijd via Abonnement.</li>
</ul>
<p>
    Bij definitieve verwijdering wordt een technische SQL-snapshot zonder mediabestanden max. 30 dagen bewaard en daarna vernietigd.
    Details over persoonsgegevens staan in de
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>.
</p>

<h2>13. Internationale beschikbaarheid</h2>
<p>
    WinProx kan internationaal worden aangeboden en in meerdere talen beschikbaar zijn.
</p>
<p>
    Ongeacht de taalversie blijft het gebruik van het platform onderworpen aan deze voorwaarden.
</p>

<h2>14. Toepasselijk recht en bevoegde rechtbank</h2>
<p>
    Op deze voorwaarden is het Belgisch recht van toepassing, zonder afbreuk aan dwingend recht van de Europese Unie.
</p>
<p>
    Geschillen behoren tot de bevoegdheid van de rechtbanken van het gerechtelijk arrondissement van de exploitant, tenzij dwingend recht anders bepaalt.
</p>

<h2>15. Wijzigingen</h2>
<p>
    WinProx kan deze voorwaarden aanpassen.
</p>
<p>
    De meest recente versie is steeds beschikbaar via het platform.
</p>
