# WinProx — Project Rules (v2, vereenvoudigde herbouw)

WinProx is **één** facilitaire meldingsapp. De oude sectoren (Real Estate, Hospitality) en
externe partijen (contractors, eigenaars/owners) bestaan **niet** meer.

**Doel:** **dezelfde functionaliteit als de oude WinProx Facility**, maar volledig **schoon
herschreven**. "Simpel" slaat op de **architectuur, de code en de visuele stijl** — niet op het
weglaten van features. We mikken dus op **Facility-pariteit** (locaties/units met bulk + QR-pack,
meldingen met filters, taken, kalender, teams + workers + team-QR, abonnement/proefperiode,
FAQ & kennisbank, juridische documenten, contact, hulp-chat, briefing), zonder de oude rommel.

> Kern: **Melding → Taken → afhandeling**. Een melding kan **meerdere taken** hebben,
> elk toegewezen aan **één operationeel team**.

**Visuele stijl (hard):** screenshots van de oude app dienen als doel voor **layout, structuur en
UX** — **kleuren NIET kopiëren**. Behoud het minimale `standard`-thema (emerald als enige accent,
wit/lichtgrijs, zachte randen, veel witruimte). Geen regenboogaccenten.

> ## ⚠ Werkwijze nr. 0 (hard) — Herlees ALTIJD eerst deze regels
> **V1 is ontspoord doordat regels niet (her)gelezen werden.** Daarom, vóór én tijdens elke taak:
> 1. Lees **`WINPROX_RULES.md`**, de relevante **`.cursor/rules/*`**, en bij ontwerpkeuzes
>    **`WINPROX_DIRECTION.md`** (roadmap, achtergrond) opnieuw — ook bij kleine wijzigingen.
> 2. Toets je plan expliciet aan: één stijl voor knoppen/pillen/kaders, minimale locales (4 talen
>    in pariteit), token-CSS/`wp-*`, thin Livewire → Actions + Form Requests, case-sensitive paden,
>    foto-golden-path, géén blur op beheer, geen sector-/contractor-/owner-overhead.
> 3. Bij twijfel of conflict: **stop en herlees**, kies de regel — niet de snelste hack.
> Doel: kleine, consistente diffs; geen wildgroei zoals in V1.

---

## 1. Stack

- **Backend:** Laravel 12 (PHP 8.2+).
- **Frontend:** Livewire 4 + Blade.
- **Styling:** Tailwind v4 (via `@tailwindcss/vite`) + een kleine, gedeelde `wp-*` componentlaag.
- **Build:** Vite. **DB:** MySQL. **Lokaal:** Windows → **Productie:** Linux.

---

## 2. Cross-platform (Windows → Linux)

- Linux is **case-sensitive**. Behandel bestandsnamen, paden, view-namen, route-namen en
  vertaalsleutels strikt hoofdlettergevoelig. "Werkt lokaal" is geen bewijs.
- Hernoem nooit een bestand enkel op casing zonder dubbelcheck (breekt bij git/Linux).
- Bij Windows↔Linux-afwijking: eerst root cause (case/pad/encoding/cache), dan één minimale fix.

---

## 3. Architectuur (verplicht)

### 3.0 Integration First (hard, fundament)
- **Alle business logic is volledig onafhankelijk van de UI.** Een Action moet **identiek**
  aanroepbaar zijn door **Livewire, REST API, Webhook, Scheduler, Queue Job en CLI Command** —
  **zonder enige code-duplicatie**.
- Gevolg voor Actions:
  - **Geen** `auth()`, `request()`, `session()`, flash, redirects of Blade/HTTP-afhankelijkheden
    binnen een Action. Geef **expliciete** input mee: gevalideerde data + **actor-context**
    (uitvoerende user/worker + tenant) als argumenten/DTO.
  - **Return data** (model/DTO/primitives), nooit een view of HTTP-response.
  - Validatie woont in **Form Requests** voor HTTP-ingangen, maar de regels zijn **herbruikbaar**
    (bv. een statische `rules()`) zodat niet-HTTP-ingangen (scheduler/CLI/job) dezelfde validatie
    kunnen toepassen.
  - Idempotent/queue-veilig waar relevant; tenant-scope expliciet (nooit impliciet via globale state).
- Elke ingang (Livewire-component, API-controller, webhook-handler, command, job) is **dun**:
  input verzamelen → valideren → **één** Action aanroepen → resultaat presenteren in dat kanaal.

### 3.1 Actions — alle business logic
- Alle business logic in `app/Actions/[Module]/[Naam]Action.php`.
- Eén publieke methode: `handle(...)` met expliciete argumenten (zie §3.0); geen verborgen globals.
- Actions doen DB-mutaties, notificaties, jobs, berekeningen, **event-dispatch (webhooks)**. Een Action mag een andere Action aanroepen.
- Geen logica dupliceren: bestaat de workflow al, roep de Action aan.

### 3.2 Livewire-componenten — dun
- **Geen** DB-queries of `Model::create()` in Livewire.
- **Geen** business logic. Rol: input opvangen → valideren via Form Request → **één** Action aanroepen → UI tonen.

### 3.3 Form Requests — validatie
- Validatieregels altijd in `app/Http/Requests/[Module]/[Naam]Request.php`. Nooit in Livewire of Action.
- Regels **herbruikbaar** (statische `rules()`) zodat niet-HTTP-ingangen (API/CLI/job/scheduler) dezelfde validatie kunnen toepassen.

### 3.3a DTOs — getypte input voor Actions
- Actions ontvangen waar mogelijk een **getypte DTO** (`app/Data/[Module]/[Naam]Data.php`), **nooit** een HTTP `Request`.
- De ingang (Livewire/API/CLI) bouwt de DTO uit gevalideerde input; de Action werkt puur met de DTO + actor/tenant-context.

### 3.3b Enums — geen magic strings
- Alle statussen/typen als **PHP enum** in `app/Enums`. Nooit losse string-literals in queries, Blade of Livewire.

### 3.3c Policies — autorisatie op één plek
- Autorisatie **altijd** via `app/Policies`. **Geen** handmatige rolchecks (`isAdmin()` e.d.) verspreid in controllers, Livewire of Blade — gebruik `authorize()`/`can()` die naar de policy verwijzen.

### 3.3d Audit logging — elke DB-write
- Elke schrijfactie wordt gelogd in `audit_logs`: **`user_id, tenant_id, action, model_type, model_id, payload, created_at`**.
- Centraal (bv. een `LogAuditAction`/listener op domein-events), niet ad hoc per scherm dupliceren.

### 3.3e Verboden patronen (hard)
- **Geen** `DB::` of `Model::create()/update()/delete()` buiten een Action.
- **Geen** business-logica in models, Blade of Livewire (models = relaties/scopes/casts/accessors).
- **Geen** repository-pattern, service-laag-duplicatie of static workflow-helpers.
- **Geen** N+1: eager-load relaties, filter in de database (niet in collections), **pagineer** lijsten.

### 3.4 API & Webhooks — first-class (vanaf het begin)
- **WinProx is API-first.** Elke domeinmutatie loopt via een **Action**; web (Livewire) én **REST API**
  zijn slechts twee dunne ingangen op **dezelfde Actions + dezelfde Form Requests**. Nooit logica
  dupliceren voor de API.
- **REST API:** versioned onder **`/api/v1`**, JSON in/uit, **token-auth via Laravel Sanctum**
  (personal access tokens, per gebruiker, **tenant-scoped** via de token-eigenaar). Consistente
  response-envelope + foutformaat; paginatie op lijsten. Routes in `routes/api.php`,
  controllers in `app/Http/Controllers/Api/V1/...` (dun → Form Request → Action → JSON resource).
- **Webhooks (uitgaand):** domein-events (bv. `issue.created`, `issue.approved`,
  `issue.status_changed`, `task.created`, `task.started`, `task.completed`) worden **per tenant**
  naar geregistreerde endpoints gestuurd. Verplicht: **queued** levering, **HMAC-signature** header
  (shared secret per endpoint), **retries** met backoff, en een **delivery-log**. Beheer van
  endpoints + events in de app (admin).
- **Webhooks (inkomend):** generieke, geverifieerde ontvangst-endpoints onder `/api/v1/hooks/...`
  (signature/secret check) → mappen naar een Action. (Stripe-webhooks blijven hun eigen latere fase.)
- **Elke nieuwe feature** levert daarom mee: (a) de Action(s), (b) waar zinvol een API-endpoint, en
  (c) de relevante webhook-event(s). Test API + webhook-dispatch in Pest.

---

## 4. Datamodel & statussen

### 4.1 Entiteiten (kern)
- **Tenant** — organisatie (root van multi-tenancy). Geen sector meer.
- **User** — beheerder/staff met login.
- **Location** — locatie/site (was "Property"; we werken **niet** meer met eigendommen).
- **Unit** — asset/ruimte/machine binnen een Location, met QR-token.
- **InternalTeam** — operationeel team (team-QR voor de werkvloer).
- **Worker** — uitvoerder **zonder** login (meldt zich aan via team-QR).
- **Issue** — de **melding**.
- **Task** — een **taak** op een melding, toegewezen aan **één team**. Een melding heeft er ≥1.
- **IssueUpdate** — tijdlijn/notities op een melding.
- **Document** / **Announcement** — per Unit/Location (bv. handleiding van een machine, mededeling "groot onderhoud").

### 4.2 Statussen (exact vier)
Statussen leven op de **Task**:

| Status | Betekenis |
|--------|-----------|
| **Nieuw (Open)** | Taak aangemaakt, nog niet gestart. |
| **In uitvoering** | Team/uitvoerder is bezig. |
| **Afgehandeld** | Uitvoerder meldt het werk klaar. |
| **Gesloten** | Beheerder bevestigt en sluit. |

De **melding (Issue)** krijgt een **afgeleide** status uit haar taken:
- alle taken **Gesloten** → melding **Gesloten**;
- alle taken **Afgehandeld**/Gesloten → melding **Afgehandeld**;
- minstens één taak **In uitvoering** → melding **In uitvoering**;
- anders → **Nieuw**.

Geen extra statussen (`on_hold`/`not_executed` bestaan niet; vroeger inklappen naar resp. *In uitvoering* / *Gesloten*).

### 4.3 Migratie-discipline
- DB wordt vers opgebouwd (greenfield). Maak nette migraties; geen productie-migraties achteraf wijzigen.
- Foreign keys expliciet benoemen. Indexen bewust op `tenant_id`, `status`, `created_at` en veelgebruikte relaties.

### 4.3a Tekstkolommen (hard)
- Vrije, door gebruikers ingevulde **leestekst** op tenant-modellen heet **`description`** (`text`, `nullable` indien optioneel).
- **Maximum 500 tekens** voor meldingen, taken, tijdlijn-updates, mededelingen, afhandelingsnotities, redenen (sluiten/heropenen/pauze) en overige portaal-/beheer-omschrijvingen (document, unit). DB: `VARCHAR(500)` op brontabellen; validatie via `TextDescriptionLimits::MAX`.
- **Vertalingstabellen:** `{model}_translations.description` — zelfde kolomnaam; **max. 1500 tekens** (`TextDescriptionLimits::TRANSLATION_MAX`, DB `VARCHAR(1500)`) omdat vertalingen langer kunnen uitvallen dan de brontekst. Geen stille afkap — te lange import/vertaling → `failed`.
- **Uitzonderingen (niet hernoemen naar `description`):** `title` (korte label/titel, bv. documentnaam), `notes` (interne admin-notities, meervoud), `message` (contactformulier), systeemvelden (`error`, `user_agent`, …).
- **Geen** nieuwe varianten (`body`, `note`, `text`, `content`) op domain-modellen voor leesbare inhoud.

### 4.4 Multi-tenancy
- Gedeelde DB met `tenant_id` per rij.
- Gebruik een **global scope via trait** (bv. `BelongsToTenant`) zodat elke query automatisch op de tenant filtert. Nooit handmatig vergeten.
- De scope is **bewust omzeilbaar** voor de platform-**superuser** (zie §8).

---

## 5. Lokalisatie (4 talen, klein & per pagina)

- Structuur: **`lang/[locale]/[page].json`** (één bestand per pagina/module). Plus `common.json` voor gedeelde labels (knoppen, statussen).
- Talen **altijd samen**: `nl`, `en`, `fr`, `de`, `es`. Uitbreidbaar (it, …) — talenlijst is data-gedreven.
- Bestanden **minimaal houden (hard)**: **alleen** sleutels die op het scherm écht gebruikt worden. Geen ongebruikte/legacy keys, geen duplicaten, geen "voor het geval dat". Volledig herschreven vanaf nul; geen oude rommel meeslepen. Bij twijfel: key verwijderen i.p.v. behouden.
- **Eén sleutel-conventie:** lowercase, punt-genest, betekenisvol: `[page].[sectie].[element]` (bv. `issues.list.empty_title`, `common.button.save`).
- **Nooit hardcoden** in Blade/PHP. Strikte JSON (geen comments/trailing comma's), UTF-8 **zonder BOM**.
- Na elke edit: `npm run fix:locales` → `npm run check:locales` → `npm run check:locales:parity`. Alle talen moeten identieke sleutels hebben.

---

## 6. UI & CSS (simpel, token-gebaseerd)

### 6.1 Bron & volgorde
- Eén Vite-entry `resources/css/app.css`: Tailwind → Inter (`@fontsource`) → `tokens.css` → `themes/<thema>.css` → `base.css` → `layout.css` → `components.css` → `buttons.css` → `forms.css`.
- **Geen** `<style>`-blokken in Blade. Geen tweede los stylesheet.

### 6.2 Tokens & thema's
- **`tokens.css`** = structurele tokens (radius, schaduw, knop-geometrie, transitie) — thema-onafhankelijk.
- **`themes/standard.css`** = de **kleuren** van het standaardthema (licht, modern, emerald). **Dit is voorlopig het enige thema.**
- Een later thema is enkel een nieuw bestand dat dezelfde variabelen overschrijft onder `[data-theme="..."]` (bv. donker/grijs). Componenten veranderen daarbij **niet**.
- Componenten verwijzen **altijd** naar variabelen, nooit naar harde kleuren.

### 6.3 Kleuren (standaardthema)
- Achtergrond `#f9fafb`, oppervlak `#ffffff`, rand `#e5e7eb`.
- Tekst: kop `#111827`, body `#4b5563`, secundair `#6b7280`. **Nooit puur zwart `#000`.**
- Accent emerald: `#059669` / `#047857` / `#ecfdf5` / `#bbf7d0`.

### 6.4 Knoppen, pillen & kaders — ÉÉN definitie, ALTIJD hergebruiken
- **Hard principe (overal, altijd):** knoppen, pillen en kaders/kaarten hebben **één** gedeelde
  stijl-definitie en worden **overal consistent hergebruikt**. **Nooit** per scherm opnieuw stijlen
  met losse utility-combinaties of inline CSS. Eén visuele taal door de hele app — beheer én publiek.
- **Knop:** altijd `.btn` + één variant (`--primary` emerald, `--ghost` secundair, `--warning` amber, `--danger` red). Geometrie uit tokens (hoogte 2.5rem, radius 1rem, `font-weight:700`). Hover `translateY(-2px)` + zachte schaduw; active terug naar 0. **Geen** losse utility-knoppen per scherm.
- **Pil/status:** altijd `.wp-pill` + één variant per status: `--new` (Nieuw), `--progress` (In uitvoering), `--done` (Afgehandeld), `--closed` (Gesloten). **Geen tien stijlen voor dezelfde pil.**
- **Kader/kaart/paneel:** altijd `.wp-card` (oppervlak `#ffffff`, rand `#e5e7eb`, radius 12–16px, zachte gelaagde schaduw, consistente padding). Sectiekop binnen een kaart via gedeelde class, geen ad-hoc varianten. **Geen tien soorten kaders.**
- Nieuwe variant nodig? Definieer hem **één keer** in de gedeelde CSS (tokens + component-class), hergebruik daarna overal. Wijk je af, dan eerst de gedeelde class uitbreiden — niet lokaal overschrijven.

### 6.5 Apparaat-targeting (responsive)
- **Beheersschermen** (dashboard, meldingen, beheer van locaties/units/teams) → **laptop/desktop-first**. Mogen breed/meerkoloms zijn, maar blijven bruikbaar op kleiner scherm.
- **Veld- en publieke schermen** (publieke QR-meldpagina, team-QR veldportaal, worker-schermen) → **mobiel-first**: één kolom, grote tap-doelen (knoppen min. 2.5rem hoog), belangrijkste actie onderaan binnen duimbereik.
- Zelfde tokens/componenten en **één** CSS-bundel; responsiviteit via eenvoudige breakpoints, geen aparte stylesheet-stack per apparaat.

### 6.5a UI-filosofie
- Voorkeur voor **modals/inline-interacties** boven losse pagina's; minimale navigatiediepte.
- Workers ronden een flow af in **zo weinig mogelijk taps** (veld/portaal, mobiel-first).
- **Modals:** altijd `<x-wp-modal closeMethod="...">` — **Esc** sluit de popup (niet handmatig
  `<div class="wp-modal">` zonder listener). Sluit-methode = dezelfde als Annuleren/X.
- **Paginering / bladerknoppen:** één stijl via `.wp-pagination__control` en `.wp-pagination__page`
  (views `vendor/livewire/tailwind`, `vendor/pagination/tailwind`, component `x-wp-detail-nav`).
  Geen losse `.btn`-varianten voor vorige/volgende/paginanummers.

### 6.6 Na UI-wijziging
- Altijd `npm run build`; vraag bij visuele controle om harde refresh (`Ctrl+F5`).

---

## 7. Foto-upload (QR-meldingen) — golden path

1. Client-side comprimeren vóór upload (max 1600px, JPEG ~72%) in `.wp-photo-upload-area` (tot 4 foto's).
2. Directe preview via lokale `objectURL`.
3. Upload op achtergrond (queue), UI niet blokkeren.
4. `wire:ignore` op de upload-area; **geen** `wire:model` op de file-input.
5. Server slaat op zonder backend-resize (geen Imagick/GD-resize). Foto's in tabel `issue_photos`.

### 7.1 Moderatie (review vóór publicatie) — verplicht
- Een melding uit een QR-inzending is **niet goedgekeurd** bij aanmaken (`issues.approved_at` is null).
- **Blur geldt UITSLUITEND op publieke QR-pagina's** (na scan): zolang niet goedgekeurd worden
  **beschrijving én foto's geblurd** getoond met overlay "Wacht op controle". Gebruik de klasse
  **`.wp-pending-review`** (blur + overlay).
- **Beheerschermen (desktop) tonen NOOIT geblurd.** Dashboard, meldingen, taken, detail enz. zijn
  alleen voor **beheerders en medewerkers**; zij moeten de inhoud juist **onverkort** zien om te
  kunnen beoordelen en goedkeuren.
- Een beheerder/medewerker keurt goed via **`ApproveIssueAction`** (zet `approved_at`/`approved_by`);
  pas dan unblurt de inhoud ook op de publieke pagina's. Afkeuren = verwijderen.
- Doel: voorkomen dat een malafide melder compromitterende foto's of tekst **publiek** publiceert.

---

## 8. Superuser

- De platform-**superuser** kan een tenant **overnemen/impersoneren** om mee te kijken/helpen.
- Bij impersonatie wordt de tenant-context op die tenant gezet (de global scope filtert dan op die tenant).
- Zonder impersonatie (platformbeheer) wordt de tenant-scope **expliciet en gecontroleerd** omzeild. Nooit ongemerkt data van meerdere tenants mengen.

---

## 9. Tests & compliance-checks

- **Pest** + **factories** voor elk model.
- **Elke Action heeft tests**; voorkeur voor **integratietests** op workflows. Mock alleen externe services.
- Dek de kern-flow: melding aanmaken → taak/taken → statusovergangen → afgeleide meldingstatus → tenant-isolatie → superuser-impersonatie → **API + webhook-dispatch (HMAC)**.
- Vóór merge/PR groen:
  - `php artisan test`
  - `npm run check:locales:parity`
  - `npm run check:architecture`
  - `npm run build` (bij CSS/JS/views)

---

## 10. Git

### Werkwijze (hard)
- We werken **lokaal** (Windows); productie staat op de **server** (Linux).
- Een taak is pas af wanneer het **lokaal werkt** én de wijziging **gecommit en gepusht** is naar `origin` (server deployt vanuit git).
- Niet afsluiten met klaar, uncommitted werk.

### Vóór commit
- `php artisan test` (relevante tests)
- `npm run check:locales:parity`
- `npm run check:architecture`
- Bij frontend-wijzigingen (`resources/css/**`, `resources/js/**`, views, Vite): **eerst** `npm run build`, gewijzigde `public/build/**` meecommitten.
- Raakt het `lang/**`: alle vier talen + `fix:locales`/`check:locales`/`check:locales:parity` vóór commit.
- Geen test-artefacten committen (`tests/Avery_*.zip`, `_extract/`, `.env`, logs).

### Push
- Na commit: **`git push`** naar `origin`, tenzij de gebruiker expliciet vraagt om niet te pushen.

---

## 11. AI-veiligheid

- Lees bestaande code vóór je hem wijzigt. Minimale diffs; geen grote rewrites zonder vraag.
- **Geen** repo-brede alignment- of “alles schoon”-runs (zie §13).
- Verwijder geen vertalingen/routes/CSS-classes zonder gebruik te checken.
- Bij twijfel: stop en vraag het exacte gewenste gedrag.
- Houd het **simpel**. Lees deze regels regelmatig.

---

## 12. Roadmap & toekomst (achtergrond — niet nu alles bouwen)

WinProx heeft een **lange-termijnrichting** (offline veld, PLG, AI, enterprise-integraties). Die staat
in **`WINPROX_DIRECTION.md`**. Houd die **in het achterhoofd** bij ontwerpkeuzes — bouw ze **niet**
proactief tenzij de taak dat expliciet vraagt.

**Lees `WINPROX_DIRECTION.md` wanneer je:**
- een nieuw datamodelveld, API-contract of veld-flow ontwerpt;
- twijfelt of iets “nu” of “later” hoort;
- V1-code porteert en wilt weten wat we bewust **niet** terugbrengen.

**Onthoud:**
- **Nu:** Facility-pariteit (`docs/FEATURES.md` + `WINPROX_FEATURES.md`).
- **Handleiding-screenshots:** runbook `docs/MANUAL_SCREENSHOTS.md` — golden path =
  `.\scripts\capture-manual-local.ps1` (lokaal Windows), PNG's committen; server-capture op Plesk
  shared meestal niet haalbaar.
- **Fundament al in deze regels:** Actions, Events, API v1, webhooks, Policies, audit — dat *is* de
  voorbereiding op integraties; geen aparte “integration rewrite” later als je je aan §3 houdt.
- **Later (roadmap):** offline PWA, PLG-marketingtools, AI, Zapier/SAP/… — elk als **laag boven**
  dezelfde Actions, niet als vervanging van Livewire-logica of DB-hacks.
- **Twee UI-oppervlakken:** beheer (Livewire) vs veld/publiek (QR/worker). Veldlogica altijd in
  Actions — offline komt later via API, niet via Livewire-offline-hacks.

**Prioriteit bij conflict:** `WINPROX_RULES.md` > `docs/FEATURES.md` > `WINPROX_DIRECTION.md` > V1-code.

Roadmap-principes (QR first, worker first, mobile first, simplicity wins) **informeren** keuzes; ze
**overschrijven** geen pariteit- of magerheidsregels uit dit document.

---

## 13. Onderhoud na V2-alignment (hard voor AI & mensen)

De brede architectuur-alignment is **afgerond**. **Geen** repo-brede “alles nalopen”-runs meer —
die leveren kleine winst en grote diffs.

### 13.1 Wanneer wél opschonen
- **Nieuwe feature of bugfix** in een bestand → volg §3 meteen (Action, policy, test).
- **Bestaand bestand dat je toch wijzigt** → fix **alleen** regelovertredingen in dat bestand
  (geen refactor van buren).
- **CI/`check:architecture` faalt** → fix de gerapporteerde plek.

### 13.2 Bewuste uitzonderingen (geen “fix” nodig)
- **`app/Support/Portal/*`** (`WorkerDeviceSession`, `WorkerVerification`, `WorkerIconGuard`):
  cookie/sessie/request horen in de HTTP-laag; DB-mutaties via Actions.
- **E-mailtemplates** (`resources/views/emails/**`, `resources/views/mail/**`): inline styles zijn normaal.
- **Legacy inline `style=` in QR-portalen/settings/manual**: alleen wegwerken **als je dat scherm toch
  aanpast** — verplaats naar `wp-*` in `components.css`.
- **`is_superuser` in platform-blades** voor badges/labels: OK zolang autorisatie via **Policies**
  + `authorize()`/`@can` loopt (geen `isAdmin()` in views).

### 13.3 Definition of done (elke wijziging)
1. Business logic in **één Action** (expliciete tenant + actor).
2. Livewire/API **dun** → Form Request → `app(Action::class)->handle(...)`.
3. Autorisatie via **Policy** — geen losse rolchecks in Blade.
4. Waar zinvol: test + audit; 4 locales in pariteit bij `lang/**`-wijzigingen.
5. `npm run check:architecture` groen.

### 13.4 Automatische check
`npm run check:architecture` faalt bij:
- `Model::create/update/delete/save` of `DB::` in **Livewire**;
- `DB::` buiten **`app/Actions`**;
- `isAdmin()` in **Blade** (beheer-views; niet e-mail).
