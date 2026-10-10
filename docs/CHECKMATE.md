# WinProx Checkmate — spec

> Status: vastgelegd spec (analyse + beslissingen). Fase 1 (§9 punten 1–7)
> gebouwd; fase 2/3 volgen (§10).
> Regelkader: `WINPROX_RULES.md` §4.5 bevat de expliciete uitzondering die Checkmate
> toestaat als **plan-preset** op dezelfde codebase — geen fork, geen sectorlogica.

## 1. Positionering (hard)

Checkmate is een **RSZ-compliance-product voor veldwerk**, met schoonmaak als eerste
scope (`CiaoCleaning`; bouw volgt via `rsz.construction_scope_enabled`). Het is **geen**
generiek "lite CRM". Dat betekent:

- `checkmate_mode` forceert bij provisioning: `has_time_module = true`,
  `time_gps_visits = true`, `time_gps_visit_radius_meters = 100`,
  `presence_compliance_enabled = true` (CIAO staat aan vanaf de start; zie §6).
- CIAO-scope default `CiaoCleaning`; BCE-nummer (`enterprise_number`) vult de
  tenant zelf in via Instellingen.
- Prijs: **€5 per actieve seat per maand**, tenant kiest het aantal zelf.

## 2. Architectuur: plan-preset, geen product

- Zelfde codebase, routes, Actions, CIAO-pijplijn. Geen nieuwe repo, geen aparte app.
- Entitlement **`checkmate_mode`** in `config/billing.php` (plan `checkmate`,
  `checkmate_trial`), opgeslagen/afgeleid op tenant-niveau zoals `has_esg_module`.
- **Whitelist-gating** (niet blacklist): centrale resolver
  (`CheckmateMode::allowedAdminRoutes()` / `allowedPortalTiles()`) bepaalt welke
  admin-routes en portaal-tegels zichtbaar zijn. Nieuwe features zijn voor
  Checkmate-tenants **standaard onzichtbaar** — een feature verschijnt pas na
  expliciete toevoeging aan de whitelist.
- Whitelist admin: Dashboard, Plaatsen (Klanten, Locaties, Units), Uitvoerders,
  Time (aanwezigheid/uren/CIAO), Instellingen, Abonnement. Categorieën bestaan
  stil op de achtergrond (default «Klantlocatie») — niet in het menu.
  Whitelist portaal: klok, klantbezoek, pauze, mijn uren.

## 3. Datamodel

**Tabel `customers`** (dun, pure CRM-laag):
`tenant_id`, `name`, `contact_name`, `email`, `phone`, `is_active`, timestamps.

- `locations.customer_id` → nullable FK naar `customers`.
- `Location` is de **werkplek** (adres, GPS-pin, DDT
  `contractual_relationship_reference`) + standaard «Hele locatie»-unit
  (zelfde basis als Facility; QR mag via whitelist verborgen blijven).
- `Customer` is de **relatie** (wie communiceert/betaalt). Eén klant heeft
  gekoppelde locaties — geen apart “werkadres”-product.
- Tenant-vlag `customers_on_location` (Instellingen); Checkmate forceert die aan.
- Geen facturen, offertes, contract-documenten — bewust kaal. `WorkVisit` dekt
  "begonnen/gestopt bij klant" volledig (`location_id`, GPS-coords, `clock_source`).

## 4. Worker-flow (mobiel, hart van het product)

Instappunt = bestaande Clock Point-portaal (`/time/{token}` / `/cp/{token}`), als
persoonlijke link per bedrijf:

1. **Eénmalig koppelen**: admin mailt `/cp/{token}` (of worker scant eenmalig een QR)
   → naam + icoon/PIN → gsm gekoppeld (`AssertWorkerClockDeviceAction`,
   `WorkerDeviceSession`). Startscherm-icoon daarna voldoende om aan te melden én in
   en uit te klokken, zonder verse scan. Aanmelden klokt niet vanzelf in. Sticker en
   klokscherm-QR (`/time/{token}`) geven ook een prik.
2. **Inklokken** aan begin van de dag, vanuit het icoon.
3. **Klantbezoek**: klantlocaties gesorteerd op afstand (`SuggestNearbyClockUnitsAction`
   — bestaat al) → "Beginnen werken bij [klant]" → `StartWorkVisitAction` → CIAO IN.
   Nabijheidscheck is **blocking**: buiten straal → `visit_unit_out_of_range` —
   soft-fail ondermijnt het compliance-bewijs, dus geen override.
4. **Nieuwe klant onderweg** (dag-één, fase 1): één scherm — klant (bestaand kiezen
   óf nieuw) + adres + contact → GPS-pin automatisch van de gsm →
   `CreateCustomerWithLocationAction` (één transactie). Bij typen van een nieuwe
   klantnaam: live-match op bestaande klantnamen binnen de tenant (fuzzy `LIKE`,
   geen nieuwe infrastructuur) met zachte nudge "Lijkt op: [klant] — toch nieuw
   aanmaken?" — geen harde blokkade, de worker beslist.
5. **Pauze / klantwissel / uitklokken**: bestaande break- en visit-wissel-flow.
6. **Mijn uren**: bestaat.

Verborgen op het Checkmate-portaal: teamtaken, unit checks, inspectierondes,
evacuatielijst, uurrooster (initieel), meldingen.

## 5. Admin-flow (desktop)

Whitelist-schermen:

- **Dashboard** — Checkmate-variant: pending-CIAO-banner (§6), mini-onboarding
  (eerste uitvoerder → eerste klant), snelacties (klant/uitvoerder/aanwezigheid/
  Clock Point-QR), KPI's (nu aanwezig, bezoeken vandaag, seats, actieve klanten)
  en de laatste klantbezoeken. Geen facility-tegels, starter packs of
  meldingen-feed.
- **Plaatsen** — **Klanten**, **Locaties**, **Units** (geen Categorieën in het
  menu). Bij plan-activatie/site-unit wordt stil een defaultcategorie
  «Klantlocatie» gezet zodat Facility-gates niet blokkeren. Locatieformulier
  kan een klant koppelen. Op locatie-detail: **geen** documenten/mededelingen
  (Facility-portaalcontent). CSV-import op `/klanten` maakt altijd een
  «Hele locatie»-unit. Per klant maandstatistieken →
  `/klanten/{customer}/statistieken` (`SummarizeCustomerWorkStatsAction`).
- **Uitvoerders** — menu linkt naar `/team` (teams aanmaken + uitvoerder-
  beheer wonen daar). `/workers` blijft whitelisted als alleen-lezen lijst.
- **Time** — aanwezigheid, uren, CIAO-inzendingen.
- **Instellingen / Abonnement** — BCE, bedrijfsgegevens, seats, Clock Point-links.

Niet op de whitelist: Meldingen, Taken, Inspectierondes, Checklists, Kalender,
Reserveringen, Unitmetingen, ESG, IoT, API.

## 6. CIAO-activatie en pending-state

- **Checkmate**: `presence_compliance_enabled = true` vanaf plan-activering
  (`ApplyPlanEntitlementsAction`, checkmate-preset) — CIAO ís het product; de
  tenant vult alleen BCE/btw in via Instellingen. Submissions zonder geldige
  BCE/NISS/DDT worden lokaal `skipped` (`SubmitPresenceBatchAction` valideert
  vóór de RSZ-call), nooit verstuurd.
- **Facility/legacy pending-flow** (tenant vraagt CIAO aan, of een checkmate-
  tenant waar compliance nog uit stond): self-service formulier (BCE +
  scope-keuze) → `presence_compliance_scope` gezet, `presence_compliance_enabled`
  blijft uit → superuser bevestigt. **Geen nieuwe statuskolom** — scope-gezet +
  enabled-uit *is* de pending-state; platform-mail naar superuser zoals bij
  trial-requests.
- **Dashboard/CIAO-banner** zolang `presence_compliance_enabled === false`:
  "RSZ-doorgifte nog niet actief — BCE-verificatie loopt" (of "BCE nog niet
  ingevuld"). Copy moet eerlijk vermelden: *actief vanaf bevestiging; eerdere
  registraties worden niet ingediend.*
- **Semantiek (geverifieerd, `EnqueuePresenceFromTimeEventAction`)**: de
  compliance-check is **synchroon op event-tijdstip** (regel 36-38: disabled →
  `return null`, geen submission aangemaakt). Er is **geen backfill**: prikken vóór
  bevestiging gaan nooit naar de RSZ. Submissions die onder compliance zijn
  aangemaakt, blijven geldig en worden verzonden zelfs als compliance later uitgaat
  (de queue-job hervraagt de flag niet — bewust, geen bug).
- DDT is **optioneel** per locatie (particulieren werken buiten 30bis); inzendingen
  vereisen wel worker-NISS + tenant-BCE.

## 7. Billing

- `config/billing.php`: plan `checkmate` — `per_worker_monthly_eur = 5`,
  `includes_facility = false`, `time_module = true`, `checkmate_mode = true`,
  `self_activate = true`, `public_catalog = true`, `subscription_period_days = 30`.
  De tenant kiest het plan dus zélf op Abonnement (en het staat op `/pricing`).
  Plan `checkmate_trial` met zelfde preset + beperkte seats (bv. 3, 30 dagen):
  `platform_assignable = true` — enkel toewijsbaar door een superuser via
  Platform → Organisaties, niet zichtbaar in de catalogus.
- **Eén nieuwe kolom**: `tenants.billing_seats_qty` (nullable → plan-default).
  `seats_limit` leest uit qty indien gezet. Bestaande seat-telling
  (`currentSeatsCount()`: collega's + uitvoerders) blijft de licentie-basis.
- **Fase-1-regel (zolang Stripe niet live is)**: seat-wijziging direct actief,
  gefactureerd vanaf volgende periode — **geen proratie, geen tussentijdse
  facturatie**. Bij Stripe-live: qty = subscription-quantity, Stripe doet proratie;
  de tenant merkt geen regime-wijziging.
- **Verminder-guard**: `assertSeatsQtyNotBelowActive()` (spiegelbeeld van
  `assertCanAddSeats`) — nieuwe qty mag niet onder `currentSeatsCount()`; verlaging
  tot exact het aantal actieve seats is geldig. Error: "deactiveer eerst seats".
  Blokkeert op de qty-write-Action, niet in de UI alleen.

## 8. Marketing / URL

- Landingspagina `/{locale}/checkmate` (campagne-patroon zoals `work-on-location`),
  6 talen. Geen eigen subdomain nodig; `checkmate.winprox.app` mag later als
  redirect. App blijft winprox.app, `/cp/{token}` links ongewijzigd.
- Optioneel later: subtiel checkmate-thema via token-overschrijving — nooit een
  aparte CSS-stack.

## 9. Te bouwen (scope)

1. `customers`-tabel + `locations.customer_id` + model + policy.
2. Actions: `CreateCustomerAction`, `UpdateCustomerAction`,
   `CreateCustomerWithLocationAction` (worker-flow, één transactie, met
   naam-match voor dedup-nudge), locatie-Action aanpassen voor `customer_id`.
3. `checkmate_mode`-entitlement + `CheckmateMode`-whitelist + route/nav-gates
   (zelfde laag als `EnsureActiveSubscriptionOrTrial`).
4. Plannen `checkmate` + `checkmate_trial`; `billing_seats_qty` +
   `assertSeatsQtyNotBelowActive`; provisioning-values (GPS-visits aan, radius 100).
5. Checkmate-portaalvariant van `TimePortal` (slanke tegels, klant-kaart,
   klant-aanmaak).
6. Admin `/klanten`-scherm + CSV/Excel klant-import (`ImportCustomersAction`,
   `customers.import_batch_id`, undo-batch zoals locatie-import).
7. CIAO self-service aanvraag + pending-banner.
8. Landingspagina + locales (6 talen) + `product_docs`-update (verplicht, §10a RULES).

## 10. Test-scope (Pest, feature-tests — verplicht)

- Worker maakt klant + locatie onderweg (incl. tenant-isolatie).
- Visit start/stop bij klant: binnen straal oké, buiten straal
  `visit_unit_out_of_range` (blocking), pin-missing-case.
- CIAO:
  - `it('creates no presence submission while compliance pending')`
  - `it('submits events after activation but never pre-activation events')`
  - `it('evaluates compliance at event time, not at submission processing time')`
    (regressie-net: verhindert dat de enqueue ooit naar een queue verhuist zonder
    de timing-semantiek te breken).
- Seat-guard: verlaging tot onder `currentSeatsCount()` → geblokkeerd; tot exact
  gelijk → toegestaan.
- Plan-gates: checkmate-tenant krijgt 403/404 op niet-whitelist routes
  (`/issues`, `/units`, `/esg`, ...).
- Entitlement-provisioning: checkmate-plan zet GPS-visits aan en radius 100.

## 11. Fasering

- **Fase 1 (MVP)**: punten 1–7 — klant-model, plan + gates, slank portaal,
  klant-CRUD, CIAO-pending, **inclusief worker-klantaanmaak onderweg** (kernpitch).
- **Fase 2**: nabijheids-UI polijst, onboarding-preset-verfijningen.
- **Fase 3**: punt 8 — landing + verkoopflow (Stripe zodra operationeel),
  construction-scope.

## 12. Expliciet buiten scope

- Geen facturen/offertes/CRM-breedte; klant = naam + contact + gekoppelde locaties.
- Geen CAW-webservice, geen Dimona, geen loonberekening. De uren van de dienst geeft de klant door aan het sociaal secretariaat.
- Geen aparte app/subdomain-hosting, geen `tenant.sector`.
- Geen soft-fail op de nabijheidscheck; geen backfill van pre-activatie-events.
