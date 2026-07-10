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
    ESG-/compliance-metingen.
</p>

<h2>3. Doel van verwerking</h2>

<p>De verwerking omvat:</p>

<ul>
    <li>beheer van meldingen (issues) en taken.</li>
    <li>beheer van gebruikers en interne teams.</li>
    <li>beheer van uitvoerders (zonder login) en toewijzing aan taken.</li>
    <li>beheer van locaties en units.</li>
    <li>verzenden van e-mailnotificaties in opdracht van de klant.</li>
    <li>logging en beveiliging.</li>
    <li>registratie en opvolging van ESG-/compliance-metingen (indien de module is geactiveerd).</li>
</ul>

<h2>4. Type gegevens</h2>

<ul>
    <li>identificatiegegevens (naam, e-mailadres, telefoonnummer indien ingevoerd).</li>
    <li>locatie- en unitgegevens (adressen, locatiedetails).</li>
    <li>meldingen en taakgegevens (inclusief foto’s en beschrijvingen).</li>
    <li>gegevens van uitvoerders en QR-melders, voor zover door de klant verzameld.</li>
    <li>toegangs- en sessiegegevens.</li>
    <li>abonnements- en toegangsmetadata.</li>
    <li>ESG-/compliance-gegevens (indicatordefinities, meetwaarden, koppelingen en optionele toeschrijving aan uitvoerders).</li>
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
    WinProx ondersteunt de klant bij het behandelen van verzoeken van betrokkenen.
</p>

<h2>10. Bewaartermijnen</h2>

<p>
    Gegevens worden bewaard volgens het bewaarbeleid zoals beschreven in de
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>, waaronder:
</p>
<ul>
    <li>gebruikersaccounts: actief + 24 maanden.</li>
    <li>meldingen en taken: contractperiode + 36 maanden.</li>
    <li>logs: 6 maanden.</li>
    <li>foto’s: 24 maanden na afsluiten.</li>
    <li>ESG-metingen: dezelfde bewaartermijn als meldingen en taken.</li>
</ul>

<h2>11. Einde overeenkomst</h2>

<p>
    Bij beëindiging van het gebruik van het platform:
</p>

<ul>
    <li>kan de klant gegevens exporteren.</li>
    <li>worden gegevens verwijderd conform het bewaarbeleid.</li>
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
