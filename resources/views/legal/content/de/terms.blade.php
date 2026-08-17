<h2>1. Allgemeines</h2>
<p>
    Diese Nutzungsbedingungen regeln die Nutzung der WinProx-Plattform.
</p>
<p>
    Mit der Nutzung von WinProx stimmt der Benutzer diesen Bedingungen zu.
</p>
<p>
    WinProx („Work in Proximity“) ist eine SaaS-Plattform für technisches und operatives Standortmanagement:
    QR-Meldungen, Nachverfolgung von Issues und Aufgaben für interne operative Teams sowie optionale
    ESG-/Compliance-Erfassung und IoT Connect (Sensor-Events in den Workflow).
</p>

<h2>2. Identität des Dienstanbieters</h2>
<p>WinProx wird betrieben von:</p>

@include('partials.wp-legal-operator')

<h2>3. Beschreibung des Dienstes</h2>
<p>
    WinProx bietet eine digitale Plattform, mit der Kunden / Administratoren:
</p>
<ul>
    <li>Issues registrieren können, auch über QR-Portale.</li>
    <li>Aufgaben verwalten und nachverfolgen können.</li>
    <li>Arbeit internen Teams und Ausführenden zuweisen können.</li>
    <li>optional ESG-/Compliance-Messungen erfassen und nachverfolgen können (falls das Modul aktiviert ist).</li>
    <li>optional IoT Connect nutzen: Gateways/Sensoren verknüpfen, damit Alarme und (soweit zutreffend) Messungen Workflow in WinProx starten.</li>
</ul>
<p>
    WinProx ist ausschließlich eine technische Plattform und führt selbst keine Arbeiten vor Ort aus.
</p>

<h2>4. Keine Ausführung von Arbeiten</h2>
<p>WinProx:</p>
<ul>
    <li>führt keine technischen oder operativen Arbeiten vor Ort aus.</li>
    <li>tritt nicht als Auftragnehmer, Vermittler oder Vertragspartei für Arbeiten vor Ort auf.</li>
    <li>garantiert kein Ergebnis oder keine Qualität von Arbeiten, die Ihre Organisation ausführt.</li>
</ul>
<p>
    Alle operativen Entscheidungen und die Ausführung bleiben in der Verantwortung des Kunden / Administrators und seiner internen Teams.
</p>

<h2>5. Verantwortung des Kunden</h2>
<p>Der Kunde / Administrator ist verantwortlich für:</p>
<ul>
    <li>die Richtigkeit eingegebener Daten.</li>
    <li>die Nutzung der Plattform innerhalb der Organisation.</li>
    <li>Zuweisung und Nachverfolgung von Aufgaben an interne Teams und Ausführende.</li>
    <li>die Einhaltung geltenden Rechts.</li>
</ul>

<p>
    Der Kunde bleibt stets Verantwortlicher für personenbezogene Daten, die im eigenen Gebrauch der Plattform verarbeitet werden.
</p>

<h2>6. Nutzung der Plattform</h2>
<p>Es ist nicht gestattet:</p>
<ul>
    <li>die Plattform für illegale Aktivitäten zu nutzen.</li>
    <li>falsche oder irreführende Informationen einzugeben.</li>
    <li>Kommunikations- oder Benachrichtigungsfunktionen zu missbrauchen.</li>
</ul>

<p>
    WinProx behält sich das Recht vor, Konten bei Missbrauch einzuschränken oder zu sperren.
</p>

<h2>7. Verfügbarkeit des Dienstes</h2>
<p>
    WinProx strebt einen guten Betrieb der Plattform an, garantiert jedoch keine unterbrechungsfreie Verfügbarkeit.
</p>
<p>
    WinProx kann Wartung, Updates oder technische Änderungen durchführen.
</p>
<p>
    WinProx haftet nicht für vorübergehende Unterbrechungen.
</p>
<p>
    WinProx nutzt automatische tägliche Backups über den Hosting-Anbieter (Cloud86), mit 7 Tagen Aufbewahrung.
    Richtwerte: RPO ≈ 24 Stunden (maximaler Datenverlust seit dem letzten nächtlichen Backup) und RTO best effort, in der Regel innerhalb eines Werktags.
    Dies ist keine Verfügbarkeitsgarantie mit Vertragsstrafe. Ein technischer SQL-Snapshot nach vollständiger Organisationslöschung (ohne Medien, max. 30 Tage) ist von diesen betrieblichen Backups zu unterscheiden.
</p>

<h2>8. Abonnement, Testphase und Zahlung</h2>
<p>
    WinProx kann eine begrenzte Testphase anbieten. Die Dauer wird bei der Registrierung oder auf der Plattform mitgeteilt.
</p>
<p>
    Nach der Testphase ist für die fortgesetzte Nutzung ein passendes Abonnement erforderlich, wie auf der Plattform beschrieben (u. a. basierend auf der Anzahl der Units und optionaler Module).
</p>
<p>
    Das Abonnement betrifft den Zugang zu und die Nutzung der Plattform für Ihre Organisation (Tenant). Zahlung, Abrechnung und Verlängerung erfolgen gemäß den auf der Plattform oder in Angeboten/Rechnungen genannten Modalitäten.
</p>
<p>
    Bei ausbleibender rechtzeitiger Zahlung oder abgelaufenem Abonnement kann WinProx den Zugang zur Plattform einschränken oder aussetzen, soweit technisch vorgesehen und unter Berücksichtigung angemessener Fristen.
</p>
<p>
    Nach Ende einer Testphase ohne aktives Abonnement kann der Zugang auf Abonnement- und Rechnungsseiten beschränkt bleiben.
    Ohne rechtzeitiges Abonnement kann WinProx die Organisation nach vorheriger E-Mail-Warnung automatisch löschen
    (Standard: Warnung ca. 7 Tage und Löschung ca. 14 Tage nach Testende). Die Aktivierung eines Abonnements
    stoppt eine geplante automatische Löschung.
</p>
<p>
    WinProx kann Tarife und Formeln anpassen. Relevante Änderungen werden über die Plattform und/oder per E-Mail mit angemessener Vorlaufzeit mitgeteilt.
</p>
<p>
    Für die Verarbeitung personenbezogener Daten in diesem Zusammenhang verweisen wir auf die
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a> und, zwischen Unternehmen und WinProx, die
    <a href="{{ route('legal.dpa') }}">{{ __('legal.documents.dpa') }}</a>, soweit anwendbar.
</p>

<h2>9. Haftung</h2>
<p>WinProx haftet nicht für:</p>
<ul>
    <li>Schäden aus Arbeiten, die vom Kunden oder seinen internen Teams ausgeführt werden.</li>
    <li>Fehler in operativen Entscheidungen oder der Ausführung vor Ort.</li>
    <li>indirekte Schäden, einschließlich entgangenem Gewinn, Folgeschäden oder Reputationsschäden.</li>
</ul>

<p>
    Soweit gesetzlich zulässig, ist die Haftung von WinProx in allen Fällen auf den Betrag begrenzt, den der Kunde für die Nutzung der Plattform in den zwölf Monaten vor dem schadensauslösenden Ereignis gezahlt hat.
</p>

<h2>10. Daten und Datenschutz</h2>
<p>
    Die Nutzung personenbezogener Daten wird in der
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a> geregelt.
</p>
<p>
    WinProx verarbeitet Daten im Auftrag des Kunden / Administrators.
</p>

<h2>11. Geistiges Eigentum</h2>
<p>
    Alle Rechte an der Plattform verbleiben Eigentum von WinProx.
</p>
<p>Es ist nicht gestattet:</p>
<ul>
    <li>die Software zu kopieren.</li>
    <li>Teile der Plattform ohne vorherige schriftliche Zustimmung wiederzuverwenden.</li>
</ul>

<h2>12. Beendigung</h2>
<p>
    WinProx kann die Nutzung der Plattform beenden oder aussetzen:
</p>
<ul>
    <li>bei Verstoß gegen diese Bedingungen.</li>
    <li>bei Missbrauch der Plattform.</li>
</ul>

<p>
    Einzelne Benutzerkonten können vom Administrator der Organisation gemäß den in der Plattform verfügbaren Rechten
    deaktiviert oder pausiert werden.
</p>
<p>
    Ein Administrator kann die vollständige Löschung der Organisationsdaten über
    <em>Abonnement → Organisationsdaten löschen</em> anfordern, nach Exportangebot, Passwortbestätigung und
    E-Mail-Bestätigung an alle Administratoren:
</p>
<ul>
    <li><strong>Testphase:</strong> nach Bestätigung kann der Administrator endgültig löschen.</li>
    <li><strong>Bezahltes Abonnement:</strong> Wartezeit von 7 Tagen; Ausführung durch WinProx-Administration; Stornierung bis dahin über Abonnement möglich.</li>
</ul>
<p>
    Bei endgültiger Löschung wird ein technischer SQL-Snapshot ohne Mediendateien max. 30 Tage aufbewahrt und danach vernichtet.
    Details zu personenbezogenen Daten stehen in der
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>.
</p>

<h2>13. Internationale Verfügbarkeit</h2>
<p>
    WinProx kann international angeboten und in mehreren Sprachen verfügbar sein.
</p>
<p>
    Unabhängig von der Sprachversion unterliegt die Nutzung der Plattform diesen Bedingungen.
</p>

<h2>14. Anwendbares Recht und Gerichtsstand</h2>
<p>
    Auf diese Bedingungen findet belgisches Recht Anwendung, unbeschadet zwingenden EU-Rechts.
</p>
<p>
    Streitigkeiten fallen in die Zuständigkeit der Gerichte des Gerichtsbezirks des Betreibers, sofern zwingendes Recht nichts anderes vorsieht.
</p>

<h2>15. Änderungen</h2>
<p>
    WinProx kann diese Bedingungen anpassen.
</p>
<p>
    Die aktuellste Version ist stets über die Plattform verfügbar.
</p>
