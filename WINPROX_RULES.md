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

### 3.1 Actions — alle business logic
- Alle business logic in `app/Actions/[Module]/[Naam]Action.php`.
- Eén publieke methode: `handle()`.
- Actions doen DB-mutaties, notificaties, jobs, berekeningen. Een Action mag een andere Action aanroepen.
- Geen logica dupliceren: bestaat de workflow al, roep de Action aan.

### 3.2 Livewire-componenten — dun
- **Geen** DB-queries of `Model::create()` in Livewire.
- **Geen** business logic. Rol: input opvangen → valideren via Form Request → **één** Action aanroepen → UI tonen.

### 3.3 Form Requests — validatie
- Validatieregels altijd in `app/Http/Requests/[Module]/[Naam]Request.php`. Nooit in Livewire of Action.

### 3.4 API (indien aanwezig)
- Zelfde patroon als Livewire en **dezelfde** Form Requests.

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

### 4.4 Multi-tenancy
- Gedeelde DB met `tenant_id` per rij.
- Gebruik een **global scope via trait** (bv. `BelongsToTenant`) zodat elke query automatisch op de tenant filtert. Nooit handmatig vergeten.
- De scope is **bewust omzeilbaar** voor de platform-**superuser** (zie §8).

---

## 5. Lokalisatie (4 talen, klein & per pagina)

- Structuur: **`lang/[locale]/[page].json`** (één bestand per pagina/module). Plus `common.json` voor gedeelde labels (knoppen, statussen).
- Talen **altijd samen**: `nl`, `en`, `fr`, `de`. Uitbreidbaar (es, it, …) — talenlijst is data-gedreven.
- Bestanden **klein houden**: alleen sleutels die echt gebruikt worden. Volledig herschreven vanaf nul; geen oude rommel meeslepen.
- **Eén sleutel-conventie:** lowercase, punt-genest, betekenisvol: `[page].[sectie].[element]` (bv. `issues.list.empty_title`, `common.button.save`).
- **Nooit hardcoden** in Blade/PHP. Strikte JSON (geen comments/trailing comma's), UTF-8 **zonder BOM**.
- Na elke edit: `npm run fix:locales` → `npm run check:locales` → `npm run check:locales:parity`. Vier talen moeten identieke sleutels hebben.

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

### 6.4 Knoppen & pillen — ÉÉN definitie
- **Knop:** altijd `.btn` + één variant (`--primary` emerald, `--ghost` secundair, `--warning` amber, `--danger` red). Geometrie uit tokens (hoogte 2.5rem, radius 1rem, `font-weight:700`). Hover `translateY(-2px)` + zachte schaduw; active terug naar 0. **Geen** losse utility-knoppen per scherm.
- **Pil/status:** altijd `.wp-pill` + één variant per status: `--new` (Nieuw), `--progress` (In uitvoering), `--done` (Afgehandeld), `--closed` (Gesloten). **Geen tien stijlen voor dezelfde pil.**
- Nieuwe variant nodig? Definieer hem één keer in de gedeelde CSS, hergebruik overal.

### 6.5 Apparaat-targeting (responsive)
- **Beheersschermen** (dashboard, meldingen, beheer van locaties/units/teams) → **laptop/desktop-first**. Mogen breed/meerkoloms zijn, maar blijven bruikbaar op kleiner scherm.
- **Veld- en publieke schermen** (publieke QR-meldpagina, team-QR veldportaal, worker-schermen) → **mobiel-first**: één kolom, grote tap-doelen (knoppen min. 2.5rem hoog), belangrijkste actie onderaan binnen duimbereik.
- Zelfde tokens/componenten en **één** CSS-bundel; responsiviteit via eenvoudige breakpoints, geen aparte stylesheet-stack per apparaat.

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

## 9. Tests

- **Pest** + **factories** voor elk model.
- Dek de kern-flow: melding aanmaken → taak/taken → statusovergangen → afgeleide meldingstatus → tenant-isolatie → superuser-impersonatie.
- CI-check (tests + locale-parity + build) groen vóór merge.

---

## 10. Git

- Na elke afgeronde taak: **altijd** `git add` + `git commit` (+ `git push` zodra er een remote is). Niet vragen.
- Bij frontend-wijzigingen (`resources/css/**`, `resources/js/**`, views, Vite): **eerst** `npm run build`, gewijzigde `public/build/**` meecommitten.
- Raakt het `lang/**`: alle vier talen + `fix:locales`/`check:locales`/`check:locales:parity` vóór commit.

---

## 11. AI-veiligheid

- Lees bestaande code vóór je hem wijzigt. Minimale diffs; geen grote rewrites zonder vraag.
- Verwijder geen vertalingen/routes/CSS-classes zonder gebruik te checken.
- Bij twijfel: stop en vraag het exacte gewenste gedrag.
- Houd het **simpel**. Lees deze regels regelmatig.
