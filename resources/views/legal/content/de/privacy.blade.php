<h2>1. Wer wir sind</h2>
<p>
    WinProx („Work in Proximity“) ist eine SaaS-Plattform für technisches und operatives Standortmanagement:
    QR-Meldungen, Nachverfolgung von Issues und Aufgaben für interne operative Teams sowie optionale
    ESG-/Compliance-Erfassung und IoT Connect (Sensor-Events in den Workflow).
</p>
<p>
    Die Plattform wird betrieben von:
</p>

@include('partials.wp-legal-operator')

<h2>2. Rollen unter der DSGVO (EU und Belgien)</h2>
<p>Innerhalb der Plattform gelten folgende Rollen:</p>
<ul>
    <li>Der Kunde / Administrator ist Verantwortlicher für die Verarbeitung.</li>
    <li>WinProx ist Auftragsverarbeiter.</li>
</ul>
<p>Das bedeutet:</p>
<ul>
    <li>Der Kunde bestimmt, welche personenbezogenen Daten verarbeitet werden und zu welchem Zweck.</li>
    <li>WinProx verarbeitet personenbezogene Daten ausschließlich im Auftrag des Kunden.</li>
</ul>

<h2>3. Welche Daten wir verarbeiten</h2>

<p><strong>Benutzer</strong></p>
<ul>
    <li>Name.</li>
    <li>E-Mail-Adresse.</li>
    <li>Rolle innerhalb der Organisation.</li>
    <li>Spracheinstellung, soweit zutreffend.</li>
</ul>

<p><strong>Abonnement und Abrechnung</strong></p>
<ul>
    <li>gewähltes Abonnement (soweit zutreffend).</li>
    <li>Enddatum der Testphase und des bezahlten Abonnements.</li>
    <li>Abrechnungs- und Zahlungsdaten, die Sie oder Ihre Organisation eingeben oder die über einen Zahlungsanbieter verarbeitet werden.</li>
</ul>

<p><strong>Standorte und Units</strong></p>
<ul>
    <li>Standorte (Sites) und Units innerhalb Ihrer Organisation.</li>
    <li>Adressen und Standortdaten, die Sie eingeben.</li>
</ul>

<p><strong>Meldungen und Aufgaben</strong></p>
<ul>
    <li>Issues und Aufgaben.</li>
    <li>Beschreibungen, Status und Nachverfolgung.</li>
    <li>Kommunikation und Verlauf innerhalb der Plattform.</li>
    <li>Fotos und Anhänge zu Meldungen oder Aufgaben.</li>
    <li>Unit-Checks (OK/Nicht OK via Unit-QR durch Ausführer), wenn an Kategorie und Unit aktiviert.</li>
</ul>

<p><strong>Ausführende (ohne Login)</strong></p>
<ul>
    <li>Name oder Anzeigename.</li>
    <li>Kontaktdaten (z. B. E-Mail-Adresse), soweit vom Kunden eingegeben.</li>
    <li>Zuweisung zu Aufgaben innerhalb interner Teams.</li>
</ul>
<p>
    Diese Daten werden vom Kunden / Administrator verwaltet. WinProx hat keine inhaltliche Kontrolle über die Eingaben des Kunden.
</p>

<p><strong>QR-Meldungen</strong></p>
<ul>
    <li>Daten, die eine meldende Person freiwillig über ein öffentliches QR-Portal eingibt (z. B. Name, E-Mail-Adresse oder Beschreibung).</li>
    <li>technische Metadaten, die für Sicherheit und Missbrauchsprävention erforderlich sind.</li>
</ul>
<p><strong>Unit-Checks</strong></p>
<p>
    Aktiviert der Kunde Unit-Checks an Kategorie und Unit, können Ausführer über den Unit-QR (nach Clock-Point-Anmeldung) ein OK- oder Nicht-OK-Ergebnis erfassen, optional mit Checkliste und GPS. Dies ist keine Meldung: OK erstellt kein Issue. WinProx speichert Ergebnis, Zeitstempel, Unit, optional GPS-Koordinaten und den Ausführer. Aufbewahrung wie bei Meldungen und Aufgaben, sofern Ihre Organisation nichts anderes festlegt.
</p>


<p><strong>ESG & Compliance (optionales Modul)</strong></p>
<p>
    Wenn der Kunde das optionale ESG-Modul aktiviert, können Messwerte und Compliance-Daten erfasst werden,
    z. B. bei wiederkehrenden Inspektionen, beim Abschließen von Aufgaben im QR-Portal, über die API oder — falls
    IoT Connect aktiviert ist — über Sensor-Events.
</p>
<ul>
    <li>Indikatordefinitionen (Name, Typ, Einheit, Schwellenwerte, Optionen), einschließlich etwaiger Übersetzungen von Indikatortexten.</li>
    <li>Messwerte (z. B. Zahl, Ja/Nein, Auswahl oder Text) mit Zeitstempel.</li>
    <li>Verknüpfung mit Meldung, Aufgabe, Standort, Unit und optional dem ausführenden Worker; auf dem Sensorpfad kann eine Aufgabe fehlen.</li>
    <li>Korrekturen als neue Messzeilen (append-only); frühere Werte bleiben erhalten.</li>
    <li>Schwellenalarme und daraus resultierende Folgeaufgaben, wenn eine Messung außerhalb der konfigurierten Grenzen liegt.</li>
    <li>API-Erstellung von Messungen und optionale Webhooks (z. B. bei einer neuen Messzeile), sofern der Kunde sie anbindet.</li>
</ul>
<p>
    Das Modul ist optional und nur für Administratoren sichtbar, wenn es aktiviert ist. Der Kunde ist verantwortlich
    für Inhalt und Nutzung der ESG-Daten innerhalb seiner Organisation.
</p>

<p><strong>IoT Connect (optionales Modul)</strong></p>
<p>
    Wenn der Kunde IoT Connect aktiviert, können Gateways Events an WinProx senden. WinProx ist keine IoT-Cloud und
    keine Zeitreihen-Plattform: Der Kunde (oder sein Hardwarepartner) verwaltet Gateways und Sensoren; WinProx wandelt
    eingehende Events in Workflow innerhalb des Tenants um.
</p>
<ul>
    <li>Gateway-Konfiguration und Authentifizierungstoken (sicher gespeichert; ein neues Token wird typischerweise einmalig angezeigt).</li>
    <li>Sensormappings (externe ID → Standort/Unit, optional ein ESG-Indikator).</li>
    <li>Alarmregeln (Schwellen, Operator, zugewiesenes Team, Priorität, Text).</li>
    <li>Event-Datensätze (verarbeitet / ignoriert / dedupliziert / fehlgeschlagen) — keine kontinuierliche Zeitreihen-Speicherung.</li>
    <li>bei Alarm: eine freigegebene Meldung und Aufgabe in der Organisation (mit Deduplizierung, solange eine offene Aufgabe für dieselbe Regel existiert).</li>
    <li>bei Messung (Corporate, mit ESG-Modul): eine ESG-Messzeile auf Basis des Sensor-Events.</li>
</ul>
<p>
    Personenbezogene Daten in IoT-Flows beschränken sich auf das, was der Kunde konfiguriert (z. B. Zuweisung an Teams/Ausführende
    über Meldungen und Aufgaben). Der Kunde bleibt verantwortlich für Sensorquellen und Event-Inhalte.
</p>

<h2>4. KI-Übersetzungen</h2>
<p>Die Plattform verwendet KI-Übersetzungen für die mehrsprachige Anzeige:</p>
<ul>
    <li>Übersetzung von Texten, die mehrsprachig in der Plattform oder im QR-Portal angezeigt werden (u. a. Meldungen, Aufgaben, Units, Mitteilungen, Dokumentbeschreibungen, Standorte, Kategorien, Teamnamen und ESG-Indikatortexte); Texte werden nach Freigabe zur Übersetzung vorgemerkt.</li>
    <li>Verwendung einer lokalen Ollama-Instanz (keine externen KI-Dienste / Cloud).</li>
    <li>WinProx führt diese Übersetzungen periodisch aus (in der Regel täglich), ohne garantierte Durchlaufzeit.</li>
    <li>Übersetzungen werden gemäß Aufbewahrungsrichtlinie gespeichert und aufbewahrt; Organisationsadministratoren können Übersetzungen in der Plattform manuell korrigieren oder ergänzen.</li>
    <li>es gibt keinen An/Aus-Schalter pro Organisation; WinProx kann die Übersetzungspipeline auf Plattformebene anhalten.</li>
</ul>

<h2>5. Zwecke der Verarbeitung</h2>
<p>Daten werden verarbeitet für:</p>
<ul>
    <li>den Betrieb der Plattform.</li>
    <li>Registrierung und Nachverfolgung von Issues und Aufgaben.</li>
    <li>Zuweisung an interne Teams und Ausführende.</li>
    <li>QR-Meldungen und Kommunikation zwischen Benutzern innerhalb Ihrer Organisation.</li>
    <li>Versand von E-Mail-Benachrichtigungen im Auftrag des Kunden.</li>
    <li>Produktverbesserung durch Onboarding-Statistiken (soweit möglich aggregiert).</li>
    <li>Sicherheit und Protokollierung.</li>
    <li>mehrsprachige Unterstützung durch KI-Übersetzungen (periodisch durch WinProx ausgeführt, ohne garantierte Durchlaufzeit).</li>
    <li>Erfassung und Nachverfolgung von ESG-/Compliance-Messungen (falls das Modul aktiviert ist).</li>
    <li>Verarbeitung von IoT-Events zu Meldungen, Aufgaben und/oder ESG-Messungen (falls IoT Connect aktiviert ist).</li>
</ul>

<h2>6. QR-Meldungen und Teamzugang</h2>
<p>
    Über QR-Codes können Meldungen ohne Konto eingereicht werden. Der Kunde / Administrator bestimmt, welche Standorte
    und Units verfügbar sind und welche Daten abgefragt werden.
</p>
<p>
    Angemeldete Benutzer und interne Teams erhalten Zugang gemäß den vom Kunden gesetzten Berechtigungen. WinProx verarbeitet
    personenbezogene Daten in diesem Zusammenhang ausschließlich als technischer Auftragsverarbeiter auf Anweisung des Kunden.
</p>

<h2>7. Support und Zugriff</h2>
<p>
    Für technischen Support kann WinProx in Ausnahmefällen über einen Supportmodus für Superuser- oder Supportmitarbeiter
    Zugriff auf Daten erhalten:
</p>
<ul>
    <li>ausschließlich für technischen Support und Fehlerbehebung.</li>
    <li>standardmäßig schreibgeschützt (nur lesen).</li>
    <li>ohne aktive Änderungen an Kundendaten, es sei denn, Sie bitten ausdrücklich darum.</li>
</ul>

<h2>8. Aufbewahrungsfristen</h2>
<p>WinProx wendet folgende Aufbewahrungsfristen an:</p>
<ul>
    <li>Benutzerkonten: aktiv + 24 Monate.</li>
    <li>Issues und Aufgaben: Vertragslaufzeit + 36 Monate.</li>
    <li>Unit-Checks: gleiche Aufbewahrung wie Meldungen und Aufgaben (Vertragslaufzeit + 36 Monate).</li>
    <li>Protokolle: 6 Monate.</li>
    <li>Onboarding-Ereignisse pro Benutzer (für Onboarding-Statistiken): 6 Monate; aggregierte Onboarding-Kennzahlen ohne Personendaten können länger aufbewahrt werden.</li>
    <li>Medien (Fotos): 24 Monate nach Abschluss der betreffenden Meldung oder Aufgabe.</li>
    <li>ESG-Messungen: gleiche Aufbewahrung wie Meldungen und Aufgaben (Vertragslaufzeit + 36 Monate).</li>
    <li>IoT-Events, Gateway- und Sensor-Metadaten: Vertragslaufzeit + 36 Monate (oder kürzer, wenn die zugrunde liegende Meldung/Aufgabe bei Organisationslöschung früher entfernt wird).</li>
    <li>betriebliche Infrastruktur-Backups (Hosting/Cloud86): 7 Tage.</li>
    <li>technischer SQL-Snapshot nach vollständiger Organisationslöschung (ohne Mediendateien): maximal 30 Tage, danach Vernichtung.</li>
</ul>
<p>
    Nach einer vollständigen Organisationslöschung (siehe unten) werden die Live-Daten des Tenants endgültig gelöscht;
    Mediendateien (Fotos, Dokumente) sind nicht Bestandteil des Wiederherstellungs-Snapshots.
</p>

<h2>9. Weitergabe von Daten</h2>
<p>Personenbezogene Daten werden nicht verkauft oder an Dritte weitergegeben, außer:</p>
<ul>
    <li>im Auftrag des Kunden.</li>
    <li>für Hosting und technische Infrastruktur.</li>
    <li>für Zahlungsabwicklung, sofern Sie diese nutzen (über einen anerkannten Zahlungspartner).</li>
    <li>wenn gesetzlich vorgeschrieben.</li>
</ul>
<p>
    Eine Übersicht der Subunternehmer-Kategorien finden Sie auf der Seite
    <a href="{{ route('legal.subprocessors') }}">{{ __('legal.documents.subprocessors') }}</a>.
</p>

<h2>10. Internationale Verfügbarkeit</h2>
<p>
    WinProx ist eine internationale Plattform und kann in mehreren Ländern genutzt werden.
</p>
<p>
    Die Plattform kann in mehreren Sprachen verfügbar sein, darunter Niederländisch, Englisch, Französisch, Deutsch, Spanisch und Italienisch.
</p>
<p>
    Unabhängig von der Sprachversion gilt diese Datenschutzerklärung für die Verarbeitung personenbezogener Daten.
</p>

<h2>11. Rechte der betroffenen Personen</h2>
<p>Betroffene Personen haben das Recht:</p>
<ul>
    <li>ihre Daten einzusehen.</li>
    <li>ihre Daten zu berichtigen.</li>
    <li>die Löschung ihrer Daten zu verlangen.</li>
    <li>der Verarbeitung zu widersprechen.</li>
</ul>

<p><strong>Wie die Plattform dies unterstützt</strong></p>
<ul>
    <li>
        <strong>Auskunft / Export:</strong> ein Administrator kann unter
        <em>Einstellungen → Datenschutz &amp; Datenexport</em> einen maschinenlesbaren Export (JSON in einer ZIP)
        des eigenen Kontos und relevanter Organisationsdaten herunterladen. Downloads werden protokolliert.
    </li>
    <li>
        <strong>Berichtigung:</strong> berechtigte Benutzer können ihr Profil (Name, E-Mail, Sprache) anpassen;
        Administratoren können Organisationsdaten anpassen.
    </li>
    <li>
        <strong>Benutzer deaktivieren:</strong> ein Administrator kann Kollegenkonten deaktivieren oder pausieren
        (Login gesperrt; Sitzungen widerrufen). Das ist keine vollständige Organisationslöschung.
    </li>
    <li>
        <strong>Organisationsdaten löschen (Self-Service):</strong> nur Administratoren, über
        <em>Abonnement → Organisationsdaten löschen</em>. Zuerst wird ein Export angeboten; danach Bestätigung
        mit Passwort und E-Mail an alle Administratoren.
        <ul>
            <li><strong>Testphase:</strong> nach E-Mail-Bestätigung kann der Administrator endgültig löschen
                (technischer SQL-Snapshot ohne Medien, max. 30 Tage aufbewahrt).</li>
            <li><strong>Bezahltes Abonnement / Grace:</strong> nach Bestätigung gilt eine Wartezeit von 7 Tagen
                (Banner in der App, Erinnerungsmail etwa 2 Tage vorher); die WinProx-Administration (Superuser)
                führt die Löschung aus. Stornierung bis dahin über Abonnement möglich.</li>
        </ul>
    </li>
    <li>
        <strong>Abgelaufene Testphase ohne Abonnement:</strong> nach Ende der Testphase kann der Login auf
        Abonnement-/Rechnungsseiten beschränkt bleiben. Ohne Abonnement sendet WinProx Warnmails und kann die
        Organisation automatisch löschen (Standard: Warnung ca. Tag 7, Löschung ca. Tag 14 nach Testende).
        Die Aktivierung eines Abonnements storniert eine geplante automatische Löschung.
    </li>
</ul>

<p>Sonstige oder außergewöhnliche Anfragen (z. B. Litigation Hold) können gerichtet werden an:</p>
@include('partials.wp-legal-operator')

<p>
    Werden die Daten im Auftrag eines Kunden verarbeitet, kann es erforderlich sein, die Anfrage über diesen Kunden zu bearbeiten.
</p>

<h2>12. Sicherheit</h2>
<p>WinProx trifft angemessene technische und organisatorische Maßnahmen, darunter:</p>
<ul>
    <li>Mandantenisolierung.</li>
    <li>Zugangskontrolle.</li>
    <li>Protokollierung.</li>
    <li>automatische tägliche Backups über den Hosting-Anbieter (Cloud86), 7 Tage Aufbewahrung.</li>
    <li>Wiederherstellungsziele: RPO ≈ 24 Stunden (max. Datenverlust seit dem letzten nächtlichen Backup); RTO best effort, in der Regel innerhalb eines Werktags.</li>
</ul>
<p>
    Siehe auch die <a href="{{ route('legal.cookies') }}">{{ __('legal.documents.cookies') }}</a> für Informationen zu
    unbedingt erforderlichen Cookies.
</p>

<h2>13. Internationale Übermittlungen</h2>
<p>
    Daten werden grundsätzlich innerhalb der Europäischen Union verarbeitet.
</p>
<p>
    Werden externe Dienstleister eingesetzt, werden angemessene Garantien vorgesehen.
</p>

<h2>14. Aufsichtsbehörde</h2>
<p>
    Sie haben das Recht, Beschwerde bei einer Aufsichtsbehörde einzureichen. In Belgien ist dies die Datenschutzbehörde
    (<a href="https://www.gegevensbeschermingsautoriteit.be" rel="noopener noreferrer" target="_blank">www.gegevensbeschermingsautoriteit.be</a>).
</p>

<h2>15. Änderungen</h2>
<p>
    Diese Datenschutzerklärung kann angepasst werden.
</p>
<p>
    Die aktuellste Version ist stets über die Plattform verfügbar.
</p>
