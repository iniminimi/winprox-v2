<h2>1. Chi siamo</h2>
<p>
    WinProx («Work in Proximity») è una piattaforma SaaS per la gestione tecnica e operativa degli impianti:
    segnalazione di problematiche tramite QR e follow-up delle attività per i team operativi interni, e registrazione
    ESG/conformità opzionale e IoT Connect (eventi sensore nel flusso di lavoro).
</p>
<p>
    La piattaforma è gestita da:
</p>

@include('partials.wp-legal-operator')

<h2>2. Ruoli ai sensi del GDPR (UE e Belgio)</h2>
<p>Nella piattaforma si applicano i seguenti ruoli:</p>
<ul>
    <li>Il cliente / amministratore è il titolare del trattamento.</li>
    <li>WinProx è il responsabile del trattamento.</li>
</ul>
<p>Ciò significa che:</p>
<ul>
    <li>Il cliente decide quali dati personali vengono trattati e per quale finalità.</li>
    <li>WinProx tratta i dati personali esclusivamente seguendo le istruzioni del cliente.</li>
</ul>

<h2>3. Dati che trattiamo</h2>

<p><strong>Utenti</strong></p>
<ul>
    <li>nome</li>
    <li>indirizzo e-mail</li>
    <li>ruolo all'interno dell'organizzazione</li>
    <li>preferenza linguistica, ove applicabile</li>
</ul>

<p><strong>Abbonamento e fatturazione</strong></p>
<ul>
    <li>piano di abbonamento selezionato (ove applicabile)</li>
    <li>data di fine del periodo di prova e dell'abbonamento a pagamento</li>
    <li>dati di fatturazione e pagamento inseriti da lei o dalla sua organizzazione, o trattati tramite un fornitore di pagamenti</li>
</ul>

<p><strong>Sedi e unità</strong></p>
<ul>
    <li>sedi (impianti) e unità all'interno della sua organizzazione</li>
    <li>indirizzi e dati di localizzazione da lei inseriti</li>
</ul>

<p><strong>Problematiche e attività</strong></p>
<ul>
    <li>problematiche e attività</li>
    <li>descrizioni, stati e follow-up</li>
    <li>comunicazioni e cronologia all'interno della piattaforma</li>
    <li>foto e allegati aggiunti a problematiche o attività</li>
    <li>controlli unità (OK/Non OK via QR unit da esecutori), se attivati su categoria e unit.</li>
</ul>

<p><strong>Lavoratori (senza accesso)</strong></p>
<ul>
    <li>nome o nome visualizzato</li>
    <li>dati di contatto (come l'indirizzo e-mail), ove inseriti dal cliente</li>
    <li>assegnazione ad attività all'interno dei team interni</li>
</ul>
<p>
    Questi dati sono gestiti dal cliente / amministratore. WinProx non ha alcun controllo sostanziale su ciò che il cliente inserisce.
</p>

<p><strong>Segnalazioni QR</strong></p>
<ul>
    <li>dati inviati volontariamente tramite un portale QR pubblico (come nome, indirizzo e-mail o descrizione)</li>
    <li>metadati tecnici necessari per la sicurezza e la prevenzione degli abusi</li>
</ul>
<p><strong>Controlli unità</strong></p>
<p>
    Se il cliente attiva i controlli unità su categoria e unit, gli esecutori possono registrare OK o Non OK via QR unit (dopo Clock Point), eventualmente con checklist e GPS. Non è una segnalazione: OK non crea issue. WinProx conserva risultato, timestamp, unit, coordinate GPS opzionali ed esecutore. Conservazione come per segnalazioni e task, salvo policy interna diversa.
</p>


<p><strong>ESG e conformità (modulo opzionale)</strong></p>
<p>
    Se il cliente attiva il modulo ESG opzionale, possono essere registrati valori di misurazione e dati di conformità,
    ad esempio durante ispezioni ricorrenti, al completamento delle attività sul portale QR, tramite API oppure — se
    IoT Connect è attivato — da eventi sensore.
</p>
<ul>
    <li>definizioni degli indicatori (nome, tipo, unità, soglie, opzioni), comprese eventuali traduzioni dei testi degli indicatori.</li>
    <li>valori di misurazione (numero, sì/no, scelta o testo) con timestamp.</li>
    <li>collegamento a problematica, attività, sede, unità e opzionalmente al lavoratore; sul percorso sensore l’attività può mancare.</li>
    <li>correzioni come nuove righe (append-only); i valori precedenti restano conservati.</li>
    <li>allarmi di soglia e relative attività di follow-up quando una misurazione esce dai limiti configurati.</li>
    <li>creazione di misurazioni via API e webhook opzionali (ad es. alla registrazione di una nuova riga), se il cliente li collega.</li>
</ul>
<p>
    Il modulo è opzionale e visibile solo agli amministratori quando è attivato. Il cliente è responsabile
    del contenuto e dell’uso dei dati ESG all’interno della propria organizzazione.
</p>

<p><strong>IoT Connect (modulo opzionale)</strong></p>
<p>
    Se il cliente attiva IoT Connect, i gateway possono inviare eventi a WinProx. WinProx non è un cloud IoT né
    una piattaforma di serie temporali: il cliente (o il suo partner hardware) gestisce gateway e sensori; WinProx
    trasforma gli eventi in ingresso in workflow all’interno del tenant.
</p>
<ul>
    <li>configurazione dei gateway e token di autenticazione (memorizzati in modo sicuro; un nuovo token è tipicamente mostrato una sola volta).</li>
    <li>mappature sensore (id esterno → sede/unità, opzionalmente un indicatore ESG).</li>
    <li>regole di allarme (soglie, operatore, team assegnato, priorità, testo).</li>
    <li>record di eventi (elaborato / ignorato / deduplicato / fallito) — nessun archivio continuo di serie temporali.</li>
    <li>in caso di allarme: una segnalazione e un’attività approvate nell’organizzazione (con deduplicazione finché esiste un’attività aperta per la stessa regola).</li>
    <li>in caso di misurazione (Corporate, con modulo ESG): una riga di misurazione ESG basata sull’evento sensore.</li>
</ul>
<p>
    I dati personali nei flussi IoT si limitano a quanto configura il cliente (ad es. assegnazione a team/lavoratori
    tramite segnalazioni e attività). Il cliente resta responsabile delle fonti sensore e del contenuto degli eventi.
</p>

<h2>4. Traduzioni IA</h2>
<p>La piattaforma utilizza traduzioni IA per la visualizzazione multilingue:</p>
<ul>
    <li>traduzione dei testi mostrati in più lingue nella piattaforma o nel portale QR (incluse segnalazioni, attività, unità, annunci, descrizioni di documenti, sedi, categorie, nomi dei team e testi degli indicatori ESG); i testi vengono messi in coda per la traduzione dopo l’approvazione.</li>
    <li>mediante un’istanza locale Ollama (nessun servizio / cloud IA esterno).</li>
    <li>WinProx esegue queste traduzioni periodicamente (di norma ogni giorno), senza tempi di elaborazione garantiti.</li>
    <li>le traduzioni vengono archiviate e conservate conformemente alla politica di conservazione; gli amministratori dell’organizzazione possono correggerle o completarle manualmente nella piattaforma.</li>
    <li>non esiste un interruttore on/off per organizzazione; WinProx può sospendere la pipeline di traduzione a livello di piattaforma.</li>
</ul>

<h2>5. Finalità del trattamento</h2>
<p>I dati vengono trattati per:</p>
<ul>
    <li>il funzionamento della piattaforma.</li>
    <li>la registrazione e il follow-up di problematiche e attività.</li>
    <li>l'assegnazione del lavoro ai team interni e ai lavoratori.</li>
    <li>segnalazioni QR e comunicazione tra gli utenti all'interno della sua organizzazione.</li>
    <li>l'invio di notifiche e-mail seguendo le istruzioni del cliente.</li>
    <li>il miglioramento del prodotto tramite statistiche di onboarding dei superutenti (aggregate ove possibile).</li>
    <li>sicurezza e registrazione delle attività.</li>
    <li>supporto multilingue tramite traduzioni IA (eseguite periodicamente da WinProx, senza tempi garantiti).</li>
    <li>registrazione e follow-up delle misurazioni ESG/conformità (se il modulo è attivato).</li>
    <li>elaborazione degli eventi IoT in segnalazioni, attività e/o misurazioni ESG (se IoT Connect è attivato).</li>
</ul>

<h2>6. Segnalazioni QR e accesso dei team</h2>
<p>
    I codici QR consentono ai segnalanti di inviare problematiche senza un account. Il cliente / amministratore decide quali sedi
    e unità sono disponibili e quali dati vengono richiesti.
</p>
<p>
    Gli utenti con accesso e i team interni hanno accesso in base alle autorizzazioni impostate dal cliente. WinProx tratta
    i dati personali in questo contesto esclusivamente come responsabile tecnico che agisce seguendo le istruzioni del cliente.
</p>

<h2>7. Supporto e accesso</h2>
<p>
    Per il supporto tecnico, WinProx può, in casi eccezionali, accedere ai dati tramite una modalità di supporto per superutenti o personale di assistenza:
</p>
<ul>
    <li>esclusivamente per supporto tecnico e risoluzione dei problemi.</li>
    <li>accesso in sola lettura per impostazione predefinita.</li>
    <li>senza modificare attivamente i dati del cliente, salvo sua esplicita richiesta.</li>
</ul>

<h2>8. Periodi di conservazione</h2>
<p>WinProx applica i seguenti periodi di conservazione:</p>
<ul>
    <li>account utente: attivi + 24 mesi</li>
    <li>problematiche e attività: durata del contratto + 36 mesi</li>
    <li>registri: 6 mesi</li>
    <li>eventi di onboarding per utente (per statistiche di onboarding): 6 mesi; i dati aggregati di onboarding senza dati personali possono essere conservati più a lungo</li>
    <li>media (foto): 24 mesi dopo la chiusura della relativa problematica o attività</li>
    <li>misurazioni ESG: stesso periodo di conservazione di problematiche e attività (durata del contratto + 36 mesi)</li>
    <li>eventi IoT, metadati di gateway e sensore: durata del contratto + 36 mesi (o più breve se la problematica/attività sottostante viene rimossa prima con la cancellazione dell’organizzazione)</li>
    <li>backup operativi di infrastruttura (hosting/Cloud86): 7 giorni</li>
    <li>snapshot SQL tecnico dopo la cancellazione completa dell'organizzazione (senza file media): massimo 30 giorni, poi distruzione</li>
</ul>
<p>
    Dopo una cancellazione completa dell'organizzazione (vedi sotto), i dati live del tenant vengono eliminati in modo definitivo;
    i file media (foto, documenti) non fanno parte dello snapshot di ripristino.
</p>

<h2>9. Comunicazione dei dati</h2>
<p>I dati personali non vengono venduti né comunicati a terzi, salvo:</p>
<ul>
    <li>seguendo le istruzioni del cliente.</li>
    <li>per hosting e infrastruttura tecnica.</li>
    <li>per l'elaborazione dei pagamenti, se sceglie di utilizzarla (tramite un partner di pagamento riconosciuto).</li>
    <li>ove richiesto dalla legge.</li>
</ul>
<p>
    Una panoramica delle categorie di sub-responsabili è disponibile nella pagina
    <a href="{{ route('legal.subprocessors') }}">{{ __('legal.documents.subprocessors') }}</a>.
</p>

<h2>10. Disponibilità internazionale</h2>
<p>
    WinProx è una piattaforma internazionale e può essere utilizzata in più paesi.
</p>
<p>
    La piattaforma può essere disponibile in diverse lingue, tra cui olandese, inglese, francese, tedesco, spagnolo e italiano.
</p>
<p>
    Indipendentemente dalla versione linguistica, la presente informativa sulla privacy si applica al trattamento dei dati personali.
</p>

<h2>11. Diritti degli interessati</h2>
<p>Gli interessati hanno il diritto di:</p>
<ul>
    <li>accedere ai propri dati.</li>
    <li>rettificare i propri dati.</li>
    <li>richiedere la cancellazione dei propri dati.</li>
    <li>opporsi al trattamento.</li>
</ul>

<p><strong>Come la piattaforma lo supporta</strong></p>
<ul>
    <li>
        <strong>Accesso / esportazione:</strong> un amministratore può scaricare un'esportazione leggibile da macchina (JSON in uno ZIP)
        in <em>Impostazioni → Privacy ed esportazione dati</em> del proprio account e dei dati rilevanti dell'organizzazione.
        I download vengono registrati.
    </li>
    <li>
        <strong>Rettifica:</strong> gli utenti autorizzati possono aggiornare il profilo (nome, e-mail, lingua);
        gli amministratori possono aggiornare i dati dell'organizzazione.
    </li>
    <li>
        <strong>Disattivare un utente:</strong> un amministratore può disattivare o mettere in pausa gli account dei colleghi
        (accesso bloccato; sessioni revocate). Non è una cancellazione completa dell'organizzazione.
    </li>
    <li>
        <strong>Eliminare i dati dell'organizzazione (self-service):</strong> solo amministratori, tramite
        <em>Abbonamento → Elimina dati organizzazione</em>. Prima viene offerta un'esportazione; poi conferma
        con password e e-mail a tutti gli amministratori.
        <ul>
            <li><strong>Periodo di prova:</strong> dopo la conferma via e-mail l'amministratore può cancellare definitivamente
                (snapshot SQL tecnico senza media, conservato max. 30 giorni).</li>
            <li><strong>Abbonamento a pagamento / grace:</strong> dopo la conferma vale un periodo di attesa di 7 giorni
                (banner nell'app, e-mail di promemoria circa 2 giorni prima); l'amministrazione WinProx (superuser)
                esegue la cancellazione. Annullamento possibile fino ad allora tramite Abbonamento.</li>
        </ul>
    </li>
    <li>
        <strong>Prova scaduta senza abbonamento:</strong> dopo la fine della prova, l'accesso può restare limitato alle pagine
        di abbonamento/fatturazione. Senza abbonamento, WinProx invia e-mail di avviso e può eliminare l'organizzazione
        automaticamente (predefinito: avviso intorno al giorno 7, cancellazione intorno al giorno 14 dopo la fine della prova).
        L'attivazione di un abbonamento annulla una cancellazione automatica in sospeso.
    </li>
</ul>

<p>Altre richieste o richieste eccezionali (ad es. litigation hold) possono essere inviate a:</p>
@include('partials.wp-legal-operator')

<p>
    Quando i dati vengono trattati seguendo le istruzioni di un cliente, può essere necessario gestire la richiesta tramite tale cliente.
</p>

<h2>12. Sicurezza</h2>
<p>WinProx implementa misure tecniche e organizzative adeguate, tra cui:</p>
<ul>
    <li>isolamento per tenant</li>
    <li>controllo degli accessi</li>
    <li>registrazione delle attività</li>
    <li>backup giornalieri automatici tramite il provider di hosting (Cloud86), conservati 7 giorni</li>
    <li>obiettivi di ripristino: RPO ≈ 24 ore (perdita massima dai dati dall’ultimo backup notturno); RTO best effort, di norma entro 1 giorno lavorativo</li>
</ul>
<p>
    Consulti anche la <a href="{{ route('legal.cookies') }}">{{ __('legal.documents.cookies') }}</a> per informazioni sui
    cookie strettamente necessari.
</p>

<h2>13. Trasferimenti internazionali</h2>
<p>
    I dati vengono generalmente trattati all'interno dell'Unione Europea.
</p>
<p>
    Quando vengono utilizzati fornitori di servizi esterni, vengono adottate garanzie adeguate.
</p>

<h2>14. Autorità di controllo</h2>
<p>
    Ha il diritto di presentare un reclamo a un'autorità di controllo. In Belgio, si tratta dell'Autorità per la protezione dei dati
    (<a href="https://www.gegevensbeschermingsautoriteit.be" rel="noopener noreferrer" target="_blank">www.gegevensbeschermingsautoriteit.be</a>).
</p>

<h2>15. Modifiche</h2>
<p>
    La presente informativa sulla privacy può essere aggiornata.
</p>
<p>
    La versione più recente è sempre disponibile tramite la piattaforma.
</p>
