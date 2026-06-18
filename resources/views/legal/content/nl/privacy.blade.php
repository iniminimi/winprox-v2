<h2>1. Wie zijn wij</h2>
<p>
    WinProx (“Work in Proximity”) is een SaaS-platform voor technisch en operationeel locatiebeheer:
    QR-meldingen, opvolging van issues en taken voor interne operationele teams.
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

<h2>4. AI Vertalingen (optioneel)</h2>
<p>Indien geactiveerd door de beheerder, kan het platform gebruikmaken van AI-vertalingen:</p>
<ul>
    <li>automatische vertaling van meldingsteksten naar andere talen.</li>
    <li>gebruik van een lokale Ollama-instantie (geen externe diensten).</li>
    <li>vertalingen worden opgeslagen en bewaard volgens het retentiebeleid.</li>
    <li>deze functie is optioneel en kan te allen tijde worden uitgeschakeld.</li>
</ul>

<h2>5. Doeleinden van verwerking</h2>
<p>Gegevens worden verwerkt voor:</p>
<ul>
    <li>het functioneren van het platform.</li>
    <li>registratie en opvolging van meldingen en taken.</li>
    <li>toewijzing aan interne teams en uitvoerders.</li>
    <li>QR-meldingen en communicatie tussen gebruikers binnen uw organisatie.</li>
    <li>het verzenden van e-mailnotificaties in opdracht van de klant.</li>
    <li>productverbetering via onboarding-statistieken (geaggregeerd waar mogelijk).</li>
    <li>beveiliging en logging.</li>
    <li>meertalige ondersteuning via AI-vertalingen (indien geactiveerd).</li>
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
    <li>logs: 6 maanden.</li>
    <li>onboarding-events per gebruiker (voor onboarding-statistieken): 6 maanden; geaggregeerde onboardingcijfers zonder persoonsdata blijven langer bewaard.</li>
    <li>media (foto’s): 24 maanden na afsluiten van de betreffende melding of taak.</li>
</ul>

<h2>9. Delen van gegevens</h2>
<p>Persoonsgegevens worden niet verkocht of gedeeld met derden, behalve:</p>
<ul>
    <li>in opdracht van de klant.</li>
    <li>voor hosting en technische infrastructuur.</li>
    <li>voor betalingsverwerking, indien u daarvoor kiest (via een erkende betalingspartner).</li>
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
    Het platform kan in verschillende talen beschikbaar zijn, waaronder Nederlands, Engels, Frans en Duits.
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

<p>Verzoeken kunnen gericht worden aan:</p>
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
