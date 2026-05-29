# WinProx — Functionele specificatie (Facility-pariteit)

Levende specificatie, opgebouwd door het menu **top‑down** te doorlopen met de gebruiker.
Doel: dezelfde functionaliteit als de oude WinProx Facility, **schoon herschreven**, in de
minimale `standard`-stijl (kleuren NIET uit de oude app overnemen — zie `WINPROX_RULES.md`).

Per scherm: doel · weergave · acties · data · rollen · device · bijzonderheden.

> **Hergebruik-principe:** oude code uit `winprox_old` mag (en moet, om niet alles opnieuw uit te
> vinden) als basis dienen, **maar moet voldoen aan de WinProx V2-regels** (`WINPROX_RULES.md` +
> `.cursor/rules/*`): token-CSS/`wp-*`, thin Livewire → Actions, Form Requests, 4 locales met
> pariteit, case-sensitive paden, foto-golden-path, géén blur op beheer. **Regels regelmatig
> herlezen** vóór en tijdens het bouwen.

> **Moderatie/blur (hard):** Het bluren van foto's én tekst geldt **uitsluitend op de publieke
> QR-pagina's** (na scan), om compromitterende inhoud vóór goedkeuring te verbergen. De
> **beheerschermen** (dashboard, meldingen, taken, …) zijn alleen voor **beheerders en
> medewerkers** en tonen alles **onverkort** — zij moeten de inhoud juist kunnen beoordelen en
> goedkeuren. Dus: **geen blur op desktop/beheer**.

Menu-volgorde (sidebar): Dashboard · Locaties/units · Meldingen · Taken · Kalender ·
Team · Abonnement · FAQ & kennisbank · Juridische documenten · Contact.

---

## 1. Dashboard

**Doel:** eerste scherm na login; overzicht van locaties, units, meldingen en taken.

**Header**
- Titel "Dashboard" + subtitel "Overzicht van je locaties, units, meldingen en taken".
- Knop **"Briefing afdrukken"** → printbare briefing met **alle taken die de teams vandaag
  moeten afhandelen** (dagoverzicht per team).
- **Proefperiode-capsule**: "Proefperiode nog X dagen." (zie §Abonnement).

**KPI-kaarten** (neutraal/minimaal, emerald enkel als subtiel accent):
- Locaties/units — totaal
- Units — totaal
- Nieuwe meldingen — aantal nieuw/open
- Open taken — "In uitvoering"

**Recente meldingen** (kaart met lijst)
- "Laatste activiteit door melders of team", met nadruk op **nieuwe** meldingen.
- Rij: omschrijving · locatie — unit · adres · datum/tijd + "gemeld door {naam}" · status-pill.
- Knop **"Meldingen openen"** → meldingenlijst.

**Zwevende elementen**
- **WinProx-logo** zweeft rechtsboven.
- **WinProx-assistent** zweeft rechtsonder (chat):
  - Gebruiker stelt een vraag.
  - Staat het antwoord in de DB → toon antwoord.
  - Zo niet → **vraag opslaan** in een tabel + **e-mail naar de superuser**.
  - Assistent is **gekoppeld aan de FAQ & kennisbank**.

**Device:** desktop-first (laptop).

**Nieuw t.o.v. huidige bouw (backlog):** Briefing-print (taken van vandaag per team),
WinProx-assistent (DB-Q&A + onbeantwoorde-vragen-tabel + superuser-mail + FAQ-koppeling).

---

## 2. Locaties/units

**Doel:** beheer van **locaties** en de **units** daaronder (machines, installaties, zones).
Twee niveaus: **locatie-lijst** → klik op een locatie → **locatie-/unit-detailscherm**.

### 2.1 Locatie-lijst
- Header: titel "Locaties/units" + subtitel "Beheer je locaties en units (machines,
  installaties, zones)".
- Knop **"+ Nieuwe locatie toevoegen"** → modal (§2.2).
- **Zoek-kaart**: zoekveld "Zoek op locatie, plaats, postcode of straat" (zoekt op naam,
  plaats, postcode én straat). Hint "Klik op een locatie om te beheren". Checkbox
  **"Toon ook inactieve locaties"** (standaard verbergt inactieve).
- **Lijst-rij** per locatie: naam · badge **"{n} units"** · adres (straat nr, postcode plaats).
  Klik op de rij → locatie-detail (§2.3). Rechts knop **"Deactiveren"** (soft-delete/inactief
  zetten, niet hard verwijderen).

### 2.2 Modal "Nieuwe locatie toevoegen" / "Locatie bewerken"
Velden:
- **Locatienaam** (optioneel)
- **Straat** + **Huisnummer**
- **Postcode** + **Plaats**
- **ISO-landcode** (geldige tweeletterige landcode, bv. BE)
- **Interne notities** (textarea)

Acties: **Annuleren** / **Locatie opslaan**. Validatie via Form Request; logica in een Action.

### 2.3 Locatie-/unit-detailscherm
- Header: locatienaam + **"Briefing afdrukken"** + **vorige/volgende**-navigatie (‹ ›) om snel
  tussen locaties te bladeren. Subtitel "Overzicht van locatie en units".
- Kaart **"Gegevens van de locatie"**: adres + knoppen **"Locatie bewerken"**,
  **"Deactiveren"**, **"QR-code locatie (algemeen)"** (algemene locatie-QR, los van unit-QR's).
- Kaart **"Units"** met badge **"{n} Totaal"** + "Beheer units gekoppeld aan deze locatie."
  Knoppen:
  - **"QR-stickerblad downloaden"** → **MS Word (.docx)** met **Avery 55×55 mm, 15 stickers/A4**
    (zie §2.5 — werkt perfect in oude app, **overnemen i.p.v. herbouwen**).
  - **"+ Unit toevoegen"** → unit per stuk.
  - **"Bulk units toevoegen"** → modal (§2.4).
- **"Recente bulk-aanmaak"**: lijst van bulk-batches (datum/tijd · aantal · unitnaam-bereik),
  met per batch **"Bulk verwijderen ({n})"**. Regel: *"Verwijder per bulk alleen units zonder
  melding of taak. Oudere bulks blijven apart staan."* Units in een bulk die al een melding/taak
  hebben **blijven staan** (melding per batch: "{n} unit(s) in deze bulk hebben al een melding of
  taak en blijven staan").
- **Unit-rij**: unitnaam · gekoppeld **team** · status-badge (bv. "Open melding") + knoppen
  **Bewerken** · **Deactiveren** · **Verwijderen** (uitgeschakeld als de unit een melding/taak
  heeft) · **Unit-QR** (individuele QR-code = publieke meld-link `/melden/{token}`).

### 2.4 Modal "Bulk units toevoegen"
Genereert in één keer meerdere units volgens een patroon.
- **Aantal reeksen** · **Units per reeks**.
- **Nummering** (dropdown), bv. "01, 11, 21… (twee cijfers: bv. Kolomboormachine 11 en 21 —
  max. 9 per reeks)".
- **Optionele prefix** (bv. "Kolomboormachine" → "Kolomboormachine 11", "… 21" …), met
  voorbeeld-hint.
- **Team** (dropdown): "Geen team (later per unit koppelen)" of een vast team voor álle nieuwe
  units (handig bij meerdere machines van hetzelfde team).
- **VOORBEELD**-blok: live preview van de eerste paar gegenereerde unitnamen.
- Acties: **Annuleren** / **Alle units aanmaken**.

**Data (te bevestigen tegen schema):** `locations` (naam, straat, huisnummer, postcode, plaats,
iso_landcode, interne_notities, actief/inactief) en `units` (naam, location_id, internal_team_id,
qr_token, bulk-batch-referentie t.b.v. "recente bulk-aanmaak", actief/inactief).

**Device:** desktop-first (beheer).

**Nieuw t.o.v. huidige bouw (backlog):** locatie-CRUD + (de)activeren, zoek/inactief-filter,
locatie-detail met vorige/volgende, algemene locatie-QR, QR-stickerblad (QR-pack) downloaden,
unit-CRUD per stuk, **bulk-aanmaak met patroon + preview**, recente-bulk-beheer met veilige
verwijderregels (niet verwijderen wat een melding/taak heeft), unit-QR → publieke meld-link.

### 2.5 QR-stickerblad (.docx) — OVERNEMEN uit `winprox_old`
De Word-export werkt perfect in de oude app → **bijna 1-op-1 porten**, niet herbouwen. Formaat:
**Avery 55×55 mm, 15 stickers per A4** (5 rijen × 3 labelkolommen + gutterkolommen), QR ±35 mm met
WinProx-logo-overlay in het midden, headline + primaire/secundaire labelregels per sticker.

**Bron (kopiëren, `App\Support\Qr\…` namespace behouden):**
- `app/Support/Qr/Word/Avery55x55WordStickerSheetBuilder.php` — bouwt de .docx (PhpWord, vaste
  Avery-marges/maten, 5×5 tabel met label-kolommen [0,2,4]).
- `app/Support/Qr/Word/QrStickerWordExporter.php` — orchestratie/entry-point.
- `app/Support/Qr/Word/WordDocxStickerExportSanitizer.php` — post-processing van de .docx.
- `app/Support/Qr/QrStickerSheetTemplate.php` (enum: labels-per-pagina), `QrStickerEntry.php`
  (DTO: reportUrl + primary/secondary label), `FacilityQrPackStickerEntries.php`
  (units → sticker-entries).
- `app/Support/Qr/QrCodePngWriter.php` — QR-PNG met logo-overlay (bacon/bacon-qr-code + GD/Imagick).
- `app/Http/Controllers/FacilityQrPackDownloadController.php` — download-route.
- Tests: `FacilityQrPackWordDownloadTest`, `FacilityQrPackStickerEntriesTest`,
  `WordDocxStickerExportSanitizerTest` (mee overnemen).
- Asset: `public/images/Winprox_logo_200.png` (logo-overlay).

**Dependencies (toevoegen):** `phpoffice/phpword: ^1.1`, `bacon/bacon-qr-code`, en PHP-extensie
**gd** of **imagick** voor PNG-rendering. ⚠️ Let op: de "geen server-side resize"-regel geldt voor
**foto-uploads**, niet voor QR-generatie — GD/Imagick is hier legitiem nodig.

---

## 3. Meldingen

**Doel:** beheerlijst van alle meldingen (issues) van de tenant, met filters, statusgroepering en
de aanmaak-flow. Bron: V1 `app/Livewire/Issues.php` + `resources/views/livewire/issues.blade.php`
(sterk uitgedund: geen onboarding/demo, contractors/invitations/quoting, hospitality-triage,
fulfillment-routing of trades). **Beheerscherm = nooit blur.**

### 3.0 Statusmapping (V1 → V2, 4 verminderde statussen)
| V1-status | V2 |
|---|---|
| `new` + `open` | **Nieuw (Open)** (`new`) |
| `in_progress`, `on_hold` | **In uitvoering** (`in_progress`) |
| `completed` / afgehandeld | **Afgehandeld** (`done`) |
| `closed`, `not_executed` | **Gesloten** (`closed`) |

Taken hebben hun eigen status; de **meldingstatus rolt af** uit de taken (bestaande
`Issue::recalculateStatus`). Geen losse `open` meer naast `new`.

### 3.1 Lijst
- Header: titel + primair **"+ Melding toevoegen"** (opent aanmaak-flow §3.3) + ghost
  **"Briefing afdrukken"**.
- **Filterkaart**: status-select (de 4 statussen + "alle") + **GO!**, **team**-select, **zoek**veld,
  checkbox **"terugkerend"** (alleen recurring tonen). Zoeken over: omschrijving, melder, en
  locatie (naam/adres/straat/nr/postcode/plaats) + unitnaam.
- **Groepering per status** met accent-header + telbadge, volgorde
  **Nieuw → In uitvoering → Afgehandeld → Gesloten**, daarbinnen nieuwste eerst.
- **Meldingskaart**: NR (id), omschrijving (onverkort — beheer), locatie · unit · adres,
  herkomst (QR/handmatig) + "gemeld door", toegewezen **team(s)**, status-pill. Net aangemaakte
  melding kort **gehighlight** (query `highlight_issue` / sessie).
- Klik → detail (`issues.show`).

### 3.2 Teamscope (later)
V1 kan de lijst scopen tot teams die een beheerder mag zien (`FacilityTeamAccess`). V2: optioneel
later; standaard ziet een tenant-beheerder alles binnen de tenant.

### 3.3 Aanmaak-flow (Facility = 2 stappen)
V1 "easy flow" voor facility, ontdaan van contractor/hospitality-stappen:
1. **Stap 1** — locatie + (optioneel) unit + **omschrijving** (min 3) + tot 4 foto's
   (+ optioneel **terugkerend**, §3.4). → maakt `Issue` (`source=manager`, `status=new`).
2. **Stap 2** — **taaknotitie** + **team** kiezen → maakt `Task` (status `new`/assigned-equivalent);
   melding → **In uitvoering**. Voor Facility sluit de flow hierna af (geen stap 3).
Logica in Actions (`CreateIssueAction` + taak-aanmaak), validatie via Form Request. Foto-golden-path.

### 3.4 Terugkerende meldingen (recurring) — **BESLIST: nu meenemen**
V1: een melding kan **terugkerend** zijn (interval **waarde** + **eenheid** week/maand/kwartaal/jaar,
**lead days**, **eerste vervaldatum**) en genereert periodiek taakcycli (koppelt aan **Kalender**).
Samen bouwen met **Meldingen + Kalender** (volgende fase, na het QR-portaal).
- Schema (nieuwe migratie, greenfield): `issues.is_recurring`, `recurrence_interval_value`,
  `recurrence_interval_unit`, `recurrence_lead_days`, `recurrence_next_due_at`,
  `recurrence_last_task_created_at`; op `tasks` velden voor cyclus (`due_at`, `scheduled_for`,
  `is_recurring_cycle`, `cycle_number`, `recurrence_issue_id`).
- Filter **"terugkerend"** wordt een echte filter (niet langer no-op); aanmaak-flow krijgt de
  recurring-opties; cycli verschijnen in de Kalender.

### 3.5 NIET overnemen (cruft / vereenvoudiging)
Onboarding/demo quick-flow, contractors + `TaskInvitation`/quoting/assignment-modes,
hospitality-triage (`awaitingTriageOnly`, `fulfillment_routing`), trades/work-types,
desktop-handoff-login, `report_finalized_at`, categorie-verplichting. Property→Location.

---

## QR-portaal (publiek, ná scan) — uitgebreid, overnemen uit `winprox_old`

> Dit is het scherm dat een bezoeker/worker ziet **na het scannen van een QR-code**. In de oude
> app is dit zeer uitgebreid en werkt het goed → **gedrag overnemen**, maar **schoon herschreven**
> (Property→Location, één sector, geen contractors/owners, geen debug-logging, geen dode variabelen).
> Onze huidige nieuwe bouw heeft slechts een **minimale** meld-/portaalversie; dit hoofdstuk
> beschrijft de **doel**-functionaliteit.

### Twee QR-types / twee persona's
- **Unit-QR** (`/melden/{unit_token}`, oud: `facility.report.show`): **burger** én **on-site worker**
  zien dezelfde URL; de worker-acties verschijnen alleen als het toestel als veldtoestel herkend
  wordt (device-cookie/verified sessie).
- **Team-QR** (`/team/{field_qr_token}`, oud: `facility.team.field.show`): **worker**-overzicht van
  open taken. **BESLIST:** via team-QR zijn taakacties **alleen-lezen** — afhandelen moet via de
  **unit-QR** (oude regel `actions_require_unit_qr`).

### Unit-portaal — secties (burger)
`home · new · issues · issue_detail · documents · announcements`
- **home**: tenant-logo, locatie/unit-regel, tegels: **Nieuwe melding** (altijd), en — alleen indien
  niet leeg — **Open meldingen**, **Mededelingen**, **Documenten**. Voor veldworkers extra: blok met
  open taken + sign-in.
- **new** (melden): veld **omschrijving** (verplicht, min 3) + **tot 4 foto's** (`image`, max 10 MB).
  Geen naam/e-mail/categorie op de Facility-unitpagina. Submit → maakt:
  - `Issue` (`source=tenant`, `category=unspecified`, `priority=medium`, `status=new`,
    `report_finalized_at=now`), gescoped op tenant/location/unit.
  - Foto's via `IssuePhotoStorage` (client comprimeert ≤1600px/JPEG ~72%, queue; server geen resize).
  - **Automatisch een taak** voor het **standaardteam van de unit** (oud: `FacilityQrIntake`),
    starttaakstatus = onze `Nieuw`/`assigned`-equivalent; geen team → enkel melding.
  - Daarna flash "melding verzonden" → sectie `issues`.
- **issues**: lijst open meldingen van deze unit (omschrijving), → **detail**.
- **issue_detail**: omschrijving + datum; burger ziet evt. statusregel ("gepland"/"in uitvoering");
  worker ziet sign-in + taakacties.
- **documents**: titel + omschrijving; downloadlink alleen als `is_public && !requires_verification`,
  anders "verificatie vereist". Bron: documenten gekoppeld aan **locatie** (en optioneel unit),
  `is_active`, gepubliceerd (`published_at ≤ now`). (Oud: `PropertyDocument`.)
- **announcements** (mededelingen): bericht-tekst; `is_active`, gepubliceerd en niet verlopen
  (`expires_at`). Use-case: "volgende week groot onderhoud". (Oud: `PropertyAnnouncement`.)

### Worker-identificatie & verificatie (anti-misbruik) — **BESLIST: volledig overnemen**
Bedoeld voor gedeelde telefoons op de werkvloer:
1. **Naam** (voor- + achternaam) → opzoeken in het team. Statussen: `found` (→ icoonstap),
   `claimable` (match zonder icoon), `ambiguous` (meerdere matches), `not_found`.
2. **Persoonlijk icoon** bevestigen (uit een vaste set iconen) → bewijst identiteit.
   **BESLIST: 12 iconen** (oud waren er 8). Porten: `heart, plane, car, star, zap, gem, crown, moon`
   (+ PNG-assets uit `public/icons/login`); **4 nieuwe** toevoegen (voorstel: `bell, leaf, key,
   anchor`) met bijbehorende PNG's + vertaalde labels (`worker_icon.*` in nl/en/fr/de).
3. **Device-cookie** (1 jaar) onthoudt de worker per toestel; **unit-field-trust** ~12u na
   geslaagde on-site icoonbevestiging.
4. **Lockout**: na **2** foute icoonpogingen geblokkeerd (sessie + worker-rij
   `field_icon_locked_at`); beheerder kan ontgrendelen/icoon resetten.
5. **"Aanmelden als andere medewerker"** wist device/sessie/trust.
6. **Team-QR** wist verificatie bij elke scan (icoon opnieuw bevestigen). **BESLIST: open
   registratie** via team-QR als het team nog **geen** actieve workers heeft (onboarding: worker +
   icoon aanmaken). Daarna identificeren bestaande workers zich met naam + icoon.

### Worker-taakafhandeling
- **Start**: taak → `In uitvoering` (`started_at`).
- **Afhandelen**: optionele notitie (max 2000) + tot 4 foto's → vastgelegd als `IssueUpdate`
  (notitie/foto's); taak → `Afgehandeld` (`completed_at`).
- **Rollup**: zijn er geen open taken meer op de melding → melding → `Gesloten`.
- Worker ziet de **melder-foto's** bij de taak (op de publieke worker-weergave).

### Toegang/gating (oud: `ResidentPortalAccess`)
Portaal **inactief** (alle acties no-op, toon reden) bij o.a.: tenant zonder geldig abonnement,
tenant inactief, locatie inactief, unit inactief, team inactief. 404 bij onbekend token.

### Locale
`?lang=nl|fr|en|de` → sessie + cookie (1 jaar); taal-pillen op het portaal; standaard nl.

### Layout/UX (alleen structuur, geen kleuren)
Mobile-first kaart, grote tapbare tegels op home, full-width primaire knoppen, sticky acties,
foto-picker 96×96 met previews, worker-iconenraster, status-chips. Minimale `standard`-stijl.

### NIEUW t.o.v. oude app: moderatie/blur (alleen publiek)
De oude app heeft **geen** blur/goedkeuring (geen `approved_at`). Dit is **onze nieuwe eis**:
- Een via QR aangemaakte melding is **niet goedgekeurd** (`approved_at` null).
- Op de **publieke** portaalpagina's worden **omschrijving én foto's geblurd** met overlay
  "Wacht op controle" tot een beheerder/medewerker goedkeurt (`ApproveIssueAction`).
- **Beheerschermen blurren nooit** (zie de hardregel bovenaan).

### NIET overnemen (cruft / vereenvoudiging)
- `debug-d00184.log`-logging in `render()`; dode render-variabelen (`showFieldWorkerTaskEntry`,
  `issueUpdatePhotoPaths`).
- `property_*`-redirect, generieke `ReportIssue`/`TeamFieldPortal`, JSON
  `report.documents`/`report.announcements` (wij doen Livewire), sector/hospitality/contractor/owner-
  takken. Property→Location overal.
- Unit-portaal `claimable` doodlopend → **BESLIST**: registratie/onboarding loopt via **team-QR**
  (open registratie bij leeg team); de unit-QR doet enkel identificatie van bestaande workers.

### Bron-bestanden (oud, ter referentie bij herbouw)
`app/Livewire/FacilityUnitPortal.php`, `app/Livewire/FacilityTeamFieldPortal.php`,
`app/Support/FacilityUnitPortal.php`, `app/Support/FacilityTeamFieldPortal.php`,
`app/Support/FacilityQrIntake.php`, worker-stack (`FacilityWorkerSession`,
`FacilityWorkerPortalVerification`, `FacilityWorkerIcon`, `FacilityWorkerIconSignInGuard`,
`FacilityUnitWorkerSignIn`), `FacilityWorkerIssueEvidence`, `ResidentPortalAccess`,
`resources/views/livewire/facility-unit-portal.blade.php` (+ team-variant),
`resources/js/image-upload-compress.js`.
