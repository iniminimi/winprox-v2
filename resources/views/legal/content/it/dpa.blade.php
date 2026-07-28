<h2>1. Parti</h2>

<p>Il presente accordo sul trattamento dei dati è stipulato tra:</p>

<p><strong>Cliente / amministratore (tenant)</strong><br>
(il titolare del trattamento)</p>

<p>e</p>

@include('partials.wp-legal-operator')

<p>(di seguito «WinProx», il responsabile del trattamento)</p>

<p>
    Il presente accordo è regolato dal Regolamento (UE) 2016/679 (GDPR) e dalla legge federale belga sulla protezione dei dati (Legge del 30 luglio 2018); implementa l'articolo 28 del GDPR per il trattamento seguendo le istruzioni del cliente.
</p>

<h2>2. Oggetto</h2>

<p>
    WinProx tratta dati personali seguendo le istruzioni del cliente in relazione all'utilizzo della piattaforma per
    la gestione degli impianti, la segnalazione di problematiche tramite QR e il follow-up di problematiche e attività, e — se
    attivato — misurazioni ESG/conformità opzionali.
</p>

<h2>3. Finalità del trattamento</h2>

<p>Il trattamento comprende:</p>

<ul>
    <li>gestione di problematiche e attività.</li>
    <li>gestione di utenti e team interni.</li>
    <li>gestione di lavoratori (senza accesso) e assegnazione ad attività.</li>
    <li>gestione di sedi e unità.</li>
    <li>invio di notifiche e-mail seguendo le istruzioni del cliente.</li>
    <li>registrazione delle attività e sicurezza.</li>
    <li>registrazione e follow-up delle misurazioni ESG/conformità (se il modulo è attivato).</li>
</ul>

<h2>4. Tipi di dati</h2>

<ul>
    <li>dati identificativi (nome, indirizzo e-mail, numero di telefono ove inseriti).</li>
    <li>dati di sede e unità (indirizzi, dettagli di localizzazione).</li>
    <li>dati di problematiche e attività (inclusi foto e descrizioni).</li>
    <li>dati di lavoratori e segnalanti QR, nella misura in cui raccolti dal cliente.</li>
    <li>dati di accesso e sessione.</li>
    <li>metadati di abbonamento e accesso.</li>
    <li>dati ESG/conformità (definizioni degli indicatori, valori di misurazione, collegamenti e attribuzione opzionale ai lavoratori).</li>
</ul>

<h2>5. Obblighi di WinProx</h2>

<p>WinProx deve:</p>

<ul>
    <li>trattare i dati esclusivamente seguendo le istruzioni del cliente.</li>
    <li>implementare misure di sicurezza adeguate.</li>
    <li>limitare l'accesso alle persone autorizzate.</li>
    <li>garantire la riservatezza.</li>
</ul>

<h2>6. Sicurezza</h2>

<p>WinProx mette a disposizione, tra l'altro:</p>

<ul>
    <li>isolamento per tenant.</li>
    <li>controllo degli accessi.</li>
    <li>registrazione delle attività.</li>
</ul>

<h2>7. {{ __('legal.documents.subprocessors') }}</h2>

<p>
    WinProx può avvalersi di terzi per hosting, infrastruttura, e-mail e (ove utilizzato) pagamenti.
</p>

<p>
    Queste parti sono selezionate con cura e soggette a garanzie contrattuali adeguate. Una panoramica aggiornata è disponibile su
    <a href="{{ route('legal.subprocessors') }}">{{ __('legal.documents.subprocessors') }}</a>.
</p>

<h2>8. Violazioni dei dati</h2>

<p>
    WinProx informerà il cliente senza ingiustificato ritardo in caso di violazione dei dati personali.
</p>

<h2>9. Diritti degli interessati</h2>

<p>
    WinProx supporta il cliente nella gestione delle richieste degli interessati, anche tramite
    funzioni della piattaforma per l'esportazione (Impostazioni → Privacy ed esportazione dati), la disattivazione utenti
    e la cancellazione self-service dell'organizzazione (Abbonamento), come descritto nell'
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>.
</p>

<h2>10. Periodi di conservazione</h2>

<p>
    I dati vengono conservati conformemente alla politica di conservazione descritta nell'
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>, inclusi:
</p>
<ul>
    <li>account utente: attivi + 24 mesi.</li>
    <li>problematiche e attività: durata del contratto + 36 mesi.</li>
    <li>registri: 6 mesi.</li>
    <li>foto: 24 mesi dopo la chiusura.</li>
    <li>misurazioni ESG: stesso periodo di conservazione di problematiche e attività.</li>
    <li>snapshot SQL tecnico dopo la cancellazione completa dell'organizzazione (senza media): max. 30 giorni.</li>
</ul>

<h2>11. Fine dell'accordo</h2>

<p>
    Al termine dell'utilizzo della piattaforma:
</p>

<ul>
    <li>il cliente può esportare i dati tramite la piattaforma (JSON/ZIP) prima della cancellazione.</li>
    <li>il cliente (amministratore) può avviare una cancellazione completa del tenant in self-service (prova: esecuzione dopo conferma e-mail; a pagamento: attesa di 7 giorni ed esecuzione da parte dell'amministrazione WinProx).</li>
    <li>WinProx può, per una prova scaduta senza abbonamento e dopo avviso, eliminare l'organizzazione automaticamente.</li>
    <li>i dati live vengono eliminati in modo definitivo; uno snapshot SQL tecnico senza file media viene conservato max. 30 giorni e poi distrutto.</li>
    <li>gli altri dati vengono eliminati o anonimizzati conformemente alla politica di conservazione, fatte salve obblighi legali di conservazione o litigation holds.</li>
</ul>

<h2>12. Responsabilità</h2>

<p>
    La responsabilità di WinProx è limitata come indicato nelle
    <a href="{{ route('legal.terms') }}">{{ __('legal.documents.terms') }}</a>.
</p>

<h2>13. Legge applicabile</h2>

<p>
    Il presente accordo è regolato dal diritto belga.
</p>
