# WinProx — featureoverzicht

Hoog-niveau overzicht van **WinProx V2** (Facility-app). Gebruik dit document om snel te zien *wat*
het product doet; voor scherm-voor-scherm bouwspecificatie en backlog, zie **`docs/FEATURES.md`**.

---

## Documentatie-landschap

| Document | Rol |
|----------|-----|
| **`WINPROX_RULES.md`** | Harde bouwregels (Actions, policies, locales, architectuur) — **altijd eerst lezen** |
| **`docs/FEATURES.md`** | Levende V2-specificatie per scherm (menu top-down, backlog, QR-portaal detail) |
| **`WINPROX_FEATURES.md`** (dit bestand) | Compact overzicht: wat WinProx Facility **is** en welke modules bestaan |
| **`WINPROX_DIRECTION.md`** | Roadmap & toekomst (achtergrond — niet proactief bouwen) |
| **`docs/MANUAL_SCREENSHOTS.md`** | Runbook handleiding-screenshots |
| **`docs/gemeente-promo.md`** | Runbook gemeente-promobrieven (marketing) |

**Prioriteit bij conflict:** `WINPROX_RULES.md` > `docs/FEATURES.md` > `WINPROX_DIRECTION.md` > V1-code (`winprox_old`).

---

## Product (hard)

- **Eén Facility-app:** melding → taken → afhandeling. Geen hospitality, contractors, owners, demo-sector.
- **Blur** alleen op **publieke QR-portalen** (niet-goedgekeurde meldingen). Beheer toont alles onverkort.
- **Talen:** `nl`, `en`, `fr`, `de`, `es`, `it` (`config/locales.php`, pariteit via `npm run check:locales:parity`).
- **Thema:** minimale `standard`-stijl (emerald accent, wit/lichtgrijs, `wp-*`-tokens).

---

## Kernflow

```
QR-scan (unit) → Melding → Taak(en) → intern team → worker op werkvloer → statusupdates → briefing/kalender
```

---

## Tenant-app (ingelogd, per organisatie)

Middleware: geldige tenant + trial/abonnement (`support.tenant`). Rollen: **admin** (alles) vs **employee** (operationeel, geen accounts/abonnement).

### Dashboard
- KPI-tegels: locaties, units, nieuwe meldingen, open taken (klikbaar).
- Recente meldingen; proefperiode-capsule.
- Link naar briefing afdrukken; WinProx-assistent (help-chat).

### Plaatsen (Categorieën, Locaties, Units)
- Locaties CRUD, zoeken, (de)activeren; adresvelden + landcode.
- **Categorieën** koppelen units aan **teams** (QR-routing).
- Units per locatie: CRUD, bulk-aanmaak met patroon, batch verwijderen (veiligheidsregels).
- **QR-stickerblad** (.docx, Avery 55×55) per locatie; unit-QR en locatie-QR.
- **Documenten** en **mededelingen** per locatie/unit (publiek op QR-portaal).
- Optioneel **GPS-rapport** per unit (veldportaal).

### Meldingen
- Lijst met filters (status, team, zoek, terugkerend); groepering per status.
- **4 statussen:** Nieuw · In uitvoering · Afgehandeld · Gesloten (afgeleid van taken).
- Aanmaak-flow (2 stappen): melding + taak/team; foto-upload (client-side compressie).
- **QR-moderatie:** nieuwe QR-meldingen wachten op goedkeuring (`ApproveIssueAction`).
- Optioneel: **e-mailbevestiging** (categorie én unit, AND; standaard uit). Tot de mailbox-link: geen Issue, taak of `IssueCreated`. Clock Point-uitvoerders uitgezonderd. Beheer: pil **Melder bevestigd**. Preflight: geldige syntax; geen mail naar eerder gebouncete adressen. Uitschrijven van marketingmails blokkeert deze bevestigingsmail niet.
- **Terugkerende meldingen** met kalendercycli.
- Notities/updates op melding (incl. foto's).

### Taken
- Lijst/detail; **4 statussen** + **prioriteit** (prio 1–4).
- Toewijzing aan **één intern team** per taak.
- Statuswijziging met verplichte reden bij sluiten/pauzeren (notitie als `IssueUpdate`).
- Terugkerende taakcycli gekoppeld aan kalender.

### Kalender
- Maand/week/dag; filter op locatie; toggle taken vs meldingen.
- Geplande taken (`scheduled_for`) en recurring-cycli.

### Team
- **Mensen → Backoffice:** collega’s (admin) — users met rol admin/employee.
- **Mensen → Teams:** checklists + interne teams + workers (zonder login): icoon, teamleader-vlag, Clock Point-QR.
- Desktop-login: e-mail + wachtwoord, plus **Inloggen met Microsoft** (Entra OIDC) voor admin/employee wanneer `ENTRA_*` gezet is. Uitvoerders: geen SSO.
- Worker-icoon reset / lockout-beheer.

### Overige tenant-schermen
- **Instellingen** — organisatieprofiel, logo, thema.
- **Abonnement** — trial, plannen, unit/user-limieten; Stripe (indien geconfigureerd).
- **FAQ & kennisbank** + help-chat.
- **Juridische documenten** (privacy, voorwaarden, cookies, DPA, subverwerkers).
- **Contact** — mailto + verwijzing assistent.
- **Handleiding** — algemeen, workers, teamleaders (meertalig).
- **Health** — tenant-gezondheidsoverzicht (admin).
- **API-instellingen** — tokens, webhook-endpoints, documentatie (`/api/v1`).

---

## Publieke schermen

| Route | Doel |
|-------|------|
| `/` | Welcome / landingspagina (marketing) |
| `/comparison` | Vergelijking / positioning |
| `/login`, `/register`, … | Auth |
| `/melden/{token}` | **Unit-QR-portaal** (melden, documenten, mededelingen, worker-modus) |
| `/time/{token}` | **Clock Point-portaal** (aanmelden, inklokken, teamtaken; afhandeling via unit-QR) |
| `/q/{token}` | QR-scan redirect (koppelen / doorverwijzen) |
| `/melden/onbekend/{token}` | Niet-toegewezen QR |
| `/promo` | 301 naar campagne-landing of `/{locale}/government` |
| `/{locale}/hospitality` | Campagne-landing horeca (Facility-copy) |
| `/{locale}/industry` | Campagne-landing industrie |
| `/{locale}/healthcare` | Campagne-landing zorg |
| `/{locale}/government` | Campagne-landing overheid (default `{{promo_url}}`) |
| `/legal/*`, `/contact` | Juridisch & contact (gast) |

**Unit-portaal:** mobiel-first; tot 4 foto's; auto-taak naar team van unit-categorie; blur tot goedkeuring.
**Worker:** naam + icoon (12 iconen), device-cookie, lockout na 2 foute pogingen, taak start/afhandelen.

---

## Platform (superuser)

Alleen voor platformbeheerders (`is_superuser`), routes onder `/platform`:

- Dashboard, tenants, users, auditlog
- Help-chat (onbeantwoorde vragen, kennisbank)
- Contactberichten
- Vertaling-sync (export/import vertaalslots)
- **Promo-campagnes** — Excel-import, optionele DOCX-brieven (print), e-mailqueue zonder bijlagen, kolommapping, campagne kopiëren
- **Promo-ontvangers** — tokens, QR-download, bezoekstatistieken
- Handleiding-screenshots (platform-tool)

Support: tenant **impersoneren** (`support.tenant`) voor hulp zonder tenant-login.

---

## API & integraties (V2-fundament)

- **REST API** `/api/v1` — Sanctum-tokens, tenant-scoped, JSON-envelope.
- **Uitgaande webhooks** — domein-events, HMAC, retries, delivery-log.
- **Inkoming** — geverifieerde hooks onder `/api/v1/hooks/…`.
- Business logic **altijd** in Actions; API/Livewire/CLI zijn dunne ingangen.

---

## Billing & limieten

- Proefperiode + grace; plannen met **unit-** en **user-limieten**.
- Stripe checkout / customer portal (optioneel via `.env`).
- Rate limiting per plan (API).

---

## Infra & onderhoud

- **Audit logging** op schrijfacties.
- **Data retention** — cron opruiming oude media/data (`RetentionPruneCommand`).
- **Vertaling** — `original_language` + vertaalslots; artisan sync/export/import.
- **E-mail** — account, notificaties, promo; unsubscribe/resubscribe.
- **GDPR** — data-export ingelogde user.

---

## Wat Facility expliciet níet heeft (V1-cruft)

- Geen **owners** / **contractors** / offertes / uitnodigingen.
- Geen hospitality- of real-estate-sector.
- Geen 7 taakstatussen (`on_hold`, `not_executed` als aparte status — wel als reden-notitie).
- Geen sector-query-params (`?sector=`) of demo-marketingflows op welcome.

---

## Terminologie V1 → V2

| V1 (`winprox_old`) | V2 (`winprox`) |
|--------------------|----------------|
| Property | **Location** |
| Unit | **Unit** |
| InternalTeam | **InternalTeam** |
| Worker | **Worker** |
| Issue | **Issue** |
| Task | **Task** |
| sector `facility` | geen sector (één app) |
| `/facility/report/{token}` | `/melden/{token}` |
| `/facility/team/{token}` | `/time/{token}` (Clock Point) |
| Standaard team per unit | **Category → team(s)** routing |
| Geen moderatie | **QR-goedkeuring + blur** publiek |
| 4 talen | **6 talen** (+ es, it) |
| 7 taakstatussen | **4 statussen** |

---

## V1-referentie (alleen bij porten)

Broncode oude app: `c:\winprox_old`. Relevante V1-paden:

| Onderdeel | V1-pad |
|-----------|--------|
| Teams UI | `app/Livewire/FacilityTeams.php` |
| Unit QR-portaal | `app/Livewire/FacilityUnitPortal.php` |
| Team QR-portaal (V1; V2 = Clock Point) | `app/Livewire/FacilityTeamFieldPortal.php` |
| QR-pack | `app/Http/Controllers/FacilityQrPackDownloadController.php` |
| Briefing | `app/Support/FacilityMorningBriefing.php` |
| QR-intake | `app/Support/FacilityQrIntake.php` |
| Worker-sessie | `app/Support/FacilityWorkerSession.php` |

Bij porten: altijd **`docs/FEATURES.md`** + **`WINPROX_RULES.md`** volgen; gedrag overnemen waar goed, architectuur V2 afdwingen.

---

## Status t.o.v. pariteit

- **Kern facility-flow** (locaties, units, meldingen, taken, kalender, team, QR-portaal, billing-basis): grotendeels **gebouwd** in V2.
- **Detail/backlog** per scherm (assistent-escalatie, exacte briefing-UX, …): zie **`docs/FEATURES.md`** secties “Nieuw t.o.v. huidige bouw” / “NIET overnemen”.
- **Platform/marketing** (promo-campagnes, gemeente-brieven): **aparte** superuser/marketing-laag, geen tenant-facility-feature.
