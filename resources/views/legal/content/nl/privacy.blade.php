<h2>1. Wie zijn wij</h2>
<p>
    WinProx (“Work in Proximity”) is een SaaS-platform voor technisch en operationeel locatiebeheer:
    QR-meldingen, opvolging van issues en taken voor interne operationele teams, en optioneel
    ESG-/compliance-registratie en IoT Connect (sensor-events naar workflow).
</p>
<p>
    Het platform wordt geëxploiteerd door:
</p>

@include('partials.wp-legal-operator')

<h2>2. Rollen onder de AVG/GDPR (EU en België)</h2>
<p>Binnen het platform gelden de volgende rollen:</p>
<ul>
    <li>De klant / beheerder is de verwerkingsverantwoordelijke.</li>
    <li>WinProx is de verwerker.</li>
</ul>
<p>Dit betekent:</p>
<ul>
    <li>De klant bepaalt welke persoonsgegevens worden verwerkt en voor welk doel.</li>
    <li>WinProx verwerkt persoonsgegevens uitsluitend in opdracht van de klant.</li>
</ul>

<h2>3. Welke gegevens wij verwerken</h2>

<p><strong>Gebruikers</strong></p>
<ul>
    <li>naam.</li>
    <li>e-mailadres.</li>
    <li>rol binnen de organisatie.</li>
    <li>voorkeurstaal, indien van toepassing.</li>
</ul>

<p><strong>Inloggen met Microsoft (optioneel)</strong></p>
<p>
    Beheerders en medewerkers kunnen zich op het desktop-inlogscherm aanmelden via Microsoft
    (Microsoft Entra ID), naast e-mail en wachtwoord. WinProx maakt daarbij geen nieuwe accounts:
    het e-mailadres van het Microsoft-account moet overeenkomen met een bestaande, actieve
    WinProx-gebruiker (beheerder of medewerker). Uitvoerders en gasten gebruiken deze aanmelding niet.
</p>
<ul>
    <li>de gebruiker wordt doorgestuurd naar Microsoft om zich te identificeren;</li>
    <li>WinProx ontvangt identificerende gegevens van Microsoft (doorgaans e-mailadres en naam) om het bestaande account te koppelen;</li>
    <li>wachtwoorden van Microsoft-accounts worden niet via deze flow in WinProx opgeslagen;</li>
    <li>het bestaande WinProx-wachtwoord blijft aanwezig (o.a. voor herstel en organisatieverwijdering).</li>
</ul>

<p><strong>Abonnement en facturatie</strong></p>
<ul>
    <li>gekozen abonnementsformule (indien van toepassing).</li>
    <li>einddatum van de proefperiode en van het betaalde abonnement.</li>
    <li>facturatie- en betalingsgegevens die u of uw organisatie invoert of die via een betalingsprovider worden verwerkt.</li>
</ul>

<p><strong>Locaties en units</strong></p>
<ul>
    <li>locaties (sites) en units binnen uw organisatie.</li>
    <li>adressen en locatiegegevens die u invoert.</li>
</ul>

<p><strong>Meldingen en taken</strong></p>
<ul>
    <li>meldingen (issues) en taken.</li>
    <li>beschrijvingen, statussen en opvolging.</li>
    <li>communicatie en historiek binnen het platform.</li>
    <li>foto’s en bijlagen die bij meldingen of taken worden toegevoegd.</li>
    <li>unit checks (OK/Niet OK via unit-QR door uitvoerders), indien ingeschakeld op categorie en unit.</li>
</ul>

<p><strong>Uitvoerders (zonder login)</strong></p>
<ul>
    <li>naam of weergavenaam.</li>
    <li>contactgegevens (zoals e-mailadres), indien door de klant ingevoerd.</li>
    <li>toewijzing aan taken binnen interne teams.</li>
</ul>
<p>
    Deze gegevens worden door de klant / beheerder beheerd. WinProx heeft geen inhoudelijke controle over wat de klant invoert.
</p>

<p><strong>QR-meldingen</strong></p>
<ul>
    <li>gegevens die een melder vrijwillig invult via een publiek QR-portaal (zoals naam, e-mailadres of beschrijving).</li>
    <li>technische metadata die nodig is voor beveiliging en misbruikpreventie.</li>
</ul>
<p><strong>Unit checks</strong></p>
<p>
    Indien de klant Unit checks inschakelt op categorie én unit, kunnen uitvoerders via de unit-QR (na aanmelding via Clock Point) een OK- of Niet OK-resultaat registreren, eventueel met checklist en GPS. Dit is geen melding: bij OK wordt geen issue aangemaakt. WinProx bewaart resultaat, tijdstip, unit, optioneel GPS-coördinaten en de uitvoerder. Bewaartermijn geldt dezelfde als voor meldingen en taken, tenzij anders bepaald in uw organisatie.
</p>


<p><strong>ESG & Compliance (optionele module)</strong></p>
<p>
    Indien de klant de optionele ESG-module activeert, kunnen meetwaarden en compliancegegevens worden vastgelegd,
    bijvoorbeeld bij terugkerende inspecties, bij het afhandelen van taken op het QR-portaal, via de API of — indien
    IoT Connect is geactiveerd — via sensorgegevens.
</p>
<ul>
    <li>indicatordefinities (naam, type, eenheid, drempels, opties), inclusief eventuele vertalingen van indicatorteksten.</li>
    <li>meetwaarden (zoals getal, ja/nee, keuze of tekst) met tijdstip van meting.</li>
    <li>koppeling aan melding, taak, locatie, unit en optioneel de uitvoerder (worker); bij sensorpad kan een taak ontbreken.</li>
    <li>correcties als nieuwe meetrijen (append-only); eerdere waarden blijven bewaard.</li>
    <li>drempelalarmen en daaruit voortvloeiende opvolgtaken wanneer een meting buiten ingestelde grenzen valt.</li>
    <li>API-aanmaak van metingen en optionele webhooks (bijv. bij een nieuwe meetrij), indien de klant die koppelt.</li>
</ul>
<p>
    De module is optioneel en enkel zichtbaar voor beheerders wanneer ze is ingeschakeld. De klant is verantwoordelijk
    voor de inhoud en het gebruik van ESG-gegevens binnen de eigen organisatie.
</p>

<p><strong>IoT Connect (optionele module)</strong></p>
<p>
    Indien de klant IoT Connect activeert, kunnen gateways events naar WinProx sturen. WinProx is geen IoT-cloud of
    timeseries-platform: de klant (of diens hardwarepartner) beheert gateways en sensoren; WinProx zet binnenkomende
    events om in workflow binnen de tenant.
</p>
<ul>
    <li>gatewayconfiguratie en authenticatietokens (tokens worden veilig bewaard; een nieuw token wordt doorgaans éénmalig getoond).</li>
    <li>sensorkoppelingen (externe id → locatie/unit, optioneel een ESG-indicator).</li>
    <li>alarmregels (drempels, operator, toegewezen team, prioriteit, tekst).</li>
    <li>eventrecords (verwerkt / genegeerd / gededupliceerd / mislukt) — geen continue meetreeksopslag.</li>
    <li>bij alarm: een goedgekeurde melding en taak binnen de organisatie (met deduplicatie zolang er een open taak voor dezelfde regel is).</li>
    <li>bij meting (Corporate, met ESG-module): een ESG-meetrij op basis van het sensorevent.</li>
</ul>
<p>
    Persoonsgegevens in IoT-flows zijn beperkt tot wat de klant configureert (bijv. toewijzing aan teams/uitvoerders
    via meldingen en taken). De klant blijft verantwoordelijk voor de sensorbronnen en de inhoud van events.
</p>

<h2>4. AI Vertalingen</h2>
<p>Het platform gebruikt AI-vertalingen voor meertalige weergave:</p>
<ul>
    <li>vertaling van teksten die meertalig in het platform of QR-portaal worden getoond (onder meer meldingen, taken, units, mededelingen, documentomschrijvingen, locaties, categorieën, teamnamen en ESG-indicatorteksten); teksten worden na goedkeuring klaargezet voor vertaling.</li>
    <li>gebruik van een lokale Ollama-instantie (geen externe AI-diensten / cloud).</li>
    <li>WinProx voert deze vertalingen periodiek uit (doorgaans dagelijks), zonder vaste doorlooptijd of garantie.</li>
    <li>vertalingen worden opgeslagen en bewaard volgens het retentiebeleid; organisatiebeheerders kunnen vertalingen in het platform handmatig corrigeren of aanvullen.</li>
    <li>er is geen aan/uit-schakelaar per organisatie; WinProx kan de vertaalpipeline op platformniveau stilleggen.</li>
</ul>

<h2>5. Doeleinden van verwerking</h2>
<p>Gegevens worden verwerkt voor:</p>
<ul>
    <li>het functioneren van het platform, inclusief aanmelding van beheerders en medewerkers (e-mail + wachtwoord en optioneel Microsoft Entra ID).</li>
    <li>registratie en opvolging van meldingen en taken.</li>
    <li>toewijzing aan interne teams en uitvoerders.</li>
    <li>QR-meldingen en communicatie tussen gebruikers binnen uw organisatie.</li>
    <li>het verzenden van e-mailnotificaties in opdracht van de klant.</li>
    <li>productverbetering via onboarding-statistieken (geaggregeerd waar mogelijk).</li>
    <li>beveiliging en logging.</li>
    <li>meertalige ondersteuning via AI-vertalingen (periodiek door WinProx uitgevoerd, zonder vaste doorlooptijd).</li>
    <li>registratie en opvolging van ESG-/compliance-metingen (indien de module is geactiveerd).</li>
    <li>verwerking van IoT-events tot meldingen, taken en/of ESG-metingen (indien IoT Connect is geactiveerd).</li>
</ul>

<h2>6. QR-meldingen en team-toegang</h2>
<p>
    Via QR-codes kunnen melders meldingen indienen zonder account. De klant / beheerder bepaalt welke locaties en units
    beschikbaar zijn en welke gegevens worden gevraagd.
</p>
<p>
    Ingelogde gebruikers en interne teams hebben toegang volgens de rechten die de klant instelt. WinProx verwerkt
    persoonsgegevens in dat kader uitsluitend als technische uitvoerder van de instructies van de klant.
</p>

<h2>7. Support en toegang</h2>
<p>
    Voor technische ondersteuning kan WinProx in uitzonderlijke gevallen toegang krijgen tot gegevens via een
    supportmodus voor superuser- of supportmedewerkers:
</p>
<ul>
    <li>enkel voor technische ondersteuning en probleemoplossing.</li>
    <li>standaard raadpleging zonder schrijfrechten (alleen lezen).</li>
    <li>zonder actieve wijzigingen aan klantgegevens, tenzij u daar uitdrukkelijk om vraagt.</li>
</ul>

<h2>8. Bewaartermijnen</h2>
<p>WinProx hanteert de volgende bewaartermijnen:</p>
<ul>
    <li>gebruikersaccounts: actief + 24 maanden.</li>
    <li>meldingen en taken: contractperiode + 36 maanden.</li>
    <li>unit checks: dezelfde bewaartermijn als meldingen en taken (contractperiode + 36 maanden).</li>
    <li>logs: 6 maanden.</li>
    <li>onboarding-events per gebruiker (voor onboarding-statistieken): 6 maanden; geaggregeerde onboardingcijfers zonder persoonsdata blijven langer bewaard.</li>
    <li>media (foto’s): 24 maanden na afsluiten van de betreffende melding of taak.</li>
    <li>ESG-metingen: dezelfde bewaartermijn als meldingen en taken (contractperiode + 36 maanden).</li>
    <li>IoT-events, gateway- en sensormetadata: contractperiode + 36 maanden (of korter indien de onderliggende melding/taak eerder wordt verwijderd in het kader van organisatieverwijdering).</li>
    <li>operationele infrastructuurbackups (hosting/Cloud86): 7 dagen.</li>
    <li>technische SQL-snapshot na een volledige organisatieverwijdering (zonder mediabestanden): maximaal 30 dagen, daarna vernietiging.</li>
</ul>
<p>
    Bij een volledige organisatieverwijdering (zie hieronder) worden de live gegevens van de tenant hard verwijderd;
    mediabestanden (foto’s, documenten) maken geen deel uit van de herstel-snapshot.
</p>

<h2>9. Delen van gegevens</h2>
<p>Persoonsgegevens worden niet verkocht of gedeeld met derden, behalve:</p>
<ul>
    <li>in opdracht van de klant.</li>
    <li>voor hosting en technische infrastructuur.</li>
    <li>voor betalingsverwerking, indien u daarvoor kiest (via een erkende betalingspartner).</li>
    <li>voor aanmelding via Microsoft Entra ID, wanneer de gebruiker Inloggen met Microsoft kiest.</li>
    <li>indien wettelijk verplicht.</li>
</ul>
<p>
    Een overzicht van categorieën subverwerkers vindt u op de pagina
    <a href="{{ route('legal.subprocessors') }}">{{ __('legal.documents.subprocessors') }}</a>.
</p>

<h2>10. Internationale beschikbaarheid</h2>
<p>
    WinProx is een internationaal platform en kan in meerdere landen worden gebruikt.
</p>
<p>
    Het platform kan in verschillende talen beschikbaar zijn, waaronder Nederlands, Engels, Frans, Duits, Spaans en Italiaans.
</p>
<p>
    Ongeacht de taalversie blijft deze privacyverklaring van toepassing op de verwerking van persoonsgegevens.
</p>

<h2>11. Rechten van betrokkenen</h2>
<p>Betrokkenen hebben het recht om:</p>
<ul>
    <li>hun gegevens in te zien.</li>
    <li>hun gegevens te laten corrigeren.</li>
    <li>hun gegevens te laten verwijderen.</li>
    <li>bezwaar te maken tegen de verwerking.</li>
</ul>

<p><strong>Hoe het platform dit ondersteunt</strong></p>
<ul>
    <li>
        <strong>Inzage / export:</strong> een beheerder kan onder
        <em>Instellingen → Privacy &amp; export van gegevens</em> een machineleesbare export (JSON in een ZIP)
        downloaden van het eigen account en relevante gegevens binnen de organisatie. Downloads worden gelogd.
    </li>
    <li>
        <strong>Rectificatie:</strong> gebruikers met rechten kunnen hun profiel (naam, e-mail, taal) aanpassen;
        beheerders kunnen organisatiegegevens aanpassen.
    </li>
    <li>
        <strong>Gebruiker deactiveren:</strong> een beheerder kan collega-accounts deactiveren of pauzeren
        (login wordt geblokkeerd; sessies worden ingetrokken). Dit is geen volledige wissing van de organisatie.
    </li>
    <li>
        <strong>Organisatiegegevens verwijderen (self-service):</strong> alleen een beheerder, via
        <em>Abonnement → Organisatiegegevens verwijderen</em>. Eerst wordt een export aangeboden; daarna bevestiging
        met wachtwoord en e-mail naar alle beheerders.
        <ul>
            <li><strong>Proefperiode:</strong> na e-mailbevestiging kan de beheerder zelf definitief wissen
                (technische SQL-snapshot zonder media, max. 30 dagen bewaard).</li>
            <li><strong>Betaald abonnement / grace:</strong> na bevestiging volgt een wachttijd van 7 dagen
                (banner in de app, herinneringsmail ongeveer 2 dagen vóór uitvoering); WinProx-administratie
                (superuser) voert de verwijdering uit. Annuleren kan tot die tijd via Abonnement.</li>
        </ul>
    </li>
    <li>
        <strong>Verlopen proef zonder abonnement:</strong> na het einde van de proefperiode blijft login beperkt
        tot abonnements-/facturatiezaken. Zonder abonnement stuurt WinProx waarschuwingsmails en kan de organisatie
        automatisch worden verwijderd (standaard: waarschuwing rond dag 7, uitvoering rond dag 14 na einde proef).
        Activeren van een abonnement annuleert een openstaande automatische verwijdering.
    </li>
</ul>

<p>Overige of uitzonderlijke verzoeken (bijv. litigation hold) kunnen gericht worden aan:</p>
@include('partials.wp-legal-operator')

<p>
    Indien de gegevens verwerkt worden in opdracht van een klant, kan het nodig zijn om het verzoek via deze klant te behandelen.
</p>

<h2>12. Beveiliging</h2>
<p>WinProx neemt passende technische en organisatorische maatregelen, waaronder:</p>
<ul>
    <li>tenant-isolatie.</li>
    <li>toegangscontrole.</li>
    <li>logging.</li>
    <li>automatische dagelijkse backups via de hostingprovider (Cloud86), 7 dagen bewaard.</li>
    <li>richtwaarde herstel: RPO ≈ 24 uur (max. dataverlies sinds de laatste nachtelijke backup); RTO best effort, doorgaans binnen 1 werkdag.</li>
</ul>
<p>
    Zie ook het <a href="{{ route('legal.cookies') }}">{{ __('legal.documents.cookies') }}</a> voor informatie over
    strikt noodzakelijke cookies.
</p>

<h2>13. Internationale doorgifte</h2>
<p>
    Gegevens worden in principe binnen de Europese Unie verwerkt.
</p>
<p>
    Indien externe dienstverleners worden gebruikt, worden passende waarborgen voorzien.
</p>

<h2>14. Toezichthoudende autoriteit</h2>
<p>
    U hebt het recht een klacht in te dienen bij een toezichthoudende autoriteit. In België is dat de Gegevensbeschermingsautoriteit
    (<a href="https://www.gegevensbeschermingsautoriteit.be" rel="noopener noreferrer" target="_blank">www.gegevensbeschermingsautoriteit.be</a>).
</p>

<h2>15. Wijzigingen</h2>
<p>
    Deze privacyverklaring kan worden aangepast.
</p>
<p>
    De meest recente versie is steeds beschikbaar via het platform.
</p>
