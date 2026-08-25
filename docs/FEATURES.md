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

Menu-volgorde (sidebar, accordion): Dashboard · **Werk** (Meldingen, Inspectierondes,
Taken, Kalender, Reserveringen, Unit checks) · **Plaatsen** (Categorieën, Locaties, Units tenant-breed) · **Mensen** (Backoffice = Beheerder/Medewerker;
Teams = Uitvoerder/Teamleader) · **Time** (module) · **Automatisering** (IoT Connect
Corporate — groep alleen als minstens één module aan staat) ·
**Organisatie** (Instellingen, API, Abonnement) · **Hulp** (FAQ, Handleiding, Juridisch,
Contact).

---

## 1. Dashboard

**Doel:** eerste scherm na login; overzicht van locaties, units, meldingen en taken.

**Starttemplate (optioneel, nieuwe organisaties)**
- Op het dashboard zolang er nog geen teams/categorieën/units zijn: knop **"Wil je op weg geholpen worden?"**. Een bestaande locatie zonder units wordt bij het laden van het template vervangen (locaties kun je in de UI alleen deactiveren).
- Modal: **kies je type organisatie** (hotel, ziekenhuis, industrie, gemeentebestuur) + uitleg wat er in de DB komt. Knoppen **Aanmaken** / **Annuleren**. Dit is **geen sectorsysteem** — alleen een starttemplate.
- Na aanmaken: kaart **Starttemplate aangemaakt** met het resultaat, volgende stap in het rood, pulserende knop **Ga naar units via Locaties** en knop **Verwijder starttemplate**.
- Namen van teams/categorieën/locatie/units worden in **alle talen** in de DB gezet. Hernoemen blijft altijd mogelijk. Zodra er meldingen zijn, kunnen units alleen nog **gedeactiveerd** worden; het template blijft dan bestaan.

**Header**
- Titel "Dashboard" + subtitel "Overzicht van je locaties, units, meldingen en taken".
- Knop **"Briefing afdrukken"** → printbare briefing met **alle taken die de teams vandaag
  moeten afhandelen** (dagoverzicht per team).
- **Proefperiode-capsule**: "Proefperiode nog X dagen." (zie §Abonnement).

**KPI-kaarten** (neutraal/minimaal, emerald enkel als subtiel accent) — **klikbaar** (elke tegel
linkt naar zijn lijst):
- Locaties — totaal → locatie-lijst
- Units — totaal → unit-lijst
- Nieuwe meldingen — aantal nieuw/open → meldingen (status=nieuw). **Alert-accent** als > 0.
- Open taken — "In uitvoering" → taken (open/in uitvoering).
- Nu aanwezig — open shifts zonder pauze → Time-aanwezigheid (Time-module; inbegrepen in proef, optioneel bij WinProx).
- **Conditioneel** (alleen tonen als telling **> 0** — geen vaste nullen / geen lege grid-gaten):
  - Te beoordelen — QR-wachtlijst → meldingen
  - Time-aandacht — open shifts met uitzondering → Time-alarmen
  - IoT-alarmen — open IoT-meldingen → IoT Connect (alleen bij IoT-module)

**Recente meldingen** (kaart met lijst)
- "Laatste activiteit door melders of team", met nadruk op **nieuwe** meldingen; laatste **5**.
- Rij: omschrijving · locatie — unit · adres · datum/tijd + "gemeld door {naam}" · status-pill ·
  **Melder bevestigd** als de QR-melder de e-maillink heeft bevestigd.
- **Net-aangemaakte melding** krijgt kort een highlight-accent in de lijst.
- Lege staat: nette "nog geen meldingen"-tekst (géén onboarding-CTA-blok uit V1).
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
WinProx-assistent (DB-Q&A + onbeantwoorde-vragen-tabel + superuser-mail + FAQ-koppeling),
klikbare KPI-tegels + alert-state, highlight net-aangemaakte melding, proefperiode-capsule.

**NIET overnemen uit V1-dashboard:** `facility-setup-panel` (onboarding-checklist), contractor-
panelen ("offerte-documenten" + "wacht op aannemers-reacties"), onboarding-banners,
proefperiode-**battery-png** (capsule wordt platte tekst), `SectorCapabilities`/`SectorUiCopy`/
`appMarketingFlow`, alle sector-/property-conditionals. Property → **Location** in copy en data.

---

## 2. Plaatsen (Categorieën, Locaties, Units)

**Doel:** beheer van **categorieën**, **locaties** en de **units** daaronder (machines, installaties, zones).
Drie submenu’s onder Plaatsen: **Categorieën** (alleen categorieën) · **Locaties** (alleen locaties) · **Units** (tenant-breed).
Locatie-lijst → klik op een locatie → **locatie-/unit-detailscherm**.
**Units** (tenant-breed): zonder categorieën → **Naar Categorieën**. Met categorieën zonder locaties → locatie-onboarding (**Locaties beheren**). Met locatie zonder units → unit-onboarding (**Units toevoegen**, naar de locatie).

### 2.0 Categorieën
- Header: titel "Categorieën" + **"Categorie toevoegen"**.
- Lijst van categorieën (naam, bewerken, verwijderen). Geen locaties op dit scherm.
- Lege staat: onboarding-kaart (één team mag aan alle categorieën) + pulserende **Categorie toevoegen**.
- Modal: naam, teams, GPS, reserveerbaar, unit checks, contactgegevens verplicht, e-mailbevestiging verplicht.

### 2.1 Locatie-lijst
- Header: titel "Locaties" + subtitel "Beheer je locaties. Klik op een locatie om units te beheren."
- Knop **"+ Nieuwe locatie toevoegen"** → modal (§2.2).
- **Zoek-kaart**: zoekveld "Zoek op locatie, plaats, postcode of straat" (zoekt op naam,
  plaats, postcode én straat). Hint "Klik op een locatie om te beheren". Checkbox
  **"Toon ook inactieve locaties"** (standaard verbergt inactieve).
- Locatie zonder units: unit-onboarding (**Units toevoegen**) boven de lijst, knop naar de locatie.
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
  - **"+ Unit toevoegen"** → unit per stuk (pulst wanneer de locatie nog geen units heeft).
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
Genereert in één keer meerdere units via **één reeks**.
- **Startnummer** (string, leidende nullen behouden, bv. `01` / `20` / `201`),
  **Aantal**, optioneel **Cijfers** (padding; leeg = auto uit startnummer), **Prefix**
  (bv. `Kamer `) en **Suffix** (bv. `-A`).
- **Voorbeeld**-blok (vóór categorie): live lijst van namen; **duplicaten** rood
  gemarkeerd → Aanmaken disabled.
- **Categorie** (optioneel, globaal voor de hele bulk).
- Acties: **Annuleren** / **Alle :count units aanmaken** (max. 500).

**Data (te bevestigen tegen schema):** `locations` (naam, straat, huisnummer, postcode, plaats,
iso_landcode, interne_notities, actief/inactief) en `units` (naam, location_id, internal_team_id,
qr_token, bulk-batch-referentie t.b.v. "recente bulk-aanmaak", actief/inactief).

**Device:** desktop-first (beheer).

**Nieuw t.o.v. huidige bouw (backlog):** locatie-CRUD + (de)activeren, zoek/inactief-filter,
locatie-detail met vorige/volgende, algemene locatie-QR, QR-stickerblad (QR-pack) downloaden,
unit-CRUD per stuk, **bulk-aanmaak met reeks + preview**, recente-bulk-beheer met veilige
verwijderregels (niet verwijderen wat een melding/taak heeft), unit-QR → publieke meld-link.

### 2.5 QR-stickerblad (.docx) — OVERNEMEN uit `winprox_old`
De Word-export werkt perfect in de oude app → **bijna 1-op-1 porten**, niet herbouwen. Formaat:
**Avery 55×55 mm, 15 stickers per A4** (5 rijen × 3 labelkolommen + gutterkolommen), QR ±42 mm met
WinProx-logo-overlay in het midden, unitnaam onder de QR (geen scan- of locatietekst).

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
  herkomst (QR/handmatig) + "gemeld door", toegewezen **team(s)**, status-pill, bij
  e-mailbevestigde QR-melders de pil **Melder bevestigd**. Net aangemaakte
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
V1: een melding kan **terugkerend** zijn (interval **waarde** + **eenheid** dag/week/maand/kwartaal/jaar,
**lead days**, **eerste vervaldatum**) en genereert periodiek taakcycli (koppelt aan **Kalender**).
Samen bouwen met **Meldingen + Kalender** (volgende fase, na het QR-portaal).
- Schema (nieuwe migratie, greenfield): `issues.is_recurring`, `recurrence_interval_value`,
  `recurrence_interval_unit`, `recurrence_lead_days`, `recurrence_next_due_at`,
  `recurrence_last_task_created_at`; op `tasks` velden voor cyclus (`due_at`, `scheduled_for`,
  `is_recurring_cycle`, `cycle_number`, `recurrence_issue_id`).
- Filter **"terugkerend"** wordt een echte filter (niet langer no-op); aanmaak-flow krijgt de
  recurring-opties; cycli verschijnen in de Kalender.
- **Pauzeren** stopt tijdelijk nieuwe cycli (`recurrence_paused_at`); **Gesloten**
  (`common.status.closed`, zelfde label als taakstatus) zet `recurrence_active = false`,
  sluit openstaande taken en de melding op **Gesloten** (heropenen reactiveert de reeks).

### 3.5 NIET overnemen (cruft / vereenvoudiging)
Onboarding/demo quick-flow, contractors + `TaskInvitation`/quoting/assignment-modes,
hospitality-triage (`awaitingTriageOnly`, `fulfillment_routing`), trades/work-types,
desktop-handoff-login, `report_finalized_at`, categorie-verplichting. Property→Location.

---

## 4. Taken

**Doel:** beheerlijst van alle **taken**. Een taak = werk onder een melding, toegewezen aan **één
team**. Bron: V1 `app/Livewire/Tasks.php` + `app/Support/FacilityTaskStatus.php` +
`app/Livewire/TaskDetail.php` (uitgedund: geen contractors/invitations/quoting, onboarding,
hospitality, trades/work-types). **Beheerscherm = nooit blur.**

### 4.0 Statusmapping (V1 → V2, verminderd)
| V1-taakstatus | V2 |
|---|---|
| `assigned` (+ legacy `open`/`invited`/`quoting`) | **Nieuw (Open)** (`new`) |
| `in_progress`, `on_hold` | **In uitvoering** (`in_progress`) |
| `completed` | **Afgehandeld** (`done`) |
| `not_executed` | **Gesloten** (`closed`) |

- Onze `TaskStatus`-enum (`new/in_progress/done/closed`) is leidend. `on_hold` (pauze, bv. "wacht op
  onderdeel") en `not_executed` (niet uitgevoerd) verdwijnen als **aparte** status maar blijven als
  **reden-notitie** behouden (zie 4.3).
- Een nieuwe taak start op **Nieuw** (team is al gekoppeld bij aanmaak/intake).

### 4.1 Lijst
- Header: titel + ghost **"Briefing afdrukken"** (taken van vandaag per team).
- Optioneel **"Nieuwe taken"**-snelblok bovenaan (recent aangemaakte taken met status Nieuw).
- **Filterkaart**: status-select (4 statussen + "alle") + GO!, **team**-select, **zoek**veld,
  checkbox **"terugkerend"** (alleen recurring-cycli). Zoeken over: taaknotitie/omschrijving,
  melding-omschrijving + melder, en locatie (naam/adres/postcode/plaats) + unitnaam.
- **Groepering per status** met accent-header + telbadge, volgorde
  **Nieuw → In uitvoering → Afgehandeld → Gesloten**, nieuwste eerst.
- **Taakkaart**: melding-omschrijving (onverkort — beheer), locatie · unit · adres, **team**,
  status-pill, evt. **gepland/vervaldatum** (recurring), "aangemaakt door". Klik → taakdetail.

### 4.2 Taakdetail
- Toont de melding-context + de taak; statuswijziging (4.3); notities/voortgang (`IssueUpdate`);
  evt. foto's van melder/worker (onverkort in beheer).

### 4.3 Statuswijziging (verminderde transities + reden-notitie)
Toegestane overgangen tussen de 4 statussen (afgeleid van V1):
- **Nieuw** → In uitvoering · Afgehandeld · **Gesloten** (= niet uitgevoerd, reden vereist).
- **In uitvoering** → Afgehandeld · **Gesloten** (reden vereist) · terug naar In uitvoering met
  "pauze"-notitie (was `on_hold`).
- **Afgehandeld** → (eind) — geen verdere overgang.
- **Gesloten** → heropenen naar Nieuw (correctie) toegestaan.
- **Verplichte reden-notitie** bij: pauzeren (oud `on_hold`) en sluiten-zonder-uitvoering
  (oud `not_executed`). Logica in een Action; notitie als `IssueUpdate`.
- Bij **Afgehandeld** → meldingstatus rolt mee op (alle taken klaar → melding Afgehandeld/Gesloten,
  bestaande `Issue::recalculateStatus`).

### 4.4 Terugkerende taakcycli
Taken die uit een **terugkerende melding** (§3.4) komen zijn `is_recurring_cycle` met `due_at`/
`scheduled_for`/`cycle_number`; verschijnen in de **Kalender** (§5). Samen bouwen met recurring.
Een **nieuwe cyclus** wordt alleen geopend als er geen openstaande cyclus-taak meer is
(`new` / `in_progress`). Onafgehandelde vorige taken worden **niet** automatisch gesloten;
`recurrence_next_due_at` schuift pas op bij het openen van de nieuwe cyclus.

### 4.5 NIET overnemen
Contractors + `TaskInvitation`/quoting/`expired_invitations`-filter, assign/invite-modi, onboarding/
demo, hospitality, trades/work-types, complexe morning-briefing-routing. Property→Location.

---

## 5. Kalender (Facility-only)

**Doel:** geplande taken en meldingen in een kalender. Bron: V1 `app/Livewire/Calendar.php`
(alleen de **Facility**-takken behouden; hospitality/contractor/onboarding eruit).
**Beheerscherm = nooit blur.**

### 5.1 Weergave & navigatie
- **Views:** maand / week / dag (toggle). Knoppen **vorige / volgende / vandaag**; periodelabel
  (bv. "mei 2026", "maandag 4 mei 2026", "Week 04/05 – 10/05"). Maandgrid start op **maandag**.
- **Type-toggle:** **Taken** (standaard), **Meldingen** of **Reserveringen**.
- **Locatie-filter:** toon alles of één locatie.
- **Categorie-filter** (alleen bij Reserveringen): alleen categorieën met `is_reservable`.
- Per dag: kleine entries met **status-badge** + titel/omschrijving; klik → detail (of
  reserveringenlijst). Maand toont max. **5** items per dag (+meer); week/dag = lijst;
  dagweergave paginert bij >50 items.
- URL onthoudt `view`, `type`, `date`, `location`, `category`.

### 5.2 Wat verschijnt waar
- **Taken-modus:** taken met **`scheduled_for`** of **`due_at`** (recurring-cycli §3.4/§4.4 +
  handmatig geplande taken), alleen van goedgekeurde meldingen, gesorteerd op prioriteit.
- **Meldingen-modus:** meldingen op **aanmaakdatum**.
- **Reserveringen-modus:** actieve pending holds + bevestigde boekingen (`blocking()`),
  gegroepeerd op startdatum; unit + tijdvak (+ gastnaam in week/dag).

### 5.3 Status-badges (verminderd)
- Taken/meldingen: onze pill-modifiers `new`→Nieuw, `in_progress`→In uitvoering,
  `done`→Afgehandeld, `closed`→Gesloten.
- Reserveringen: lifecycle-pillen pending / confirmed (cancelled/expired niet op de kalender).

### 5.4 Briefing
Vanuit de kalender: **Briefing afdrukken** — taken van die dag per team. In Taken-modus volgt
de briefingdatum de geselecteerde kalenderdag — zie Dashboard §1 / Taken §4.1.

### 5.5 NIET overnemen
Hospitality-takken, contractor-taaktypes (`type != internal`-splitsing), onboarding/demo, complexe
`FacilityTeamAccess` manager-scoping (optioneel later). Property→Location.

---

## 5c. Unit-reserveringen (Facility)

**Doel:** eenvoudig reserveren van units (vergaderzaal, voertuig, sleutel, …) via de unit-QR,
zonder SSO of verplichte account-login.

### Instellingen
- **Categorie:** vinkje `is_reservable` schakelt reserveren in voor alle units in die categorie.
- **Unit:** vinkje `allow_reservations` (standaard **uit**) kan reserveren per unit aanzetten.

### Gastflow (unit-QR)
- Tegel **Reserveren** op het portaal wanneer de unit reserveerbaar is.
- Gast vult voornaam, achternaam, e-mail, start/eind in → pending hold **30 minuten**.
- Bevestiging via e-mail (magic link). Na bevestiging: manage-link om te wijzigen/annuleren.
- Zelfde acties ook via QR (herkenning via cookie + e-mail).
- Overlap-check op bevestigde én actieve pending holds. Geen statusmachine daarbuiten
  (pending / confirmed / cancelled / expired).

### Beheer
- Sidebar **Reserveringen**: lijst, aanmaken (direct bevestigd), wijzigen, annuleren
  (admin + medewerker via Policy).
- Kalender: derde type-toggle **Reserveringen** naast taken/meldingen.

### API & webhooks
- `/api/v1/reservations` (CRUD/cancel) + events `reservation.created|confirmed|updated|cancelled`.

---

## 5d. Unit checks (Facility)

**Doel:** snelle OK / Niet OK-controle op de unit-QR (security, schoonmaak, techniek) zonder
de meldingenlijst te vervuilen. Los van ESG.

### Portaal (geverifieerde worker)
- Alleen zichtbaar als **categorie én unit** Unit checks toestaan (beide standaard uit;
      Plaatsen → Categorieën / unit bewerken).
- Tegel **Unit check** even breed als **Melding maken** (primaire tegel).
- Keuze **OK** of **Niet OK**; optioneel GPS als locatiebewijs.
- **OK** → rij in `unit_checks`, terug naar home.
- **Niet OK** → rij in `unit_checks`, daarna bestaande meldflow (`new`) blijft beschikbaar.
- Optionele **checklist** (indien gekoppeld aan de unit; templates onder **Teams**):
  vinkjes vóór OK.

### Beheer (`/unit-checks`)
- Historiek: tijdstip, resultaat, locatie/unit, uitvoerder/team, GPS-link.
- Filters: resultaat, locatie. Admin + medewerker via Policy.
- **Aan/uit:** Plaatsen → Categorieën (`allow_unit_checks`) én unit bewerken (`allow_unit_checks`);
  beide nodig, beide default uit.

### Checklists (Teams)
- Templates met vinkpunten: Teams → uitklap **Checklists** (optioneel gekoppeld aan een
  team, of gedeeld voor alle teams). Starters (schoonmaak / techniek / security) zijn
  kopieerbaar. Checklists zonder gekoppelde units kun je verwijderen; anders deactiveren.
- Koppelen aan unit via Locaties → unit bewerken. Dropdown toont gedeelde checklists én
  checklists van teams die aan de categorie van de unit hangen.
  Op de unit-QR verschijnen de vinkjes; bij **OK** moeten alle punten afgevinkt zijn.
- Checklistnaam en vinkpunten zijn **tenant-content**: brontaal in `original_language`,
  overige talen automatisch via Ollama (`unit_check_list_translations`, pending →
  completed/failed) zoals meldingen, locaties en ESG-indicatoren. Bij bewerken: uitklap
  **Vertaling** (zoals teams/categorieën) om per taal naam + punten te bekijken of te
  corrigeren. Het portaal toont de vertaalde labels; opgeslagen check-snapshots gebruiken
  altijd de **bronlabels**.

### Dagelijks terugkeren
- Interval-eenheid **dag** beschikbaar op terugkerende meldingen (naast week/maand/…).
- Bij Unit check **OK** met open goedgekeurde taak voor het team (zonder ESG-indicator):
  taak wordt gestart én afgehandeld; `task_id` staat op de check-rij.

### API & webhooks
- `POST /api/v1/units/{unit}/checks` — ability `units:update`; zie `docs/api/unit-checks.md`.
  Vereist `allow_unit_checks` op categorie én unit. `source=api`.
- `POST /api/v1/units/checks` — inbound sync via `external_unit_id` (unit-mapping
  `units.external_id`, beheer alleen zichtbaar met **API-toegang** / Corporate);
  optioneel `external_id` op de check (idempotent). `source=external`.
- Webhook `unit.check.recorded` bij elke **nieuwe** rij (payload bevat `external_id` /
  `unit_external_id` indien gezet).

### Fasering (afgerond)
| Fase | Levert | Status |
|------|--------|--------|
| **1** | Portaal OK/Niet OK → `unit_checks` + beheerlijst; Niet OK → meldflow; GPS optioneel | Klaar |
| **2** | Webhook `unit.check.recorded` + API POST | Klaar (meegeleverd met fase 1) |
| **3** | Interval **dag** + checklists + taakkoppeling via `task_id` | Klaar |
| **4** | Externe ID-mapping op unit + inbound `POST /units/checks` (IWMS/CMMS/ERP) | Klaar |

---

## 5e. Inspectierondes (Facility)

**Doel:** terugkerende melding met geordende stop-lijst (units). Eén taak per cyclus;
voortgang via Unit check OK (en optioneel skip) per stop. Compleet als er geen open
stops meer zijn voor **deze** cyclus-taak.

**BESLIST (fase 1):**
- Stops op de melding (`issue_round_stops`); ronde-issue heeft `unit_id = null`.
  `location_id` alleen als alle stops dezelfde locatie delen, anders null.
  Create- én show-picker: actieve units met unit checks, gegroepeerd per locatie
  (stops mogen over locaties heen). Units zonder checks staan niet in de lijst.
- **Beheer — intentie-flow:** knop *Inspectieronde plannen* op Meldingen (naast Nieuwe
  melding): stops + interval + team in één modal; onder water terugkerende melding +
  eerste taak. Bestaande melding-wizard blijft; rondestops daar optioneel.
- **Beheer — lijst:** sidebar **Werk → Inspectierondes** opent dezelfde Meldingen-pagina
  met `?recurring=1&inspection_round=1` (titel/ondertitel/lege staat + filter
  “Alleen inspectierondes”). Optioneel `?round_create=1` opent direct de plan-modal.
- Label: **Ronde · N stops**. Geen ESG op ronde-issues.
- Unit check OK: single-unit taak eerst, daarna ronde-voortgang (één transactie).
- Taak↔unit (2b): `issue.unit_id = U` **óf** U is stop — via `TaskBelongsToUnitAction`
  + Eloquent `Issue::belongsToUnit` (open unittaken + banner-exclude).
- Completion (4/4b): stops − (OK ∪ Niet OK ∪ skipped **voor huidige `task_id`**). Skips in
  `task_round_stop_skips` per task. Indexen `(task_id, unit_id)` op checks én skips.
- Unit check **Niet OK**: rij in `unit_checks` + meldformulier; stop telt mee als
  afgehandeld in de ronde (label **Niet OK**), daarna volgende stop. Bij openen van
  het meldformulier na Niet OK op een rondestop wordt de omschrijving vooringevuld
  (datum/tijd + “Reden: ”) via `SuggestNotOkReportPrefillAction` (resultaat van
  `RecordUnitCheckAndApplyTasksAction`), zodat de herkomst zichtbaar blijft op de melding.
- ≥2 stops: Form Request `min:2` bij opslaan.
- Impliciet meetellen (geen “start ronde”).
- Units zonder unit checks (unit uit, of categorie uit wanneer de unit een
  categorie heeft): niet in de stop-picker; een regel toont hoeveel units
  verborgen zijn. Is er geen enkele unit selecteerbaar, dan vervalt de picker
  (geen vinkjes, geen opslaan) en blijft een korte uitleg. Unit zonder categorie
  volgt alleen de unit-vlag. Bestaande stops worden verwijderd bij uitschakelen
  (en self-heal bij openen melding). Ronde met <2 stops over houdt geen stops meer.
- **Fase 2:** strikte stop-volgorde (alleen de eerstvolgende open stop mag OK/skip);
  rijke progress-UI (balk + genummerde stops met status, datum/uur en uitvoerder)
  op **unit-QR (elke stop)** én op **taakdetail in beheer**.

### Fasering
| Fase | Levert | Status |
|------|--------|--------|
| **1** | Stops CRUD, match, progress, complete, skip, portal 2b | Klaar |
| **2** | Strikte stop-volgorde + rijkere progress-UI | Klaar |

---

## 5b. ESG & Compliance (optionele module)

**Doel:** meetwaarden vastleggen bij terugkerende inspecties (duurzaamheid, compliance, meters).
Zichtbaar in de sidebar zodra `has_esg_module` aan staat (superuser: Platform → Tenants). **Alleen
admin**-accounts; medewerkers zien ESG niet.

### 5b.1 Indicatoren (`/esg/indicators`)
- **Meetdefinitie:** naam, type, optionele eenheid (getal) en min/max-drempels (getal).
- **Actief/inactief:** deactiveren i.p.v. verwijderen; bestaande metingen blijven.
- **Lege staat:** genummerde stappen (module → indicator → terugkerende melding → portaal).

#### 5b.1a Indicatortypes — ontwerp vs implementatie

**Oorspronkelijk productontwerp (5 universele types):**

| Type | Doel |
|------|------|
| Numeric | Meterstanden, tellingen |
| Boolean | Ja/nee-controles |
| Choice | Eén keuze uit vooraf gedefinieerde opties (bv. afvalcategorie) |
| MultiChoice | Meerdere keuzes uit dezelfde optielijst |
| Text | Vrije korte tekst |

**Sprint 1 datalaag (gebouwd):** `numeric`, `boolean`, `string`, `json` — geen `choice` /
`multi_choice`, geen `options`-kolom op indicatoren. `string` ≈ Text; `json` = technisch type
voor API-koppelingen (geen eindgebruiker-keuzelijst).

**Beslissing (juli 2026):**

- **`choice` wordt toegevoegd** als eerste ontbrekende eindgebruikerstype: `options` JSON op
  `esg_indicators`, meting in `value_string`, dropdown op unit-QR-portaal.
- **`multi_choice` is gebouwd** — zelfde `options` als Choice; meting in `value_json` als `string[]`;
  checkbox-UI op unit-QR-portaal; validatie en rapportage.
- **`json` blijft bestaan** naast Choice — niet deprecaten. Bestaande `json`-indicatoren en
  -metingen worden **niet** automatisch geconverteerd; wie een keuzelijst nodig heeft, maakt een
  nieuwe Choice-indicator aan.
- **Geen datamigratie** voor bestaande `json`-rijen: kolom `options` is nullable; `json`-type
  blijft ongewijzigd werken.

**Geïmplementeerde types (na Choice-sprint):** getal · ja/nee · keuzelijst · **meervoudige keuze** · tekst · gekoppeld
systeem (json).

**Choice-opties bewerken (§5b.6 #1 — afgerond):** optie met bestaande metingen is **niet
verwijderbaar** (readonly + servervalidatie). Waarden die niet meer in de huidige optielijst staan
worden in rapportage getoond als `:waarde (niet meer in lijst)`.

### 5b.2 Terugkerende melding + indicator
- Bij **nieuwe terugkerende melding** (stap 1): unit verplicht + keuze **ESG-indicator** (alleen actieve).
- Elke recurring-cyclus: taak op unit-QR-portaal; bij **afronden** verplichte meetwaarde + tijdstip
  (`recorded_at` = client, `created_at` = server).

### 5b.3 Metingen (`/esg/measurements`)
- **Append-only** overzicht: indicator, waarde, locatie/unit, uitvoerder, tijdstip; link naar taak.
- **Filters:** indicator, locatie, unit, datum van meting; drempelwaarschuwing buiten min/max.
- **Lege staat:** workflow naar eerste meting (indicator → melding → portaal of API).

### 5b.4 API & webhooks (Business+)
- `POST /api/v1/esg/measurements` — ability `esg:create`; zie `docs/api/esg.md`.
- Webhook `esg.measurement.recorded` bij elke nieuwe rij.

### 5b.5 Nog niet in scope (fase 2+)
`GET` metingen, CSV-export, dashboards/KPI's.

### 5b.6 Openstaande ESG-werkzaamheden (geprioriteerd)

**Status:** nog geen operationele ESG-metingen in productie — onderstaande punten zijn bewust
uitgesteld, maar moeten vóór of zodra er echte historiek is opgepakt. Volgorde is aanbevolen
prioriteit.

| # | Onderwerp | Status |
|---|-----------|--------|
| ~~**1**~~ | ~~**Choice-opties bewerken**~~ | **Afgerond** — blokkeren bij metingen in gebruik; legacy-label in rapportage. |
| ~~**2**~~ | ~~**Correctie-UI**~~ | **Afgerond** — backoffice `/esg/measurements`: Corrigeren-modal, append-only nieuwe rij, keten oorspronkelijk → correctie zichtbaar. |
| ~~**3**~~ | ~~**`multi_choice`-indicatortype**~~ | **Afgerond** — enum + `options`, checkbox-portaal, `value_json` als `string[]`, validatie, rapportage, optie-bescherming. |

**Correcties:** append-only — een correctie is een **nieuwe** rij met
`corrects_measurement_id`; de oorspronkelijke meting blijft ongewijzigd. Backoffice: knop
**Corrigeren** op oorspronkelijke rijen; correctierij toont pill + “Vervangt [waarde]”.

**Niet in deze lijst (breedere fase 2):** `GET /api/v1/esg/measurements`, CSV-export,
dashboards/KPI's — zie §5b.5.

---

## 5c. IoT Connect

**Doel:** hardware-onafhankelijke sensor-ingest. WinProx is geen IoT-platform: de gateway
stuurt events; WinProx zet die om in workflow.

### Plannen
- **Facility:** IoT Connect aan (`has_iot_module`) — alleen **alarm → Issue → Task**.
- **Corporate:** idem + **measurement → ESG-meting** (vereist ook `has_esg_module`).
- Time/trial: IoT uit (tenzij platform-toggle).

### Beheer (`/iot`)
- Gateways (token éénmalig tonen), sensoren (external_id → location/unit, optioneel ESG-indicator),
  alarmregels (operator/threshold/team/prio/tekst), recente events.
- Alleen **admin**; module via plan-entitlements of Platform → Tenants toggle.

### Ingest
- `POST /api/v1/iot/events` — gateway-token (`X-WinProx-Iot-Key` / Bearer), **buiten** full
  Sanctum `api.access` (Facility kan dus wel ingesten). Zie `docs/api/iot.md`.
- Events in `iot_events` (geen time-series dump): status processed/ignored/deduped/failed.
- Alarms: `IssueSource::Iot`, direct goedgekeurd; dedup zolang open taak bestaat voor dezelfde regel.
- Measurements: `esg_measurements.task_id` mag `null` (sensorpad); threshold follow-up blijft werken.

### Help / FAQ / marketing
- FAQ-item `iot` + help-chat patronen; page-help `iot.index`; handleiding-hoofdstuk.
- Marketing: `/features/iot`, about-link, sitemap/`llms.txt`, abonnementstatusregel.

---

## 6. Mensen (Backoffice + Teams)

**Doel:** twee pagina’s onder **Mensen**. **Backoffice** = collega-gebruikers (login).
**Teams** = checklists + operationele teams + workers.
Worker-aanmelding loopt via **Clock Point-QR** (`/time/{token}`), niet via een aparte team-QR.
**Sector/hospitality (`InternalTeams`, `category_slug`) eruit.**

### 6.0 Rollen (drie actoren — hard)
- **Beheerder (`admin`)** — login-account; **kan alles**: collega-accounts aanmaken/bewerken/
  deactiveren, **bedrijfsgegevens** aanpassen, teams aanmaken/deactiveren, workers beheren,
  abonnement. (`superuser` = platformrol die kan overnemen, los van dit scherm.)
- **Medewerker (`employee`)** — login-account; **kan minder**: operationeel (dashboard, meldingen
  incl. goedkeuren, taken, locaties/units, kalender) + workers/teams-inhoud beheren. **Geen**
  accountbeheer, **geen** bedrijfsgegevens, **geen** abonnement.
- **Worker** — veldmedewerker **zonder login** (identificatie via naam + persoonlijk icoon op het
  QR-portaal). **Handelt taken af.** Elke worker kan **teamleader** zijn.
- **Teamleader** = een worker met vlag `is_teamleader`. Mag **iconen vrijgeven** (lockout + icoon van
  een collega-worker resetten) — in het **veld-portaal** (teamleader bevestigt eerst eigen icoon).
- Implementatie: `users.role` (`admin`|`employee`), `workers.is_teamleader` (bool). Géén losse
  "team-manager"-user-pivot meer (RBAC verloopt via `role`).

### 6.1 Collega-gebruikers (alleen admin)
- Lijst + aanmaken/bewerken/deactiveren van gebruikers, met **rol** (admin/medewerker);
  welkomst-/accountmail (set-password-link via reset-broker). `users.is_active` → inactief = geen login.
- Sidebar **Mensen → Backoffice** (`/team?section=backoffice`). Alleen admin ziet dit menu-item.

### 6.2 Teams
- Sidebar **Mensen → Teams** (`/team?section=teams`): **checklists** (unit checks) + teamlijst.
- Lijst: teamnaam, aantal actieve workers, actief/inactief.
- Aanmaken/bewerken (naam, `sort_order`, actief) — **aanmaken/deactiveren = admin**; inhoud
  bewerken = admin of medewerker. Geen sectorcopy.
- **Clock Point-QR** (Time-module, standaard aan): printbare QR → `/time/{token}` voor aanmelden
  (naam + icoon), in-/uitklokken en teamtaken-overzicht. Afhandelen van taken blijft via de unit-QR.

### 6.3 Workers
- Per team: workers toevoegen (voor-/achternaam), lijst met **icoon-status**; **teamleader-vlag**
  (`is_teamleader`) toewijzen/intrekken; **actief/inactief**-toggle (V2-verbetering); verwijderen.
- **Icoon vrijgeven/resetten** (= ontgrendelt lockout + wist icoon/devices/sessies):
  - In **beheer** (Team-hub): door admin/medewerker.
  - In het **veld-portaal**: door een **teamleader** van het team (follow-up op de portaal-build).
- Icoon-set = **12** (zie QR-portaal); lockout automatisch na 2 foute pogingen.

### 6.4 Desktop SSO — Microsoft Entra OIDC (v1)

**Status:** gebouwd. Knop **Inloggen met Microsoft** op `/login` als `ENTRA_CLIENT_ID` +
`ENTRA_CLIENT_SECRET` gezet zijn. Callback: `/auth/microsoft/callback`.
**Kosten:** geen extra SSO-SaaS (geen WorkOS). App-registratie zelf is **Entra ID Free (€0)**.
Wel nodig: een **eigen Microsoft-directory** (Azure-account). Aanmelden vraagt meestal
**creditcard ter verificatie**; Microsoft rekent Entra ID Free niet aan. Geen VM’s of andere
Azure-diensten aanzetten — anders wordt het pay-as-you-go. **Niet** het M365-ontwikkelaarsprogramma
(sandbox, vaak Visual Studio-abonnement, niet voor productie).

**Wie:** alleen beheerder (`admin`) en medewerker (`employee`) op desktop.
**Niet:** uitvoerders, Clock Point-QR, unit-QR, gasten, superuser.

**Protocol:** Microsoft Entra ID via OpenID Connect (OIDC). Geen SAML, geen SCIM, geen Google,
geen betaalde SSO-dienst (WorkOS e.d.).

**Gedrag (wanneer de knop er is):**
- Alleen **bestaande** WinProx-users; e-mail moet gelijk zijn aan het Microsoft-account
  (`mail` of UPN). Geen match → weigeren, geen nieuw account.
- Geen automatische accounts (geen JIT): collega’s blijven via `CreateColleagueAction` +
  gebruikerslimiet.
- E-mail + wachtwoord blijft; `users.password` blijft verplicht (o.a. tenant-purge).
- `is_active=false` → weigeren. Superuser nooit via Entra.
- Zelfregistratie van een nieuwe tenant blijft e-mail + wachtwoord (geen “aanmelden met Microsoft”).
- API blijft Sanctum-tokens. SSO is geen koppeling met Ultimo/IWMS/CMMS.

**Voorwaarde — eigen directory.** Een app kan niet “buiten een map” bestaan. Zonder Azure-/Entra-
tenant toont App-registraties: *toepassingen buiten een map afgeschaft*. Oplossing: **Aanmelden
voor Azure** ([gratis Azure-account](https://azure.microsoft.com/pricing/purchase-options/azure-account))
met het WinProx-werkaccount. Daarna opnieuw **App-registraties**. Niet “Lid worden van het
M365-ontwikkelaarsprogramma”.

**App-registratie** (handmatig in [Entra admin center](https://entra.microsoft.com) — niet via
deze repo; Azure CLI ontbreekt lokaal):

1. **App registrations** → New registration. Naam: `WinProx`.
2. Supported account types: **Accounts in any organizational directory (Multitenant)** —
   geen persoonlijke Microsoft-accounts (Hotmail/Xbox).
3. Redirect URI, platform **Web** (allebei, exact zoals de browser de app opent):
   - Productie: `https://winprox.app/auth/microsoft/callback`
   - Lokaal: `http://localhost/auth/microsoft/callback` — Azure staat **http** alleen toe voor
     hostnaam `localhost` (niet `127.0.0.1`; die zou **https** eisen). `APP_URL` lokaal dus
     `http://localhost` (zonder poort in de Azure-URI).
4. **Certificates & secrets:** client secret aanmaken; waarde alleen in lokale `.env`, nooit in git.
5. API permissions (delegated, Microsoft Graph): `openid`, `profile`, `email`.
6. Bewaar Application (client) ID + secret intern. Placeholders: `.env.example` (`ENTRA_*`).
   `ENTRA_TENANT=organizations` (niet `common` — anders *unauthorized_client / not enabled for consumers*).

**Niet in v1:** wachtwoordlogin uitzetten, automatische user-provisioning, per-klant SAML,
SSO voor uitvoerders.

### 6.5 NIET overnemen
Hospitality `InternalTeams`-component, `category_slug`, triage-categorieën, `SectorUiCopy`/JSON
sector-suffixes, marketing-query-params. Property→Location.

**Device:** desktop-first (beheer). **Bouwvolgorde:** ná de QR-portaal-build (deelt worker/team-model).

---

## 7. Abonnement (proefperiode / plan)

**Doel:** abonnementsbeheer (admin): proefperiode/grace-status, planlimieten, plan kiezen, beheren.
Bron: `Subscription.php`, `subscription.blade.php`, `Tenant.php`, `config/billing.php`,
`EnsureActiveSubscriptionOrTrial`. **WinProx (jaar, 10/50/100 units) + optionele Time-prikklok + Corporate op maat.**

### 7.1 Status & toegang (behouden, generiek)
- Tenant-velden: `trial_ends_at`, `billing_plan`, `billing_active_until`, `billing_units_cap`
  (Corporate: afgesproken units = documentlimiet), `is_active`, `stripe_customer_id` (optioneel).
- `hasFullAppAccess()` = trial **of** betaald **of** grace; middleware blokkeert de app
  (behalve `billing.*`, `faq.*`, logout) wanneer geen toegang.
- **Proefperiode-capsule** (dashboard §1 + dit scherm): resterende dagen. V1 had battery-PNG's →
  **V2: tekstuele/minimalistische capsule** (geen PNG-animatie).

### 7.2 Plannen
- **WinProx** (`winprox_10` / `winprox_50` / `winprox_100`): **jaarlijks**, units + documenten (1:1),
  onbeperkt locaties/gebruikers/foto's. **Geen** Time, IoT, ESG of API. Clock Point blijft de
  aanmeld-QR (identiteit + takenlijst).
- **Time (prikklok)** — modulenaam Time, inhoud = prikklok: optionele plan-variant
  (`winprox_*_time`), zelfde units, `time_module` aan. Toeslag €29 / €39 / €49 per maand,
  op de **jaarfactuur** (×12). In-/uitklokken, pauze, aanwezigheid, urenstaat.
- **Trial:** 50 units, Time (prikklok) inbegrepen, geen IoT/ESG/API.
- **Corporate:** geen self-activate; superuser zet `billing_plan=corporate` + `billing_units_cap`
  via Platform → Organisaties. Time + IoT + ESG + API. Prijs op maat (geen Stripe price_id).
- **Legacy `facility_*`:** blijven in config voor bestaande abonnees (niet in catalogus, niet
  self-activate).
- Plankaarten + vergelijkingstabel op publieke `/pricing`; admin activeert WinProx (Stripe of
  gesimuleerd) met optioneel Time-vinkje. Corporate = mailto naar `billing.contact_email`.
- **Grace-periode** na verloop behouden.

### 7.3 Stripe
- Env: `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, zes `STRIPE_PRICE_WINPROX_*` (jaar, met/zonder
  Time; zie `.env.example`). Legacy `STRIPE_PRICE_FACILITY_*` mag blijven voor bestaande abonnees.
- Checkout voor self-activate tiers; Corporate **niet** in Stripe.

### 7.4 Gegevens verwijderen (tenant purge)
Self-service wispad voor tenant-admins (niet medewerkers), onder Abonnement.

**Trial** (`Tenant::purgeTrack() === trial`):
1. Export aanbieden (`account.data-export`) + checkbox + wachtwoord.
2. Bevestigingsmail naar **alle** admins → link zet status “bevestigd via e-mail”.
3. Admin voert uit (opnieuw wachtwoord) → SQL-snapshot **zonder media** → hard delete tenant (cascade) → resultaatmail met tellingen; snapshot 30 dagen.

**Betaald / grace / legacy** (`paid` track):
1. Zelfde start (export + wachtwoord) → mail naar alle admins.
2. Na bevestiging: **cool-down 7 dagen**; mail met “wordt doorgvoerd op [datum uur] door WinProx-administratie”.
3. App-banner: “Nog X dagen tot verwijderen…”. Reminder-mail op **T−2**.
4. Alleen **superuser** voert uit ná gepland tijdstip → snapshot → delete → resultaatmail.
5. Snapshot retentie **30 dagen** (`winprox:tenant-purge-maintenance`).

**Verlopen proef zonder abonnement** (`expired_trial` track, automatisch):
1. Na trial-einde: login blijft mogelijk **alleen** voor billing/abonnement (`EnsureTenantHasAppAccess`).
2. **T+7**: waarschuwingsmail naar **alle** admins (locale per admin) + plan auto-purge op **T+14**.
3. **T−2** (T+12): reminder-mail; CTA abonneren + exportvermelding.
4. **T+14**: scheduler voert purge uit via dezelfde `ExecuteTenantPurgeAction` (snapshot + hard delete).
5. Abonnementsactivatie annuleert openstaande `expired_trial`-aanvragen.
6. Self-service purge mag naast dit spoor bestaan (één open aanvraag tegelijk).
7. Bij elke geplande purge (paid cool-down of expired_trial) ook interne mail naar `info@winprox.app`
   (`tenant_purge.ops_notification_email`).

**Nooit gebruikt account** (`unused` track):
1. Zelfregistratie zonder **e-mailverificatie** blijft geblokkeerd (`EnsureUserEmailIsVerified`);
   publieke pagina's en QR-portalen blijven wel open. Bevestigingsmail: dezelfde preflight als promo
   (geldige syntax; geen verzending naar eerder gebouncete adressen). Uitschrijven van marketingmails blokkeert deze bevestigingsmail niet.
2. **T+7** (`unverified_registration_days`): `winprox:tenant-purge-maintenance` wist de tenant via
   dezelfde `ExecuteTenantPurgeAction` (snapshot + hard delete), **zonder** mail naar het
   (mogelijk valse) adres.
3. Superuser kan zo'n account ook meteen wissen in `/platform/tenants` — organisatienaam typen ter
   bevestiging; badge “E-mail niet bevestigd” markeert verdachte aanmeldingen. Het bevestigingsvenster
   toont eerst de aantallen die verdwijnen (`CollectTenantPurgeCountsAction`, alleen wat > 0 is).
4. Audit blijft op platformniveau bewaard (`tenant_purge.unused_deleted`, zonder `tenant_id`).
5. Collega's die een beheerder zelf aanmaakt gelden meteen als geverifieerd (geen zelfregistratie).

Config: `config/tenant_purge.php`. Actions onder `app/Actions/TenantPurge/`.

### 7.5 NIET overnemen
`micro_hospitality`, hospitality-trialplan-split, demo/marketing-query-params, sector-subtitels,
battery-PNG-widget (vervangen door tekstcapsule).

---

## 8. FAQ & kennisbank (+ WinProx-assistent)

**Doel:** in-app FAQ-accordeon voor ingelogde gebruikers; voedt de **help-chat/assistent**
(zie dashboard §1). Bron: `faq.blade.php`, `lang/*/faq.json`, `lang/*/page-help.json`,
`HelpChat.php`, `config/help_chat_faq.php`, `config/help_chat_page_help.php`,
`HelpChatKnowledgeBaseEntry`, `HelpChatUnansweredQuestion`.

### 8.1 FAQ-pagina
- Accordeon met FAQ-items per **slug** (geen DB-categoriemodel nodig). Facility-specifieke items
  (bv. interne teams, opvolging, **microsoft_login** voor desktop SSO) **herschrijven**
  (geen contractor-flow).
- **V2-opslag: `lang/[locale]/faq.json`** (4 talen, pariteit) — **niet** de oude dubbele
  JSON+PHP-opslag. Let op cross-platform regel: vermijd `__('FAQ')` dat botst met een `faq`-groep;
  gebruik unieke per-page keys.

### 8.2 WinProx-assistent (help-chat)
- Volgorde: **tenant-inzicht** (DB-tellingen) → **kennisbank** → **pagina-hulp / handleiding**
  (`page-help.json` via `HelpChatPageHelpMatcher`, zelfde bron als ManualChapters) →
  **FAQ-samenvattingen** (`config/help_chat_faq.php`) →
  **geen match** → vraag opslaan (`help_chat_unanswered_questions`) + **e-mail naar helpdesk/
  superuser**; gebruiker kan een antwoord laten **escaleren** naar de helpdesk.
- Rate-limit (bv. 30/min). Gekoppeld aan de FAQ (§dashboard-assistent = dezelfde feature).
- **Superuser-beheer** (buiten dit menu): onbeantwoorde vragen + Q&A-kennisbank.

### 8.3 NIET overnemen
Contractor/owner-FAQ-slugs, hospitality-only help-chat-entries, real-estate `how_it_works`-flow,
dubbele FAQ-opslag, sector-suffixes, mojibake (DE umlauts correct: ä/ö/ü/ß), foutieve facility-copy.

---

## 9. Juridische documenten (legal)

**Doel:** statische juridische documenten (privacy, voorwaarden, cookies, DPA, subverwerkers),
publiek + in-app, per taal. Bron: `routes/web.php` (legal.*), `layouts/legal.blade.php`,
`legal/content/{locale}/*`, `config/legal.php`.

### 9.1 Documenten & weergave
- Vijf documenten: **privacy, voorwaarden (terms), cookies, DPA, subverwerkers**.
- Per-locale inhoud; **laatst bijgewerkt**-datum (config); navigatie tussen documenten.
- Facility-ingelogd: opent in **nieuw tabblad** vanuit sidebar; geen "← WinProx"-marketingterug.

### 9.2 V2-opzet
- **Eén schone legal-layout** met token-CSS/`wp-*` (geen aparte Arial `legal-pages.css`-stack;
  eigen Vite-entry mag volgens stijlregel, maar dan token-gebaseerd).
- Labels/meta in `lang/[locale]/legal.json` (4 talen). Operator-/jurisdictieblok uit config.
- **Geen** acceptatie/versietracking (zoals V1) tenzij later gewenst.

### 9.3 NIET overnemen
`DemoSectorCopy` + `?sector=`-marketing, dubbele nav-labels, Arial niet-token CSS-stack.

---

## 10. Contact

**Doel:** eenvoudige contactpagina. Bron: `contact.blade.php` (gast), `contact-auth.blade.php`
(ingelogd). V1 = **geen** formulier/tabel, enkel **mailto** + verwijzing naar de assistent.

### 10.1 V2
- Ingelogd: app-layout, kaart met dashboard-link + **mailto** (`info@winprox.app`) + verwijzing naar
  de **assistent** rechtsonder.
- Gast: publieke layout, mailto + login-verwijzing.
- Sidebar-label **vertaald** (niet hardcoded "Contact").
- **BESLIST: mailto behouden** (zoals V1) + verwijzing naar de assistent; geen formulier/tabel.

### 10.2 NIET overnemen
Hospitality/facility lead-copy-varianten (één facility-copy), demo/marketing-query-params,
foutieve `card_body_facility` (hotel-tekst in DE).

---

## 11. Welcome / landingspagina (publiek)

**Doel:** publieke marketing-/landingspagina. **Facility-only, voorlopig ZONDER demo.**

**SEO / meertaligheid (hard):** elke marketingpagina heeft een **unieke URL per taal**
(`/{locale}/`, `/{locale}/hospitality|industry|healthcare|government|realestate`, `/{locale}/pricing`,
`/{locale}/contact`, `/{locale}/legal/…`). `/` en oude paden zonder prefix **redirecten** naar
de gelokaliseerde URL. Oude `/{locale}/promo` **301** naar de campagne-landing (met `ref`) of
`/{locale}/government`. In `<head>`: hreflang + canonical. Sitemap: `/sitemap.xml` (vermeld in
`robots.txt`). **IndexNow** (Bing e.a.): key-bestand in site-root +
`php artisan marketing:indexnow-submit` na deploy/content-wijziging (`config/indexnow.php`).
App/QR-portals blijven cookie/`?lang=` (geen SEO-prefix).

### 11.1 Structuur (behouden, opgeschoond)
- **Nav:** WinProx-logo, **taal-pillen** (NL/FR/EN/DE), **Inloggen**, **Account aanmaken**.
- **Hero:** kicker, titel, subtitel en CTA's naast `images/landing/general/welcome_01.jpg`
  (split zoals campagne-landings; algemene welcome-copy, geen sectortekst).
- **Probleem** (3 kaarten) · **Oplossing** (lead + **QR-leaflet** als illustratie) · **Features**
  (6 kaarten met iconen) · **Hoe werkt het** (stappen) · **CTA-band** · **screenshots** · **footer**
  (juridische links + contact).

### 11.2 Zonder demo (voorlopig)
- **Verwijderen:** alle "Probeer demo"-CTA's, `demo.index`-links en de flow-stap "demo".
- **Hoe werkt het** → herschrijven naar productflow: **1) QR scannen & melden · 2) taak naar team ·
  3) afgehandeld** (i.p.v. demo→register→login).
- **QR-leaflet:** behouden als **illustratie** (statisch voorbeeld); **geen** live demo-portaal-link
  (`WelcomePageDemoPortal`) zolang demo uit staat. Later eventueel koppelen aan een echt voorbeeld.
- Demo kan in een **latere fase** terugkomen (apart beslissen).

### 11.3 Schoon maken (V2-regels)
- `DemoSectorCopy::trans(...)` → **`lang/[locale]/welcome.json`** (4 talen, pariteit), unieke keys.
- **Geen** sector-/marketing-query-params (`$publicMarketingFlow`, `?sector=`), geen sector-redirects
  in de `/`-route.
- **`wp-welcome-*`** CSS behouden maar **token-gebaseerd** (emerald + neutralen, geen losse hexen);
  geen inline Tailwind-soup. Publieke layout (`layouts/public`).
- Screenshots: eigen, actuele WinProx-screenshots als assets (TODO) i.p.v. de oude.

### 11.4 NIET overnemen
Sector-hub/real-estate/hospitality welcome-varianten + `/`-sector-routing, demo-flow, marketing-query-
params, `DemoSectorCopy`. Property→Location in copy.

### 11.5 Campagne-landings (geen productsectoren)
Vijf **campagne-landings** (`/{locale}/hospitality|industry|healthcare|government|realestate`): één
Facility-product, sectorvoorbeelden in de copy. Alle vijf mogen langer (e-mailbezoeker).
Video: speler als het bestand bestaat (`{slug}_promo_{locale}.mp4` e.d.); elke landing
reserveert de videoplek (placeholder tot het bestand er is). Government-NL valt tijdelijk
terug op `video/nl/issue_nl_01.mp4` tot er een eigen government-video is.
Geen demo-CTA, geen prijs op de landing, geen “geen langdurig contract”. CTA’s alleen in de hero.
Foto’s per sector via `SectorLandingVisuals` (`public/images/landing/…`); ontbrekende assets
vallen terug op de tekstlayout. Vastgoed gebruikt `images/landing/general/`; de sluitbanner
legt witte copy over het donkere vlak van `welcome_07.jpg` (volledig beeld, beide personen zichtbaar).
Industrie gebruikt `images/landing/industry/` met gemixte beeldgroottes (compact, breed, feature)
en een sluitbanner met linkse scrim. Horeca gebruikt `images/landing/hospitality/` met selectie
(compact, breed, tall) en een sluitbanner met scrim. Zorg gebruikt `images/landing/healthcare/`
met selectie (compact, breed, tall, feature) en een sluitbanner met scrim. Overheid gebruikt
`images/landing/gouvernment/` met selectie (compact, breed, tall) en een sluitbanner met scrim.
Campagnes hebben een **verplichte landing**; `{{promo_url}}` bouwt die URL met `?ref=`. Dit is
**geen** V1-sectorhub en **geen** productvariant.

---

## QR-portaal (publiek, ná scan) — uitgebreid, overnemen uit `winprox_old`

> Dit is het scherm dat een bezoeker/worker ziet **na het scannen van een QR-code**. In de oude
> app is dit zeer uitgebreid en werkt het goed → **gedrag overnemen**, maar **schoon herschreven**
> (Property→Location, één sector, geen contractors/owners, geen debug-logging, geen dode variabelen).
> Onze huidige nieuwe bouw heeft slechts een **minimale** meld-/portaalversie; dit hoofdstuk
> beschrijft de **doel**-functionaliteit.

### QR-types / persona's
- **Unit-QR** (`/melden/{unit_token}`, oud: `facility.report.show`): **burger** én **on-site worker**
  zien dezelfde URL; de worker-acties verschijnen alleen als het toestel als veldtoestel herkend
  wordt (device-cookie/verified sessie).
- **Clock Point-QR** (`/time/{token}`): **worker**-aanmelding (naam + icoon), in-/uitklokken en
  overzicht van open teamtaken. **BESLIST:** via Clock Point zijn taakacties **alleen-lezen** —
  afhandelen moet via de **unit-QR**. (Vervangt de oude team-QR `/team/{token}`.)

### Unit-portaal — secties (burger)
`home · new · issues · issue_detail · documents · announcements`
- **home**: tenant-logo, locatie/unit-regel, tegels: **Nieuwe melding** (altijd), en — alleen indien
  niet leeg — **Open meldingen**, **Mededelingen**, **Documenten**. Voor veldworkers extra: blok met
  open taken + sign-in.
- **new** (melden): veld **omschrijving** (verplicht, min 3) + **tot 4 foto's** (`image`, max 10 MB).
  Optioneel voornaam/achternaam/e-mail; verplicht wanneer categorie **én** unit
  `require_reporter_contact` aan staan (alleen anonieme QR-melders — niet voor ingelogde
  veldwerkers). Optionele **e-mailbevestiging** (`require_reporter_email_verification` op
  categorie **én** unit): de melding wordt vastgehouden tot de melder de link in de mail
  klikt (geen Issue, taak of `IssueCreated` tot dan). Zelfde publieke QR-rate-limits als
  bij direct indienen. Preflight: geldige syntax; geen verzending naar eerder gebouncete
  adressen. Uitschrijven van marketingmails blokkeert deze bevestigingsmail niet.
  Submit zonder die poort → maakt:
  - `Issue` (`source=qr`, ongekeurd tot moderatie), gescoped op tenant/location/unit.
  - Foto's via `IssuePhotoStorage` (client comprimeert ≤1600px/JPEG ~72%, queue; server geen resize).
  - **Automatisch een taak** voor het **standaardteam van de unit** (oud: `FacilityQrIntake`),
    starttaakstatus = onze `Nieuw`/`assigned`-equivalent; geen team → enkel melding.
  - Daarna flash "melding verzonden" → sectie `issues`. Met e-mailbevestiging: flash "check inbox".
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
6. **Clock Point** herstelt verificatie via de device-cookie (geen wipe bij elke scan).
   **BESLIST: open registratie** via Clock Point als er precies **één** actief team zonder
   workers is (onboarding: worker + icoon aanmaken). Daarna identificeren bestaande workers
   zich met naam + icoon.

### Worker-taakafhandeling
- **Start**: taak → `In uitvoering` (`started_at`).
- **Afhandelen**: optionele notitie (max 2000) + tot 4 foto's → vastgelegd als `IssueUpdate`
  (notitie/foto's); taak → `Afgehandeld` (`completed_at`).
- **Rollup**: zijn er geen open taken meer op de melding → melding → `Gesloten`.
- Worker ziet de **melder-foto's** bij de taak (op de publieke worker-weergave).

### Toegang/gating (oud: `ResidentPortalAccess`)
Portaal **inactief** (alle acties no-op, toon reden) bij o.a.: tenant zonder geldig abonnement,
tenant inactief, locatie inactief, unit inactief, team inactief. Onbekende, beschadigde,
inactieve of vervallen QR-tokens tonen één publieke foutkaart met knop naar de startpagina
(niet de generieke 404). Niet-toegewezen stickers blijven het koppelportaal.

### Locale
`?lang=nl|fr|en|de|es|it` → sessie + cookie (1 jaar); taal-pillen op het portaal; standaard nl.
(Marketingpagina’s: zie §11 — locale in het pad, niet via `?lang=`.)

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
- Unit-portaal `claimable` doodlopend → **BESLIST**: registratie/onboarding loopt via **Clock Point**
  (open registratie bij precies één leeg team); de unit-QR doet enkel identificatie van bestaande workers.

### Bron-bestanden (oud, ter referentie bij herbouw)
`app/Livewire/FacilityUnitPortal.php`, `app/Livewire/FacilityTeamFieldPortal.php`,
`app/Support/FacilityUnitPortal.php`, `app/Support/FacilityTeamFieldPortal.php`,
`app/Support/FacilityQrIntake.php`, worker-stack (`FacilityWorkerSession`,
`FacilityWorkerPortalVerification`, `FacilityWorkerIcon`, `FacilityWorkerIconSignInGuard`,
`FacilityUnitWorkerSignIn`), `FacilityWorkerIssueEvidence`, `ResidentPortalAccess`,
`resources/views/livewire/facility-unit-portal.blade.php` (+ team-variant),
`resources/js/image-upload-compress.js`.
