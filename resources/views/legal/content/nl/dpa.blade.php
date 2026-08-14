<h2>1. Partijen</h2>

<p>Deze verwerkersovereenkomst wordt gesloten tussen:</p>

<p><strong>Klant / beheerder (tenant)</strong><br>
(de verwerkingsverantwoordelijke)</p>

<p>en</p>

@include('partials.wp-legal-operator')

<p>(hierna “WinProx”, de verwerker)</p>

<p>
    Deze verwerkersovereenkomst kadert in Verordening (EU) 2016/679 (AVG) en het Belgische federaal recht betreffende gegevensbescherming (wet van 30 juli 2018); zij vormt een uitwerking van artikel 28 AVG voor de verwerking in opdracht.
</p>

<h2>2. Onderwerp</h2>

<p>
    WinProx verwerkt persoonsgegevens in opdracht van de klant in het kader van het gebruik van het platform voor
    locatiebeheer, QR-meldingen en opvolging van issues en taken, en — indien geactiveerd — optionele
    ESG-/compliance-metingen en IoT Connect (sensor-events naar workflow).
</p>

<h2>3. Doel van verwerking</h2>

<p>De verwerking omvat:</p>

<ul>
    <li>beheer van meldingen (issues) en taken.</li>
    <li>registratie van unit checks (OK/Niet OK via unit-QR), indien ingeschakeld op categorie en unit.</li>
    <li>beheer van gebruikers en interne teams.</li>
    <li>beheer van uitvoerders (zonder login) en toewijzing aan taken.</li>
    <li>beheer van locaties en units.</li>
    <li>verzenden van e-mailnotificaties in opdracht van de klant.</li>
    <li>logging en beveiliging.</li>
    <li>registratie en opvolging van ESG-/compliance-metingen (indien de module is geactiveerd).</li>
    <li>verwerking van IoT-events (alarms naar meldingen/taken; metingen naar ESG indien geconfigureerd).</li>
</ul>

<h2>4. Type gegevens</h2>

<ul>
    <li>identificatiegegevens (naam, e-mailadres, telefoonnummer indien ingevoerd).</li>
    <li>locatie- en unitgegevens (adressen, locatiedetails).</li>
    <li>meldingen en taakgegevens (inclusief foto’s en beschrijvingen).</li>
    <li>unit-checkgegevens (resultaat, tijdstip, unit, optioneel GPS, uitvoerder).</li>
    <li>gegevens van uitvoerders en QR-melders, voor zover door de klant verzameld.</li>
    <li>toegangs- en sessiegegevens.</li>
    <li>abonnements- en toegangsmetadata.</li>
    <li>ESG-/compliance-gegevens (indicatordefinities, meetwaarden, koppelingen, drempelopvolging en optionele toeschrijving aan uitvoerders).</li>
    <li>IoT-gegevens (gateways, sensorkoppelingen, alarmregels, eventstatussen; geen timeseries-dump).</li>
</ul>

<h2>5. Verplichtingen van WinProx</h2>

<p>WinProx zal:</p>

<ul>
    <li>gegevens enkel verwerken op instructie van de klant.</li>
    <li>passende beveiligingsmaatregelen nemen.</li>
    <li>toegang beperken tot bevoegde personen.</li>
    <li>vertrouwelijkheid garanderen.</li>
</ul>

<h2>6. Beveiliging</h2>

<p>WinProx voorziet onder meer:</p>

<ul>
    <li>tenant-isolatie.</li>
    <li>toegangscontrole.</li>
    <li>logging.</li>
    <li>automatische dagelijkse backups via de hostingprovider (Cloud86), 7 dagen bewaard.</li>
    <li>richtwaarde herstel: RPO ≈ 24 uur (max. dataverlies sinds de laatste nachtelijke backup); RTO best effort, doorgaans binnen 1 werkdag.</li>
</ul>

<h2>7. {{ __('legal.documents.subprocessors') }}</h2>

<p>
    WinProx kan gebruik maken van derden voor hosting, infrastructuur, e-mail en (indien gebruikt) betalingen.
</p>

<p>
    Deze partijen worden zorgvuldig geselecteerd en vallen onder passende contractuele waarborgen. Een actueel overzicht staat op
    <a href="{{ route('legal.subprocessors') }}">{{ __('legal.documents.subprocessors') }}</a>.
</p>

<h2>8. Datalekken</h2>

<p>
    WinProx zal de klant zonder onredelijke vertraging informeren bij een inbreuk op persoonsgegevens.
</p>

<h2>9. Rechten van betrokkenen</h2>

<p>
    WinProx ondersteunt de klant bij het behandelen van verzoeken van betrokkenen, onder meer via
    platformfuncties voor export (Instellingen → Privacy &amp; export van gegevens), gebruikersdeactivatie
    en self-service organisatieverwijdering (Abonnement), zoals beschreven in de
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>.
</p>

<h2>10. Bewaartermijnen</h2>

<p>
    Gegevens worden bewaard volgens het bewaarbeleid zoals beschreven in de
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>, waaronder:
</p>
<ul>
    <li>gebruikersaccounts: actief + 24 maanden.</li>
    <li>meldingen en taken: contractperiode + 36 maanden.</li>
    <li>unit checks: dezelfde bewaartermijn als meldingen en taken.</li>
    <li>logs: 6 maanden.</li>
    <li>foto’s: 24 maanden na afsluiten.</li>
    <li>ESG-metingen: dezelfde bewaartermijn als meldingen en taken.</li>
    <li>IoT-events en gateway-/sensormetadata: contractperiode + 36 maanden (behoudens eerdere tenant-verwijdering).</li>
    <li>operationele infrastructuurbackups (hosting/Cloud86): 7 dagen.</li>
    <li>technische SQL-snapshot na volledige organisatieverwijdering (zonder media): max. 30 dagen.</li>
</ul>

<h2>11. Einde overeenkomst</h2>

<p>
    Bij beëindiging van het gebruik van het platform:
</p>

<ul>
    <li>kan de klant gegevens exporteren via het platform (JSON/ZIP) vóór verwijdering.</li>
    <li>kan de klant (beheerder) self-service een volledige tenant-verwijdering starten (proef: zelf uitvoeren na e-mailbevestiging; betaald: cool-down van 7 dagen en uitvoering door WinProx-administratie).</li>
    <li>kan WinProx bij een verlopen proef zonder abonnement, na waarschuwing, de organisatie automatisch verwijderen.</li>
    <li>worden live gegevens hard verwijderd; een technische SQL-snapshot zonder mediabestanden wordt max. 30 dagen bewaard en daarna vernietigd.</li>
    <li>overige gegevens worden verwijderd of geanonimiseerd conform het bewaarbeleid, behoudens wettelijke bewaarplichten of litigation holds.</li>
</ul>

<h2>12. Aansprakelijkheid</h2>

<p>
    De aansprakelijkheid van WinProx is beperkt zoals bepaald in de
    <a href="{{ route('legal.terms') }}">{{ __('legal.documents.terms') }}</a>.
</p>

<h2>13. Toepasselijk recht</h2>

<p>
    Op deze overeenkomst is het Belgisch recht van toepassing.
</p>
