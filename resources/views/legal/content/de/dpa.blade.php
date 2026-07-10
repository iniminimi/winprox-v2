<h2>1. Parteien</h2>

<p>Diese Auftragsverarbeitungsvereinbarung wird geschlossen zwischen:</p>

<p><strong>Kunde / Administrator (Tenant)</strong><br>
(Verantwortlicher für die Verarbeitung)</p>

<p>und</p>

@include('partials.wp-legal-operator')

<p>(nachstehend „WinProx“, der Auftragsverarbeiter)</p>

<p>
    Diese Vereinbarung richtet sich nach der Verordnung (EU) 2016/679 (DSGVO) und dem belgischen Bundesgesetz zum Datenschutz (Gesetz vom 30. Juli 2018); sie setzt Artikel 28 DSGVO für die Verarbeitung im Auftrag um.
</p>

<h2>2. Gegenstand</h2>

<p>
    WinProx verarbeitet personenbezogene Daten im Auftrag des Kunden im Zusammenhang mit der Nutzung der Plattform für
    Standortmanagement, QR-Meldungen und Nachverfolgung von Issues und Aufgaben sowie — falls aktiviert — optionale
    ESG-/Compliance-Messungen.
</p>

<h2>3. Zweck der Verarbeitung</h2>

<p>Die Verarbeitung umfasst:</p>

<ul>
    <li>Verwaltung von Issues und Aufgaben.</li>
    <li>Verwaltung von Benutzern und internen Teams.</li>
    <li>Verwaltung von Ausführenden (ohne Login) und Zuweisung zu Aufgaben.</li>
    <li>Verwaltung von Standorten und Units.</li>
    <li>Versand von E-Mail-Benachrichtigungen im Auftrag des Kunden.</li>
    <li>Protokollierung und Sicherheit.</li>
    <li>Erfassung und Nachverfolgung von ESG-/Compliance-Messungen (falls das Modul aktiviert ist).</li>
</ul>

<h2>4. Art der Daten</h2>

<ul>
    <li>Identifikationsdaten (Name, E-Mail-Adresse, Telefonnummer, soweit eingegeben).</li>
    <li>Standort- und Unit-Daten (Adressen, Standortdetails).</li>
    <li>Issue- und Aufgabendaten (einschließlich Fotos und Beschreibungen).</li>
    <li>Daten von Ausführenden und QR-Meldern, soweit vom Kunden erhoben.</li>
    <li>Zugangs- und Sitzungsdaten.</li>
    <li>Abonnement- und Zugriffsmetadaten.</li>
    <li>ESG-/Compliance-Daten (Indikatordefinitionen, Messwerte, Verknüpfungen und optionale Zuordnung zu Ausführenden).</li>
</ul>

<h2>5. Pflichten von WinProx</h2>

<p>WinProx wird:</p>

<ul>
    <li>Daten nur auf Anweisung des Kunden verarbeiten.</li>
    <li>angemessene Sicherheitsmaßnahmen treffen.</li>
    <li>den Zugang auf befugte Personen beschränken.</li>
    <li>Vertraulichkeit gewährleisten.</li>
</ul>

<h2>6. Sicherheit</h2>

<p>WinProx stellt unter anderem bereit:</p>

<ul>
    <li>Mandantenisolierung.</li>
    <li>Zugangskontrolle.</li>
    <li>Protokollierung.</li>
</ul>

<h2>7. {{ __('legal.documents.subprocessors') }}</h2>

<p>
    WinProx kann Dritte für Hosting, Infrastruktur, E-Mail und (sofern genutzt) Zahlungen einsetzen.
</p>

<p>
    Diese Parteien werden sorgfältig ausgewählt und unterliegen angemessenen vertraglichen Garantien. Eine aktuelle Übersicht finden Sie unter
    <a href="{{ route('legal.subprocessors') }}">{{ __('legal.documents.subprocessors') }}</a>.
</p>

<h2>8. Datenschutzverletzungen</h2>

<p>
    WinProx informiert den Kunden unverzüglich bei einer Verletzung des Schutzes personenbezogener Daten.
</p>

<h2>9. Rechte betroffener Personen</h2>

<p>
    WinProx unterstützt den Kunden bei der Bearbeitung von Anfragen betroffener Personen.
</p>

<h2>10. Aufbewahrungsfristen</h2>

<p>
    Daten werden gemäß der Aufbewahrungsrichtlinie in der
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a> aufbewahrt, einschließlich:
</p>
<ul>
    <li>Benutzerkonten: aktiv + 24 Monate.</li>
    <li>Issues und Aufgaben: Vertragslaufzeit + 36 Monate.</li>
    <li>Protokolle: 6 Monate.</li>
    <li>Fotos: 24 Monate nach Abschluss.</li>
    <li>ESG-Messungen: gleiche Aufbewahrung wie Issues und Aufgaben.</li>
</ul>

<h2>11. Ende der Vereinbarung</h2>

<p>
    Bei Beendigung der Nutzung der Plattform:
</p>

<ul>
    <li>kann der Kunde Daten exportieren.</li>
    <li>werden Daten gemäß der Aufbewahrungsrichtlinie gelöscht.</li>
</ul>

<h2>12. Haftung</h2>

<p>
    Die Haftung von WinProx ist begrenzt wie in den
    <a href="{{ route('legal.terms') }}">{{ __('legal.documents.terms') }}</a> festgelegt.
</p>

<h2>13. Anwendbares Recht</h2>

<p>
    Auf diese Vereinbarung findet belgisches Recht Anwendung.
</p>
